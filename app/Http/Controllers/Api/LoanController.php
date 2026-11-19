<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\CreditCategory;
use App\Models\CreditCategoryRule;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\LoanImage;
use App\Models\LoanInterestPayment;
use App\Models\Personal;
use App\Models\Product;
use App\Services\LoanPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de préstamos para la API.
 *
 * Expone el listado y el detalle de préstamos con sus relaciones
 * y valores calculados en tiempo real.
 */
class LoanController extends Controller
{
    /**
     * Lista los préstamos con paginación.
     *
     * Incluye cliente, producto y sucursal. Agrega el total de intereses
     * impagos como subconsulta para evitar N+1. Soporta filtros por
     * sucursal, estado, producto, búsqueda por ID/cliente/producto
     * y rango de fechas de creación.
     *
     * @param  Request  $request  Parámetros: branch_id, status, product_id, q,
     *                             date_from, date_to, per_page (1-100, def. 15)
     * @return JsonResponse  200 con colección paginada de préstamos (incluye numero y code).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = Loan::with(['client', 'product', 'branch'])
            ->addSelect([
                'unpaid_interest_total' => LoanInterestPayment::selectRaw('COALESCE(SUM(interest_amount - paid_amount), 0)')
                    ->whereColumn('loan_id', 'loans.id')
                    ->where('status', '!=', 'paid'),
            ])
            ->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $company?->id);
        } elseif ($company) {
            $query->where('company_id', $company->id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        $status = trim((string) $request->get('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('id', $q)
                    ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        $dateFrom = $request->get('date_from');
        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $loans = $query->paginate($perPage)->withQueryString();

        return response()->json($loans);
    }

    /**
     * Registra un nuevo préstamo desde la app móvil.
     *
     * Requiere una caja abierta asignada al usuario. La sucursal se deriva
     * automáticamente de esa caja (no es seleccionable por el cliente móvil).
     * Solo se acepta un client_id existente — la creación de clientes nuevos
     * se realiza mediante el endpoint dedicado de clientes.
     *
     * La lógica de campos del producto varía por categoría:
     *  - 1 (Vehículo): name, brand, model_year, plate, chassis obligatorios
     *  - 2 (Joyas): item_type, karat obligatorios
     *  - 3 (Garrafas): solo product_details opcional
     *  - 4 (Artículos): name, brand, serial_number obligatorios
     *
     * @param  Request  $request
     * @return JsonResponse  201 con el préstamo creado, 422 con errores de validación,
     *                        403 sin permiso, 409 sin caja abierta, 500 error inesperado.
     */
    public function store(Request $request): JsonResponse
    {
        $user    = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$company) {
            return response()->json(['message' => 'No tienes empresa activa asignada.'], 403);
        }

        if ($limitError = $this->planLimitError((int) $company->id, ['loans', 'products'])) {
            return response()->json(['message' => $limitError, 'code' => 'plan_limit_reached'], 403);
        }

        // Resolver caja abierta del usuario
        $activeCashSession = $this->resolveOpenCashSession($user, (int) $company->id);
        if (!$activeCashSession) {
            return response()->json([
                'message' => 'No hay una caja abierta asignada a tu usuario. Apertura una caja antes de registrar préstamos.',
                'code'    => 'no_open_cash_session',
            ], 409);
        }

        // La sucursal viene de la caja, no del request
        $activeCashSession->load('cashRegister.branch');
        $sessionBranch = $activeCashSession->cashRegister?->branch;
        if (!$sessionBranch) {
            return response()->json([
                'message' => 'La caja activa no tiene una sucursal asociada. Contacta al administrador.',
                'code'    => 'cash_register_no_branch',
            ], 409);
        }

        try {
            $validated = $request->validate([
                'client_id'              => 'required|integer|exists:clients,id',
                'credit_category_id'     => 'required|integer|exists:credit_categories,id',
                'amount'                 => 'required|numeric|min:0.01',
                'interest_rate'          => 'nullable|integer|min:1|max:100',
                'start_date'             => 'nullable|date',
                // Producto — campos varían según categoría
                'product_price'          => 'nullable|numeric|min:0',
                'product_details'        => 'nullable|string|max:1000',
                // Vehículo (cat 1)
                'product_name'           => 'nullable|string|max:255',
                'product_brand'          => 'nullable|string|max:255',
                'product_model_year'     => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'product_plate'          => 'nullable|string|max:120',
                'product_chassis'        => 'nullable|string|max:120',
                // Joyas (cat 2)
                'product_item_type'      => 'nullable|string|max:120',
                'product_karat'          => 'nullable|string|max:20',
                'product_material_type'  => 'nullable|string|max:60',
                // Artículos (cat 4)
                'product_serial_number'  => 'nullable|string|max:120',
                'product_item_model'     => 'nullable|string|max:255',
                // Imágenes multipart (images[])
                'images'                 => 'nullable|array|max:10',
                'images.*'               => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
                // Imágenes en base64 (images_base64[])
                'images_base64'          => 'nullable|array|max:10',
                'images_base64.*'        => 'nullable|string',
                'request_id'             => 'nullable|string|max:64',
            ], [
                'client_id.required'          => 'Selecciona un cliente.',
                'client_id.integer'           => 'El cliente no es válido.',
                'client_id.exists'            => 'El cliente no existe.',
                'credit_category_id.required' => 'Selecciona una categoría.',
                'credit_category_id.integer'  => 'La categoría no es válida.',
                'credit_category_id.exists'   => 'La categoría no existe.',
                'amount.required'             => 'Ingresa el monto del préstamo.',
                'amount.numeric'              => 'El monto debe ser un número.',
                'amount.min'                  => 'El monto debe ser mayor a 0.',
                'interest_rate.integer'       => 'La tasa de interés debe ser un número entero.',
                'interest_rate.min'           => 'La tasa de interés debe ser al menos 1%.',
                'interest_rate.max'           => 'La tasa de interés no puede superar el 100%.',
                'start_date.date'             => 'La fecha de inicio no es válida.',
                'product_price.numeric'       => 'El precio del producto debe ser un número.',
                'product_price.min'           => 'El precio del producto no puede ser negativo.',
                'product_details.max'         => 'Los detalles no pueden superar los 1000 caracteres.',
                'product_model_year.integer'  => 'El año del modelo debe ser un número.',
                'product_model_year.min'      => 'El año del modelo no es válido.',
                'product_model_year.max'      => 'El año del modelo no es válido.',
                'images.max'                  => 'Puedes adjuntar como máximo 10 imágenes.',
                'images.*.file'               => 'Cada imagen debe ser un archivo.',
                'images.*.mimes'              => 'Las imágenes deben ser JPG, PNG o WEBP.',
                'images.*.max'                => 'Cada imagen puede pesar como máximo 5 MB.',
                'images_base64.max'           => 'Puedes adjuntar como máximo 10 imágenes.',
                // Comodines al final: Laravel usa la primera clave que coincide.
                '*.max'                       => 'El texto es demasiado largo.',
                '*.string'                    => 'El valor debe ser texto.',
            ]);

            // No permitir desembolsar más de lo que hay en la caja abierta (igual que la web).
            $cashAvailable = $activeCashSession->currentBalance();
            if ((float) $validated['amount'] > $cashAvailable) {
                $msg = 'El monto del préstamo (' . \App\Support\Currency::format((float) $validated['amount'], $company)
                    . ') supera el saldo disponible en caja (' . \App\Support\Currency::format($cashAvailable, $company) . ').';
                return response()->json(['message' => $msg, 'errors' => ['amount' => [$msg]]], 422);
            }

            // Verificar que el cliente pertenece a la empresa
            $client = Client::where('id', $validated['client_id'])
                ->where('company_id', $company->id)
                ->where('active', true)
                ->first();
            if (!$client) {
                return response()->json(['message' => 'Cliente no encontrado o inactivo en esta empresa.'], 422);
            }

            // Verificar que la categoría pertenece a la empresa
            $category = CreditCategory::where('id', $validated['credit_category_id'])
                ->where('company_id', $company->id)
                ->first();
            if (!$category) {
                return response()->json(['message' => 'Categoría de crédito no válida para esta empresa.'], 422);
            }
            if (!$category->active) {
                return response()->json(['message' => 'La categoría seleccionada está desactivada.'], 422);
            }

            // Obtener regla activa de la categoría
            $rule = CreditCategoryRule::where('credit_category_id', $category->id)
                ->where('active', true)
                ->orderBy('interest_rate')
                ->orderBy('id')
                ->first();
            if (!$rule) {
                return response()->json([
                    'message' => 'La categoría seleccionada no tiene una regla de crédito activa.',
                    'field'   => 'credit_category_id',
                ], 422);
            }

            $categoryId = (int) $category->id;

            // Validar campos por categoría
            if ($categoryId === CreditCategory::CATEGORIA_VEHICULO) {
                $missing = [];
                foreach (['product_name' => 'nombre', 'product_brand' => 'marca', 'product_model_year' => 'modelo (año)', 'product_plate' => 'placa', 'product_chassis' => 'chasis'] as $field => $label) {
                    if (empty($validated[$field])) {
                        $missing[$field] = ['El campo ' . $label . ' es obligatorio para vehículos.'];
                    }
                }
                if ($missing) {
                    return response()->json(['message' => 'Faltan campos requeridos para la categoría.', 'errors' => $missing], 422);
                }
            }

            if ($categoryId === CreditCategory::CATEGORIA_JOYAS) {
                $missing = [];
                if (empty($validated['product_item_type'])) {
                    $missing['product_item_type'] = ['El tipo de joya es obligatorio.'];
                }
                if (empty($validated['product_karat'])) {
                    $missing['product_karat'] = ['El kilataje es obligatorio.'];
                }
                if ($missing) {
                    return response()->json(['message' => 'Faltan campos requeridos para la categoría.', 'errors' => $missing], 422);
                }
            }

            // Cualquier categoría distinta de 1/2/3 usa la regla genérica de Artículos.
            if (!in_array($categoryId, [
                CreditCategory::CATEGORIA_VEHICULO,
                CreditCategory::CATEGORIA_JOYAS,
                CreditCategory::CATEGORIA_GARRAFAS,
            ], true)) {
                $missing = [];
                foreach (['product_name' => 'nombre', 'product_brand' => 'marca', 'product_serial_number' => 'serial'] as $field => $label) {
                    if (empty($validated[$field])) {
                        $missing[$field] = ['El campo ' . $label . ' es obligatorio para artículos.'];
                    }
                }
                if ($missing) {
                    return response()->json(['message' => 'Faltan campos requeridos para la categoría.', 'errors' => $missing], 422);
                }
            }

            // Determinar nombre del producto
            $productName = trim((string) ($validated['product_name'] ?? ''));
            if ($categoryId === CreditCategory::CATEGORIA_JOYAS) {
                $productName = trim((string) ($validated['product_item_type'] ?? ''));
            }
            if ($categoryId === CreditCategory::CATEGORIA_GARRAFAS) {
                $productName = (string) $category->name;
            }

            // Evitar préstamos duplicados si la app reenvía el mismo intento.
            $requestId = trim((string) ($validated['request_id'] ?? ''));
            if ($requestId !== '') {
                $lockKey = 'api-loan-store:' . $user->id . ':' . $requestId;
                if (!Cache::add($lockKey, true, now()->addMinutes(10))) {
                    $lockKey = null;
                    return response()->json([
                        'message' => 'Este préstamo ya fue enviado. Revisa la lista de préstamos antes de intentarlo de nuevo.',
                        'code'    => 'duplicate_request',
                    ], 409);
                }
            }

            // Todo lo que sigue se guarda junto o no se guarda nada.
            DB::beginTransaction();

            // Cada préstamo crea su propio producto (cantidad 1, con su propio valor): los artículos usados
            // nunca son iguales aunque coincidan nombre y marca.
            $product = Product::create([
                'company_id'         => $company->id,
                'credit_category_id' => $category->id,
                'name'               => $productName,
                'sku'                => Product::makeSku($productName),
                'brand'              => $validated['product_brand'] ?? null,
                'model_year'         => $validated['product_model_year'] ?? null,
                'plate'              => $validated['product_plate'] ?? null,
                'chassis'            => $validated['product_chassis'] ?? null,
                'serial_number'      => $validated['product_serial_number'] ?? null,
                'karat'              => $validated['product_karat'] ?? null,
                'item_type'          => $validated['product_item_type'] ?? null,
                'item_model'         => $validated['product_item_model'] ?? null,
                'material_type'      => $validated['product_material_type'] ?? null,
                'description'        => $validated['product_details'] ?? null,
                'unit'               => 'unidad',
                'cost'               => $validated['amount'],
                'price'              => $validated['product_price'] ?? 0,
                'active'             => true,
            ]);

            // Calcular fechas
            $canSetStartDate = $user->is_super_admin
                || $user->hasPermissionInCompany('loans.set_start_date', $company);

            $startDate  = ($canSetStartDate && !empty($validated['start_date']))
                ? $validated['start_date']
                : now()->toDateString();
            $termMonths = (int) ($rule->term_months_limit ?? 1);
            $endDate    = \Carbon\Carbon::parse($startDate)->addMonths($termMonths)->toDateString();

            // Crear préstamo
            $loan = $company->loans()->create([
                'branch_id'                  => $sessionBranch->id,
                'product_id'                 => $product->id,
                'credit_category_id'         => $category->id,
                'credit_category_rule_id'    => $rule->id,
                'client_id'                  => $client->id,
                'amount'                     => $validated['amount'],
                'current_capital'            => $validated['amount'],
                // Solo Vehículo (1) y Joyas (2) permiten interés manual; el resto usa la regla.
                'interest_rate'              => in_array($categoryId, [CreditCategory::CATEGORIA_VEHICULO, CreditCategory::CATEGORIA_JOYAS], true)
                    ? ($validated['interest_rate'] ?? $rule->interest_rate)
                    : $rule->interest_rate,
                'interest_period'            => $rule->interest_period,
                'term_months'               => $termMonths,
                'notes'                      => null,
                'created_by'                 => $user->id,
                'approved_by'                => $user->id,
                'approved_at'                => now(),
                'start_date'                 => $startDate,
                'end_date'                   => $endDate,
                'status'                     => Loan::STATUS_ACTIVE,
                'cash_register_id'           => $activeCashSession->cash_register_id,
                'cash_register_session_id'   => $activeCashSession->id,
            ]);

            // El producto lleva el código del préstamo (PR-00123) y su sucursal.
            $product->assignOrigin($loan->code, $loan->branch_id);

            // Movimiento de caja — egreso por desembolso
            CashMovement::create([
                'company_id'               => $company->id,
                'cash_register_id'         => $activeCashSession->cash_register_id,
                'cash_register_session_id' => $activeCashSession->id,
                'user_id'                  => $user->id,
                'type'                     => 'expense',
                'category'                 => 'loan_disbursement',
                'amount'                   => $validated['amount'],
                'reference_type'           => Loan::class,
                'reference_id'             => $loan->id,
                'description'              => 'Desembolso préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
                'movement_date'            => now(),
            ]);

            $loan->update([
                'monthly_payment' => $loan->calculateMonthlyPayment(),
                'total_to_pay'    => $loan->calculateTotalToPay(),
            ]);

            // Colateral e inventario si la sucursal tiene almacén
            if ($sessionBranch->warehouse_id) {
                $movement = InventoryMovement::create([
                    'company_id'    => $company->id,
                    'warehouse_id'  => $sessionBranch->warehouse_id,
                    'branch_id'     => $loan->branch_id,
                    'product_id'    => $product->id,
                    'user_id'       => $user->id,
                    'type'          => 'in',
                    'quantity'      => 1,
                    'unit_cost'     => $product->cost ?? 0,
                    'reference'     => 'LOAN-RET-' . $loan->id,
                    'notes'         => 'Ingreso a retención por préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
                    'movement_date' => now(),
                ]);

                LoanCollateral::updateOrCreate(
                    ['loan_id' => $loan->id],
                    [
                        'company_id'            => $company->id,
                        'branch_id'             => $loan->branch_id,
                        'warehouse_id'          => $sessionBranch->warehouse_id,
                        'product_id'            => $product->id,
                        'inventory_movement_id' => $movement->id,
                        'status'                => 'retained',
                        'retained_at'           => now(),
                        'expected_release_date' => $endDate,
                        'notes'                 => 'Prenda retenida al crear préstamo',
                    ]
                );
            }

            // Imágenes — acepta base64 o multipart
            $imageFiles = $request->file('images') ?? [];
            foreach ($imageFiles as $image) {
                if (!$image) {
                    continue;
                }
                $path = $image->store('loan-images/loan-' . $loan->id, 'public');
                LoanImage::create([
                    'loan_id'       => $loan->id,
                    'path'          => $path,
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type'     => $image->getClientMimeType(),
                    'size_bytes'    => $image->getSize(),
                ]);
            }

            // Imágenes en base64 (campo images_base64[])
            foreach ((array) $request->input('images_base64', []) as $b64) {
                if (!is_string($b64) || !str_contains($b64, ',')) {
                    continue;
                }
                $data   = explode(',', $b64, 2)[1] ?? '';
                $binary = base64_decode($data, true);
                if (!$binary) {
                    continue;
                }
                $filename = 'foto-' . now()->format('YmdHisu') . '.jpg';
                $path     = 'loan-images/loan-' . $loan->id . '/' . $filename;
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $binary);
                LoanImage::create([
                    'loan_id'       => $loan->id,
                    'path'          => $path,
                    'original_name' => $filename,
                    'mime_type'     => 'image/jpeg',
                    'size_bytes'    => strlen($binary),
                ]);
            }

            // Generar primer período de interés (mismo comportamiento que el show() web)
            $loan->generatePendingInterestPeriods();

            DB::commit();

            $loan->load(['client', 'product', 'branch', 'creditCategory']);

            return response()->json([
                'message' => 'Préstamo registrado exitosamente.',
                'loan'    => [
                    'id'            => $loan->id,
                    'numero'        => $loan->numero,
                    'code'          => 'PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
                    'amount'        => (float) $loan->amount,
                    'interest_rate' => (float) $loan->interest_rate,
                    'interest_period' => $loan->interest_period,
                    'term_months'   => $loan->term_months,
                    'start_date'    => $loan->start_date,
                    'end_date'      => $loan->end_date,
                    'status'        => $loan->status,
                    'monthly_payment' => (float) $loan->monthly_payment,
                    'total_to_pay'  => (float) $loan->total_to_pay,
                    'branch'        => ['id' => $sessionBranch->id, 'name' => $sessionBranch->name],
                    'client'        => ['id' => $client->id, 'name' => $client->name],
                    'category'      => ['id' => $category->id, 'name' => $category->name],
                    'product'       => $product ? ['id' => $product->id, 'name' => $product->name] : null,
                    'created_at'    => $loan->created_at?->toIso8601String(),
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (!empty($lockKey)) {
                Cache::forget($lockKey);
            }
            Log::error('API store loan error', [
                'user_id'    => $user->id,
                'company_id' => $company->id,
                'exception'  => get_class($e),
                'message'    => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'No fue posible registrar el préstamo. Por favor intenta nuevamente.',
                'code'    => 'internal_error',
            ], 500);
        }
    }

    /**
     * Resolve the open cash register session assigned to the given user.
     */
    private function resolveOpenCashSession($user, int $companyId): ?CashRegisterSession
    {
        $personal = Personal::where('user_id', $user->id)->first();
        if (!$personal) {
            return null;
        }

        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) {
            return null;
        }

        return CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    /**
     * Retorna las categorías de crédito con sus reglas activas.
     *
     * Permite a la app móvil conocer la tasa de interés, periodo y plazo
     * que aplica por categoría, y si la tasa es editable por el usuario.
     *
     * @param  Request  $request
     * @return JsonResponse  200 con array de categorías y sus reglas.
     */
    public function categories(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');

        if (!$company) {
            return response()->json(['message' => 'No tienes empresa activa asignada.'], 403);
        }

        // Igual que la web: solo categorías activas.
        $categories = CreditCategory::where('company_id', $company->id)
            ->where('active', true)
            ->with(['rules' => fn ($q) => $q->where('active', true)->orderBy('interest_rate')->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->map(fn (CreditCategory $cat) => [
                'id'                    => $cat->id,
                'name'                  => $cat->name,
                // Solo Vehículo (1) y Joyas (2) permiten editar el interés.
                'interest_rate_editable' => in_array($cat->id, [
                    CreditCategory::CATEGORIA_VEHICULO,
                    CreditCategory::CATEGORIA_JOYAS,
                ], true),
                'rules' => $cat->rules->map(fn ($rule) => [
                    'id'              => $rule->id,
                    'name'            => $rule->name ?: ('Regla #' . $rule->id),
                    'interest_rate'   => (float) $rule->interest_rate,
                    'interest_period' => $rule->interest_period,
                    'term_months'     => (int) $rule->term_months_limit,
                    'min_amount'      => $rule->min_amount !== null ? (float) $rule->min_amount : null,
                    'max_amount'      => $rule->max_amount !== null ? (float) $rule->max_amount : null,
                ])->values(),
            ]);

        return response()->json(['categories' => $categories]);
    }

    /**
     * Retorna el detalle completo de un préstamo.
     *
     * Carga todas las relaciones relevantes: cliente, producto, sucursal,
     * categoría y regla de crédito, pagos con su registrador, pagos de
     * interés y amortizaciones. Además devuelve valores calculados en
     * tiempo real: capital actual, intereses impagos, monto de liquidación,
     * monto pendiente y principal vigente.
     *
     * @param  Request  $request
     * @param  Loan     $loan
     * @return JsonResponse  200 con loan (relaciones cargadas) y computed (valores calculados).
     */
    public function show(Request $request, Loan $loan): JsonResponse
    {
        $this->authorizeLoan($request, $loan);

        // Igual que la vista web: al abrir el préstamo se generan los periodos
        // de interés vencidos y se evalúa la mora.
        if (!$loan->trashed() && in_array($loan->status, Loan::OPEN_STATUSES, true)) {
            if ($loan->current_capital === null) {
                $loan->update(['current_capital' => $loan->amount]);
            }
            $loan->generatePendingInterestPeriods();
            $loan->checkOverdueStatus();
            $loan->refresh();
        }

        $loan->load([
            'client',
            'product',
            'branch',
            'creditCategory',
            'creditCategoryRule',
            'payments.registeredBy',
            'interestPayments' => fn ($q) => $q->withTrashed()
                ->with(['deletedBy.personal', 'restoredBy.personal', 'registeredBy.personal'])
                ->orderBy('period_number')
                ->orderBy('id'),
            'amortizations',
            'images',
        ]);

        // Fotos de la prenda con su URL pública (igual que la galería de la web).
        $loan->images->each(fn ($img) => $img->setAttribute('url', asset('storage/' . $img->path)));

        // Igual que la web: los periodos eliminados "al editar" que ya tienen
        // reemplazo activo no se muestran; a cada periodo se le agregan los datos
        // listos para mostrar (estado, vencido, nombres de quién eliminó/restauró).
        $activePeriods = $loan->interestPayments->filter(fn ($ip) => !$ip->trashed())->pluck('period_number')->all();
        $personName = fn ($user) => $user ? ($user->personal?->full_name ?? $user->name) : null;
        $loan->setRelation('interestPayments', $loan->interestPayments
            ->reject(fn ($ip) => $ip->trashed()
                && $ip->deletion_reason === LoanInterestPayment::REASON_LOAN_EDITED
                && in_array($ip->period_number, $activePeriods))
            ->map(function ($ip) use ($personName, $activePeriods) {
                $isPaid = $ip->status === 'paid';
                $isTrashed = $ip->trashed();
                $ip->setAttribute('display_status', $isTrashed ? 'deleted'
                    : ($isPaid ? 'paid' : ($ip->period_end->lt(now()) ? 'overdue' : 'pending')));
                $ip->setAttribute('remaining_amount', $ip->getRemainingAmount());
                $ip->setAttribute('deleted_by_name', $personName($ip->deletedBy));
                $ip->setAttribute('restored_by_name', $personName($ip->restoredBy));
                $ip->setAttribute('registered_by_name', $personName($ip->registeredBy));
                $ip->setAttribute('deletion_reason_label', $ip->deletion_reason_label);
                // Restaurable solo si no hay otro interés activo en ese periodo.
                $ip->setAttribute('restorable', $isTrashed && !in_array($ip->period_number, $activePeriods));
                $ip->makeHidden(['deletedBy', 'restoredBy', 'registeredBy']);
                return $ip;
            })
            ->values());

        $oldestPending = $loan->interestPayments
            ->filter(fn ($ip) => !$ip->trashed() && $ip->status !== 'paid')
            ->sortBy('period_number')
            ->first();

        $payments = app(LoanPaymentService::class);
        $user = $request->user();
        $userId = (int) $user->id;
        $can = fn (string ...$slugs) => collect($slugs)->contains(fn ($slug) => $user->hasPermissionInCompany($slug, $loan->company));

        return response()->json([
            'loan' => $loan,
            'computed' => [
                'current_capital' => $loan->getCurrentCapital(),
                'unpaid_interest_total' => $loan->getUnpaidInterestTotal(),
                'liquidation_amount' => $loan->getLiquidationAmount(),
                'pending_amount' => $loan->getPendingAmount(),
                'outstanding_principal' => $loan->getOutstandingPrincipal(),
                'pending_penalty' => $loan->getPendingPenalty(),
                'penalty_reached_at' => $loan->getPenaltyReachedAt()?->toDateString(),
                'grace_ends_at' => $loan->getGraceEndsAt()?->toDateString(),
                'oldest_pending_interest_id' => $oldestPending?->id,
                // Motivo por el que no se puede cobrar (null = se puede).
                'actions_locked_reason' => $payments->getActionsLockedReason($loan),
                'cash_session_error' => $payments->getCashSessionError($userId, (int) $loan->company_id),
            ],
            // Permisos del usuario para las acciones del detalle (mismos que las rutas).
            'abilities' => [
                'pay_interest' => $can('payments.create'),
                'pay_penalty' => $can('payments.create'),
                'amortize' => $can('loans.edit', 'loans.amortize', 'payments.create'),
                'liquidate' => $can('loans.edit', 'loans.liquidate', 'payments.create'),
                'delete_interest' => $can('loans.interest.delete'),
                'restore_interest' => $can('loans.interest.restore'),
            ],
        ]);
    }

    /**
     * Verifica que el usuario tenga acceso al préstamo.
     *
     * Aborta con 403 si el préstamo no pertenece a la empresa del token.
     *
     * @param  Request  $request
     * @param  Loan     $loan
     */
    protected function authorizeLoan(Request $request, Loan $loan): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $loan->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a este préstamo.');
        }

        if ($user->is_super_admin && $company && $loan->company_id !== $company->id) {
            abort(403, 'El préstamo no pertenece a la empresa del token.');
        }
    }
}
