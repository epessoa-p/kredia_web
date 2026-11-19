<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\CreditCategory;
use App\Models\CreditCategoryRule;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\LoanImage;
use App\Models\LoanPayment;
use App\Models\Personal;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class LoanController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-permission:loans.view');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $company) {
            $this->updateOverdueCollateralStatuses((int) $company->id);
        }

        $branchesQuery = Branch::where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $branchesQuery->where('company_id', $company?->id);
        }
        $branches = $branchesQuery->get();

        $selectedBranchId = $request->integer('sucursal_id');

        // Restrict branch visibility for non-manager staff (force their branch, no "TODOS")
        if (!$user->is_super_admin) {
            $personal = Personal::where('user_id', $user->id)->first();
            if ($personal && !$personal->isManager() && $personal->branch_id) {
                $branches = $branches->where('id', $personal->branch_id)->values();
                $selectedBranchId = (int) $personal->branch_id;
            }
        }

        // Validate the selected branch belongs to the allowed list (0 = all)
        if ($selectedBranchId && $branches->isNotEmpty() && !$branches->pluck('id')->contains($selectedBranchId)) {
            $selectedBranchId = 0;
        }

        $deletedFilter = $request->get('deleted_filter', '');

        if ($deletedFilter === 'deleted') {
            $query = Loan::onlyTrashed()->with('client', 'product', 'creditCategory', 'branch')->latest();
        } elseif ($deletedFilter === 'all') {
            $query = Loan::withTrashed()->with('client', 'product', 'creditCategory', 'branch')->latest();
        } else {
            $query = Loan::with('client', 'product', 'creditCategory', 'branch')->latest();
        }

        // Personal que registró el préstamo (usuario -> personal).
        $query->with('createdBy.personal');

        // Colateral (para etiquetar los préstamos cuyo producto ya fue vendido).
        $query->with('collateral');

        if (!$user->is_super_admin) {
            $query->where('company_id', $company?->id);
        }

        if ($selectedBranchId) {
            $query->where('branch_id', $selectedBranchId);
        }

        $status = trim((string) $request->get('status', ''));
        if ($status !== '') {
            if ($status === 'delinquent') {
                $query->whereIn('status', Loan::DELINQUENT_STATUSES);
            } else {
                $normalizedStatus = Loan::normalizeStatus($status);
                if ($normalizedStatus) {
                    $query->where('status', $normalizedStatus);
                }
            }
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            // Soporta múltiples términos separados por coma o espacio (ej. "146, 147"):
            // cada término busca por código (numero), cliente o producto, y se combinan con OR.
            $terms = preg_split('/[,\s]+/', $q, -1, PREG_SPLIT_NO_EMPTY);
            $query->where(function ($outer) use ($terms) {
                foreach ($terms as $term) {
                    $outer->orWhere(function ($sub) use ($term) {
                        $sub->where('numero', 'like', "%{$term}%")
                            ->orWhereHas('client', fn ($clientQ) => $clientQ->where('name', 'like', "%{$term}%"))
                            ->orWhereHas('product', fn ($productQ) => $productQ->where('name', 'like', "%{$term}%"));
                    });
                }
            });
        }

        $categoryId = $request->integer('credit_category_id');
        if ($categoryId) {
            $query->where('credit_category_id', $categoryId);
        }

        $personalId = $request->integer('personal_id');
        if ($personalId) {
            $query->whereHas('createdBy.personal', fn ($p) => $p->where('id', $personalId));
        }

        // Filtro: producto en garantía vendido / no vendido.
        $soldFilter = trim((string) $request->get('sold_filter', ''));
        if ($soldFilter === 'sold') {
            $query->whereHas('collateral', fn ($c) => $c->where('status', 'sold'));
        } elseif ($soldFilter === 'not_sold') {
            $query->whereDoesntHave('collateral', fn ($c) => $c->where('status', 'sold'));
        }

        // Cantidad de intereses sin pagar en cada préstamo (para la etiqueta y el filtro).
        $query->withCount(['interestPayments as unpaid_interests_count' => fn ($ip) => $ip->where('status', '!=', 'paid')]);

        // Filtro: préstamos con N intereses sin pagar (selección múltiple: 1, 2, 3, 4, 5+).
        $unpaidCounts = collect((array) $request->input('unpaid_interests', []))
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => in_array($v, ['1', '2', '3', '4', '5+'], true))
            ->unique()
            ->values()
            ->all();
        if (!empty($unpaidCounts)) {
            $unpaidSql = '(select count(*) from loan_interest_payments lip'
                . ' where lip.loan_id = loans.id and lip.status != ? and lip.deleted_at is null)';
            $exact = array_map('intval', array_values(array_filter($unpaidCounts, fn ($v) => $v !== '5+')));

            $query->where(function ($q) use ($unpaidSql, $exact, $unpaidCounts) {
                if (!empty($exact)) {
                    $placeholders = implode(',', array_fill(0, count($exact), '?'));
                    $q->orWhereRaw("{$unpaidSql} in ({$placeholders})", array_merge(['paid'], $exact));
                }
                if (in_array('5+', $unpaidCounts, true)) {
                    $q->orWhereRaw("{$unpaidSql} >= 5", ['paid']);
                }
            });
        }

        $dateFrom = $request->get('date_from');
        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $categoriesQuery = CreditCategory::where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $categoriesQuery->where('company_id', $company?->id);
        }
        $categories = $categoriesQuery->get();

        // Personal (con usuario) para el filtro de "registrado por".
        $personalsQuery = Personal::whereNotNull('user_id')->where('active', true)->orderBy('full_name');
        if (!$user->is_super_admin) {
            $personalsQuery->where('company_id', $company?->id);
        }
        $personals = $personalsQuery->get();

        $loans = $query->paginate(15)->withQueryString();
        $hasActiveCashSession = $this->resolveOpenCashSession((int) $company?->id) !== null;

        return view('loans.index', compact(
            'loans',
            'branches',
            'selectedBranchId',
            'status',
            'q',
            'categoryId',
            'categories',
            'personals',
            'personalId',
            'soldFilter',
            'unpaidCounts',
            'dateFrom',
            'dateTo',
            'hasActiveCashSession',
            'deletedFilter'
        ));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa para crear préstamos.']);
        }

        $branches = $user->is_super_admin
            ? Branch::where('active', true)->orderBy('name')->get()
            : Branch::where('company_id', $company->id)->where('active', true)->orderBy('name')->get();

        $defaultBranchId = old('branch_id', $request->integer('sucursal_id'));
        if (empty($defaultBranchId) && $branches->isNotEmpty()) {
            $defaultBranchId = $branches->first()->id;
        }
        if ($defaultBranchId && $branches->isNotEmpty() && !$branches->pluck('id')->contains((int) $defaultBranchId)) {
            $defaultBranchId = $branches->first()->id;
        }

        $products = $user->is_super_admin
            ? Product::where('active', true)->with('creditCategory')->orderBy('name')->get()
            : Product::where('company_id', $company->id)->where('active', true)->with('creditCategory')->orderBy('name')->get();

        $creditCategories = $user->is_super_admin
            ? CreditCategory::where('active', true)->with(['rules' => fn ($q) => $q->where('active', true)->orderBy('interest_rate')])->orderBy('name')->get()
            : CreditCategory::where('company_id', $company->id)->where('active', true)->with(['rules' => fn ($q) => $q->where('active', true)->orderBy('interest_rate')])->orderBy('name')->get();

        $clients = $user->is_super_admin
            ? Client::where('active', true)->orderBy('name')->get()
            : $company->clients()->where('active', true)->orderBy('name')->get();

        $requestClientId = (int) $request->input('client_id', old('client_id', 0));
        $preselectedClient = $requestClientId > 0 ? Client::find($requestClientId) : null;

        $canSetStartDate = $user->is_super_admin
            || $user->hasPermissionInCompany('loans.set_start_date', $company);

        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);
        $cashSessionError = $this->getCashSessionError((int) $company->id);

        // Derive branch from the open cash register
        $activeCashSession?->load('cashRegister.branch');
        $sessionBranch = $activeCashSession?->cashRegister?->branch;
        if ($sessionBranch) {
            $defaultBranchId = $sessionBranch->id;
        }

        $cashAvailable = $activeCashSession?->currentBalance();

        return view('loans.create', compact('company', 'clients', 'products', 'creditCategories', 'branches', 'defaultBranchId', 'canSetStartDate', 'activeCashSession', 'cashSessionError', 'preselectedClient', 'sessionBranch', 'cashAvailable'));
    }

    public function store()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa para crear préstamos.']);
        }

        // El préstamo crea su prenda como producto: cuentan ambos límites.
        if ($limitError = $this->planLimitError((int) $company->id, ['loans', 'products'])) {
            return back()->withInput()->withErrors(['error' => $limitError]);
        }

        $cashError = $this->getCashSessionError((int) $company->id);
        if ($cashError) {
            return back()->withInput()->withErrors(['error' => $cashError]);
        }
        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);

        try {
            $validated = request()->validate([
                'branch_id' => 'required|exists:branches,id',
                'product_id' => 'nullable|exists:products,id',
                'credit_category_id' => 'required|exists:credit_categories,id',
                'credit_category_rule_id' => 'nullable|exists:credit_category_rules,id',
                'product_name' => 'nullable|string|max:255',
                'product_brand' => 'nullable|string|max:255',
                'product_model_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'product_plate' => 'nullable|string|max:120',
                'product_chassis' => 'nullable|string|max:120',
                'product_serial_number' => 'nullable|string|max:120',
                'product_item_type' => 'nullable|string|max:120',
                'product_item_model' => 'nullable|string|max:120',
                'product_karat' => 'nullable|string|max:20',
                'product_material_type' => 'nullable|string|max:60',
                'product_price' => 'nullable|numeric|min:0',
                'product_details' => 'nullable|string|max:1000',
                'client_id' => 'nullable|exists:clients,id',
                'client_name' => 'nullable|required_without:client_id|string|max:255',
                'client_id_number' => 'nullable|string|max:30',
                'client_phone' => 'nullable|string|max:20',
                'client_email' => 'nullable|email|max:255',
                'client_address' => 'nullable|string|max:255',
                'client_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
                'amount' => 'required|numeric|min:0.01',
                'interest_rate' => 'nullable|integer|min:1|max:100',
                'term_months' => 'nullable|integer|min:1',
                'interest_period' => 'nullable|in:monthly,quarterly,semiannual,annual',
                'start_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'images' => 'nullable|array|max:20',
                'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            // No permitir desembolsar más de lo que hay en la caja abierta
            $cashAvailable = $activeCashSession->currentBalance();
            if ((float) $validated['amount'] > $cashAvailable) {
                return back()->withInput()->withErrors([
                    'error' => 'El monto del préstamo (' . \App\Support\Currency::format((float) $validated['amount'])
                        . ') supera el saldo disponible en caja (' . \App\Support\Currency::format($cashAvailable)
                        . '). No puedes desembolsar más de lo que hay en la caja.',
                ]);
            }

            DB::beginTransaction();

            if (!empty($validated['branch_id'])) {
                $branch = Branch::findOrFail($validated['branch_id']);
                if ($branch->company_id !== $company->id && !$user->is_super_admin) {
                    abort(403);
                }
            }

            $category = CreditCategory::findOrFail($validated['credit_category_id']);

            if ($category && $category->company_id !== $company->id && !$user->is_super_admin) {
                abort(403);
            }

            $rule = CreditCategoryRule::where('credit_category_id', $category->id)
                ->where('active', true)
                ->orderBy('interest_rate')
                ->orderBy('id')
                ->first();

            if (!$rule) {
                return back()->withInput()->withErrors(['credit_category_id' => 'La categoría seleccionada no tiene una regla de crédito activa.']);
            }

            $categoryId = (int) $category->id;

            if ($categoryId === CreditCategory::CATEGORIA_VEHICULO) {
                $requiredVehicleFields = [
                    'product_name' => 'nombre',
                    'product_brand' => 'marca',
                    'product_model_year' => 'modelo (año)',
                    'product_plate' => 'placa',
                    'product_chassis' => 'chasis',
                ];
                foreach ($requiredVehicleFields as $field => $label) {
                    if (empty($validated[$field])) {
                        return back()->withInput()->withErrors([$field => 'El campo ' . $label . ' es obligatorio para vehículos.']);
                    }
                }
            }

            if ($categoryId === CreditCategory::CATEGORIA_JOYAS) {
                if (empty($validated['product_item_type'])) {
                    return back()->withInput()->withErrors(['product_item_type' => 'El tipo de joya es obligatorio.']);
                }
                if (empty($validated['product_karat'])) {
                    return back()->withInput()->withErrors(['product_karat' => 'Selecciona el kilataje de la joya.']);
                }
            }

            // Cualquier categoría distinta de 1/2/3 usa la regla genérica de Artículos.
            if (!in_array($categoryId, [
                CreditCategory::CATEGORIA_VEHICULO,
                CreditCategory::CATEGORIA_JOYAS,
                CreditCategory::CATEGORIA_GARRAFAS,
            ], true)) {
                $requiredArticleFields = [
                    'product_name' => 'nombre',
                    'product_brand' => 'marca',
                    'product_serial_number' => 'serial',
                ];
                foreach ($requiredArticleFields as $field => $label) {
                    if (empty($validated[$field])) {
                        return back()->withInput()->withErrors([$field => 'El campo ' . $label . ' es obligatorio para artículos.']);
                    }
                }
            }

            $productName = trim((string) ($validated['product_name'] ?? ''));
            if ($categoryId === CreditCategory::CATEGORIA_JOYAS) {
                $productName = trim((string) ($validated['product_item_type'] ?? ''));
            }
            if ($categoryId === CreditCategory::CATEGORIA_GARRAFAS) {
                $productName = (string) $category->name;
            }

            // Cada préstamo crea su propio producto (cantidad 1, con su propio valor): los artículos usados
            // nunca son iguales aunque coincidan nombre y marca.
            $product = Product::create([
                'company_id' => $company->id,
                'credit_category_id' => $category->id,
                'name' => $productName,
                'sku' => Product::makeSku($productName),
                'brand' => $validated['product_brand'] ?? null,
                'model_year' => $validated['product_model_year'] ?? null,
                'plate' => $validated['product_plate'] ?? null,
                'chassis' => $validated['product_chassis'] ?? null,
                'serial_number' => $validated['product_serial_number'] ?? null,
                'karat' => $validated['product_karat'] ?? null,
                'item_type' => $validated['product_item_type'] ?? null,
                'item_model' => $validated['product_item_model'] ?? null,
                'material_type' => $validated['product_material_type'] ?? null,
                'description' => $validated['product_details'] ?? null,
                'unit' => 'unidad',
                'cost' => $validated['amount'],
                'price' => $validated['product_price'] ?? 0,
                'active' => true,
            ]);

            if (!empty($validated['client_id'])) {
                $client = Client::findOrFail($validated['client_id']);
                if ($client->company_id !== $company->id && !$user->is_super_admin) {
                    abort(403);
                }
            } else {
                $client = Client::create([
                    'company_id' => $company->id,
                    'name' => $validated['client_name'],
                    'id_number' => $validated['client_id_number'] ?? null,
                    'phone' => $validated['client_phone'] ?? null,
                    'email' => $validated['client_email'] ?? null,
                    'address' => $validated['client_address'] ?? null,
                    'photo' => null,
                    'active' => true,
                ]);

                if (request()->hasFile('client_photo')) {
                    $photoPath = request()->file('client_photo')->store('client-photos/' . $client->id, 'public');
                    $client->update(['photo' => $photoPath]);
                }
            }

            $canSetStartDate = $user->is_super_admin
                || $user->hasPermissionInCompany('loans.set_start_date', $company);

            $startDate = ($canSetStartDate && !empty($validated['start_date']))
                ? $validated['start_date']
                : now()->toDateString();

            $termMonths = (int) ($rule?->term_months_limit ?? $validated['term_months']);
            $endDate = \Carbon\Carbon::parse($startDate)->addMonths($termMonths)->toDateString();

            $loan = $company->loans()->create([
                'branch_id' => $validated['branch_id'] ?? null,
                'product_id' => $product?->id,
                'credit_category_id' => $category?->id,
                'credit_category_rule_id' => $rule?->id,
                'client_id' => $client->id,
                'amount' => $validated['amount'],
                'current_capital' => $validated['amount'],
                // Solo Vehículo (1) y Joyas (2) permiten interés manual; el resto usa la regla.
                'interest_rate' => in_array($categoryId, [CreditCategory::CATEGORIA_VEHICULO, CreditCategory::CATEGORIA_JOYAS], true)
                    ? ($validated['interest_rate'] ?? $rule->interest_rate)
                    : $rule->interest_rate,
                'interest_period' => $rule->interest_period,
                'term_months' => $termMonths,
                'notes' => null,
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => Loan::STATUS_ACTIVE,
                'cash_register_id' => $activeCashSession->cash_register_id,
                'cash_register_session_id' => $activeCashSession->id,
            ]);

            // El producto lleva el código del préstamo (PR-00123) y su sucursal.
            $product->assignOrigin($loan->code, $loan->branch_id);

            // Registrar egreso (desembolso) en movimientos de caja
            CashMovement::create([
                'company_id' => $company->id,
                'cash_register_id' => $activeCashSession->cash_register_id,
                'cash_register_session_id' => $activeCashSession->id,
                'user_id' => $user->id,
                'type' => 'expense',
                'category' => 'loan_disbursement',
                'amount' => $validated['amount'],
                'reference_type' => Loan::class,
                'reference_id' => $loan->id,
                'description' => 'Desembolso préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
                'movement_date' => now(),
            ]);

            $loan->update([
                'monthly_payment' => $loan->calculateMonthlyPayment(),
                'total_to_pay' => $loan->calculateTotalToPay(),
            ]);

            // Auto-create collateral if product exists
            if ($product) {
                $branch = Branch::find($validated['branch_id']);
                $warehouseId = $branch?->warehouse_id;

                if ($warehouseId) {
                    $movement = InventoryMovement::create([
                        'company_id' => $company->id,
                        'warehouse_id' => $warehouseId,
                        'branch_id' => $loan->branch_id,
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => 'in',
                        'quantity' => 1,
                        'unit_cost' => $product->cost ?? 0,
                        'reference' => 'LOAN-RET-' . $loan->id,
                        'notes' => 'Ingreso a retención por préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
                        'movement_date' => now(),
                    ]);

                    LoanCollateral::updateOrCreate(
                        ['loan_id' => $loan->id],
                        [
                            'company_id' => $company->id,
                            'branch_id' => $loan->branch_id,
                            'warehouse_id' => $warehouseId,
                            'product_id' => $product->id,
                            'inventory_movement_id' => $movement->id,
                            'status' => 'retained',
                            'retained_at' => now(),
                            'expected_release_date' => $endDate,
                            'notes' => 'Prenda retenida al crear préstamo',
                        ]
                    );
                }
            }

            // Store attached images
            if (request()->hasFile('images')) {
                foreach (request()->file('images') as $image) {
                    if (!$image) continue;
                    $path = $image->store('loan-images/loan-' . $loan->id, 'public');
                    LoanImage::create([
                        'loan_id' => $loan->id,
                        'path' => $path,
                        'original_name' => $image->getClientOriginalName(),
                        'mime_type' => $image->getClientMimeType(),
                        'size_bytes' => $image->getSize(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('loans.show', $loan)->with('success', 'Préstamo registrado exitosamente');
        } catch (ValidationException $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            // Let Laravel show field-level errors in the form.
            throw $exception;
        } catch (\Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error al registrar préstamo', [
                'company_id' => $company?->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible registrar el préstamo.']);
        }
    }

    public function show(Loan $loan)
    {
        $this->authorizeCompany($loan);

        // Los préstamos en cuotas tienen su propia pantalla (plan de pagos).
        if ($loan->isInstallment() && !$loan->trashed()) {
            return redirect()->route('loans.installments.show', $loan);
        }

        // Un préstamo eliminado se muestra en modo SOLO LECTURA: no se ejecuta
        // ninguna escritura (ni generación de periodos ni cambios de estado).
        if (!$loan->trashed()) {
            $this->updateOverdueCollateralStatuses((int) $loan->company_id);

            // Inicializar current_capital si es null
            if ($loan->current_capital === null) {
                $loan->update(['current_capital' => $loan->amount]);
            }

            // Generar periodos de interés pendientes
            if (in_array($loan->status, Loan::OPEN_STATUSES, true)) {
                $loan->generatePendingInterestPeriods();
                $loan->checkOverdueStatus();
            }
        }

        $loan->load([
            'client', 'product', 'creditCategory', 'creditCategoryRule', 'createdBy', 'approvedBy',
            'deletedBy.personal',
            'payments.registeredBy.personal', 'collateral.warehouse', 'contract.attachments', 'images',
            'interestPayments' => fn ($q) => $q->withTrashed()->with('deletedBy.personal', 'restoredBy.personal'),
            'amortizations', 'cashRegister',
        ]);

        $contractTemplates = \App\Models\DocumentTemplate::where('company_id', $loan->company_id)
            ->where('type', 'contrato')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $interestPrintTemplates = \App\Models\DocumentTemplate::where('company_id', $loan->company_id)
            ->whereIn('type', ['recibo', 'boleta', 'otro'])
            ->where('active', true)
            ->orderByRaw("CASE WHEN paper_size = 'pequeno' THEN 0 WHEN paper_size = 'mediano' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $loanActionPrintTemplates = \App\Models\DocumentTemplate::where('company_id', $loan->company_id)
            ->whereIn('type', ['recibo', 'boleta', 'otro', 'amortizacion', 'liquidacion'])
            ->where('active', true)
            ->orderByRaw("CASE WHEN paper_size = 'pequeno' THEN 0 WHEN paper_size = 'mediano' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $liquidationPrintTemplates = \App\Models\DocumentTemplate::where('company_id', $loan->company_id)
            ->where('type', 'liquidacion')
            ->where('active', true)
            ->orderByRaw("CASE WHEN paper_size = 'pequeno' THEN 0 WHEN paper_size = 'mediano' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $activeCashSession = $this->resolveOpenCashSession((int) $loan->company_id);
        $cashSessionError = $this->getCashSessionError((int) $loan->company_id);

        $canEdit = $loan->payments->isEmpty()
            && $loan->interestPayments->where('status', 'paid')->isEmpty()
            && $loan->amortizations->isEmpty();

        return view('loans.show', compact('loan', 'contractTemplates', 'interestPrintTemplates', 'loanActionPrintTemplates', 'liquidationPrintTemplates', 'activeCashSession', 'cashSessionError', 'canEdit'));
    }

    public function edit(Loan $loan)
    {
        $this->authorizeCompany($loan);

        $loan->loadMissing('product', 'creditCategory', 'client', 'payments', 'interestPayments', 'amortizations');

        $hasPayments = $loan->payments->isNotEmpty()
            || $loan->interestPayments->where('status', 'paid')->isNotEmpty()
            || $loan->amortizations->isNotEmpty();

        if ($hasPayments) {
            return redirect()->route('loans.show', $loan)
                ->withErrors(['error' => 'No se puede editar un préstamo con pagos registrados.']);
        }

        $user = auth()->user();
        // Empresa DEL PRÉSTAMO (el super admin no tiene empresa seleccionada).
        $companyId = (int) $loan->company_id;

        $canSetStartDate = $user->is_super_admin
            || $user->hasPermissionInCompany('loans.set_start_date', $user->getCurrentCompany());

        $activeCashSession = $this->resolveOpenCashSession($companyId);
        $cashSessionError  = $this->getCashSessionError($companyId);
        $activeCashSession?->load('cashRegister.branch');

        // La sucursal del préstamo no cambia al editar (la prenda está en su almacén).
        $loan->loadMissing('branch');

        return view('loans.edit', compact('loan', 'canSetStartDate', 'activeCashSession', 'cashSessionError'));
    }

    public function update(Request $request, Loan $loan)
    {
        $this->authorizeCompany($loan);

        $loan->loadMissing('payments', 'interestPayments', 'amortizations', 'product');

        $hasPayments = $loan->payments->isNotEmpty()
            || $loan->interestPayments->where('status', 'paid')->isNotEmpty()
            || $loan->amortizations->isNotEmpty();

        if ($hasPayments) {
            return redirect()->route('loans.show', $loan)
                ->withErrors(['error' => 'No se puede editar un préstamo con pagos registrados.']);
        }

        $user            = auth()->user();
        $canSetStartDate = $user->is_super_admin
            || $user->hasPermissionInCompany('loans.set_start_date', $user->getCurrentCompany());

        $validated = $request->validate([
            'amount'                => 'required|numeric|min:0.01',
            'interest_rate'         => 'nullable|integer|min:1|max:100',
            'term_months'           => 'nullable|integer|min:1',
            'start_date'            => 'nullable|date',
            'notes'                 => 'nullable|string|max:2000',
            'product_name'          => 'nullable|string|max:255',
            'product_brand'         => 'nullable|string|max:255',
            'product_model_year'    => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'product_plate'         => 'nullable|string|max:120',
            'product_chassis'       => 'nullable|string|max:120',
            'product_serial_number' => 'nullable|string|max:120',
            'product_karat'         => 'nullable|string|max:20',
            'product_item_type'     => 'nullable|string|max:120',
            'product_item_model'    => 'nullable|string|max:120',
            'product_material_type' => 'nullable|string|max:60',
            'product_details'       => 'nullable|string|max:1000',
            'product_price'         => 'nullable|numeric|min:0',
            'images'                => 'nullable|array|max:20',
            'images.*'              => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($loan->product) {
            $productUpdate = [
                'cost'  => $validated['amount'],
                'price' => $validated['product_price'] ?? $loan->product->price,
            ];
            foreach ([
                'product_name'          => 'name',
                'product_brand'         => 'brand',
                'product_model_year'    => 'model_year',
                'product_plate'         => 'plate',
                'product_chassis'       => 'chassis',
                'product_serial_number' => 'serial_number',
                'product_karat'         => 'karat',
                'product_item_type'     => 'item_type',
                'product_item_model'    => 'item_model',
                'product_material_type' => 'material_type',
                'product_details'       => 'description',
            ] as $requestKey => $modelKey) {
                if (array_key_exists($requestKey, $validated)) {
                    $productUpdate[$modelKey] = $validated[$requestKey];
                }
            }
            $loan->product->update($productUpdate);
        }

        $startDate = ($canSetStartDate && !empty($validated['start_date']))
            ? $validated['start_date']
            : $loan->start_date->toDateString();

        $termMonths = (int) ($validated['term_months'] ?? $loan->term_months);
        $endDate    = \Carbon\Carbon::parse($startDate)->addMonths($termMonths)->toDateString();

        $oldAmount = (float) $loan->amount;
        $newAmount = (float) $validated['amount'];
        $newRate   = $validated['interest_rate'] ?? $loan->interest_rate;

        // ¿Cambiaron las condiciones que definen los intereses? Si solo se agregan
        // fotos, notas o datos del producto, los intereses no se tocan.
        $termsChanged = abs($oldAmount - $newAmount) > 0.001
            || abs((float) $loan->interest_rate - (float) $newRate) > 0.001
            || (int) $loan->term_months !== $termMonths
            || $loan->start_date->toDateString() !== \Carbon\Carbon::parse($startDate)->toDateString();

        // La sucursal del préstamo se conserva (no se toma del formulario ni de la caja).
        $loan->update([
            'amount'          => $newAmount,
            'current_capital' => $newAmount,
            'interest_rate'   => $newRate,
            'term_months'     => $termMonths,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'notes'           => $validated['notes'] ?? null,
        ]);

        $loan->update([
            'monthly_payment' => $loan->calculateMonthlyPayment(),
            'total_to_pay'    => $loan->calculateTotalToPay(),
        ]);

        if (abs($oldAmount - $newAmount) > 0.001) {
            CashMovement::where('reference_type', Loan::class)
                ->where('reference_id', $loan->id)
                ->where('category', 'loan_disbursement')
                ->update(['amount' => $newAmount]);
        }

        // Si cambiaron las condiciones, recalcular los intereses pendientes en su lugar
        // (mismos registros, sin borrarlos). Si no, no se tocan.
        if ($termsChanged) {
            $loan->refresh()->recalculatePendingInterestPeriods();
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if (!$image) continue;
                $path = $image->store('loan-images/loan-' . $loan->id, 'public');
                LoanImage::create([
                    'loan_id'       => $loan->id,
                    'path'          => $path,
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type'     => $image->getClientMimeType(),
                    'size_bytes'    => $image->getSize(),
                ]);
            }
        }

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Préstamo actualizado correctamente.');
    }

    public function approve(Loan $loan)
    {
        $this->authorizeCompany($loan);

        if (!auth()->user()->hasPermissionInCompany('loans.approve', auth()->user()->getCurrentCompany())) {
            return back()->withErrors(['error' => 'No tienes permisos para aprobar préstamos']);
        }

        if (!$loan->product_id) {
            return back()->withErrors(['error' => 'El préstamo debe tener un producto para aprobar y retener en almacén.']);
        }

        $loan->loadMissing('branch', 'product');
        $warehouseId = $loan->branch?->warehouse_id;

        if (!$warehouseId) {
            return back()->withErrors(['error' => 'La sucursal no tiene almacén principal configurado.']);
        }

        $endDate = now()->copy()->addMonths((int) $loan->term_months)->toDateString();

        $loan->update([
            'status' => Loan::STATUS_ACTIVE,
            'approved_by' => auth()->user()->id,
            'approved_at' => now(),
            'start_date' => now()->toDateString(),
            'end_date' => $endDate,
        ]);

        $movement = InventoryMovement::create([
            'company_id' => $loan->company_id,
            'warehouse_id' => $warehouseId,
            'branch_id' => $loan->branch_id,
            'product_id' => $loan->product_id,
            'user_id' => auth()->id(),
            'type' => 'in',
            'quantity' => 1,
            'unit_cost' => $loan->product?->cost ?? 0,
            'reference' => 'LOAN-RET-' . $loan->id,
            'notes' => 'Ingreso a retención por préstamo #' . $loan->id,
            'movement_date' => now(),
        ]);

        LoanCollateral::updateOrCreate(
            ['loan_id' => $loan->id],
            [
                'company_id' => $loan->company_id,
                'branch_id' => $loan->branch_id,
                'warehouse_id' => $warehouseId,
                'product_id' => $loan->product_id,
                'inventory_movement_id' => $movement->id,
                'status' => 'retained',
                'retained_at' => now(),
                'expected_release_date' => $endDate,
                'notes' => 'Prenda retenida por aprobación de préstamo',
            ]
        );

        return back()->with('success', 'Préstamo aprobado y activado exitosamente');
    }

    public function recordPayment(Loan $loan)
    {
        $this->authorizeCompany($loan);

        $validated = request()->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $cashError = $this->getCashSessionError((int) $loan->company_id);
        if ($cashError) {
            return back()->withInput()->withErrors(['error' => $cashError]);
        }
        $activeCashSession = $this->resolveOpenCashSession((int) $loan->company_id);

        $payment = $loan->payments()->create([
            ...$validated,
            'registered_by' => auth()->user()->id,
            'cash_register_id' => $activeCashSession->cash_register_id,
            'cash_register_session_id' => $activeCashSession->id,
        ]);

        // Registrar ingreso en movimientos de caja
        CashMovement::create([
            'company_id' => $loan->company_id,
            'cash_register_id' => $activeCashSession->cash_register_id,
            'cash_register_session_id' => $activeCashSession->id,
            'user_id' => auth()->id(),
            'type' => 'income',
            'category' => 'loan_payment',
            'amount' => $validated['amount'],
            'reference_type' => LoanPayment::class,
            'reference_id' => $payment->id,
            'description' => 'Pago préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
            'movement_date' => now(),
        ]);

        $loan->increment('total_paid', $validated['amount']);

        if ($loan->total_paid >= $loan->total_to_pay) {
            $loan->update(['status' => Loan::STATUS_FINISHED]);
            $this->releaseCollateralFromLoan($loan);
        }

        return back()->with('success', 'Pago registrado exitosamente');
    }

    public function destroy(Request $request, Loan $loan)
    {
        $this->authorizeCompany($loan);

        if (!auth()->user()->hasPermissionInCompany('loans.delete', auth()->user()->getCurrentCompany())) {
            abort(403);
        }

        $hasPaidInterest = $loan->interestPayments()
            ->where(function ($q) {
                $q->where('status', 'paid')->orWhere('paid_amount', '>', 0);
            })->exists();

        if ($hasPaidInterest) {
            return back()->with('error', 'No se puede eliminar: el préstamo tiene pagos de interés registrados.');
        }

        // Si el préstamo ya generó cobros en caja (pago, interés, amortización,
        // liquidación) no se puede eliminar: eliminar es para corregir errores
        // de registro, no para deshacer un préstamo que ya movió dinero.
        $hasIncome = CashMovement::where('reference_type', Loan::class)
            ->where('reference_id', $loan->id)
            ->where('type', 'income')
            ->exists();

        if ($hasIncome) {
            return back()->with('error', 'No se puede eliminar: el préstamo ya tiene cobros registrados en caja.');
        }

        // El dinero desembolsado debe volver a la caja aperturada del usuario logueado.
        $cashError = $this->getCashSessionError((int) $loan->company_id);
        if ($cashError) {
            return back()->with('error', 'Debes tener una caja abierta para eliminar el préstamo: el dinero desembolsado debe volver a tu caja. (' . $cashError . ')');
        }

        $session = $this->resolveOpenCashSession((int) $loan->company_id);
        if (!$session) {
            return back()->with('error', 'No se encontró una caja abierta para registrar la devolución del dinero.');
        }

        DB::beginTransaction();

        try {
            // Monto realmente desembolsado (update() reescribe el monto del movimiento
            // cuando se edita el préstamo, así que se lee del movimiento, no del préstamo).
            $disbursed = (float) CashMovement::where('reference_type', Loan::class)
                ->where('reference_id', $loan->id)
                ->where('type', 'expense')
                ->sum('amount');

            if ($disbursed > 0) {
                CashMovement::create([
                    'company_id' => $loan->company_id,
                    'cash_register_id' => $session->cash_register_id,
                    'cash_register_session_id' => $session->id,
                    'user_id' => auth()->id(),
                    'type' => 'income',
                    'category' => 'loan_deletion',
                    'amount' => $disbursed,
                    'reference_type' => Loan::class,
                    'reference_id' => $loan->id,
                    'description' => 'Reversión por eliminación préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT),
                    'movement_date' => now(),
                ]);
            }

            // La prenda sale del inventario y el colateral queda liberado.
            $this->releaseCollateralFromLoan($loan, 'LOAN-DEL-', 'Salida por eliminación del préstamo');

            $notes = trim((string) $request->input('notes', ''));
            $updateData = ['deleted_by' => auth()->id()]; // quién elimina
            if ($notes) {
                $existing = $loan->notes ? trim($loan->notes) . "\n" : '';
                $updateData['notes'] = $existing . '[Eliminado] ' . $notes;
            }
            $loan->update($updateData);

            $loan->interestPayments()->update(['deleted_by' => auth()->id(), 'deletion_reason' => 'loan_deleted']);
            $loan->interestPayments()->delete();
            $loan->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al eliminar préstamo: ' . $e->getMessage(), ['loan_id' => $loan->id]);

            return back()->with('error', 'No se pudo eliminar el préstamo: ' . $e->getMessage());
        }

        $message = $disbursed > 0
            ? 'Préstamo eliminado. Se devolvieron ' . \App\Support\Currency::format($disbursed) . ' a tu caja.'
            : 'Préstamo eliminado correctamente.';

        return redirect()->route('loans.index')->with('success', $message);
    }

    protected function authorizeCompany(Loan $loan)
    {
        if (!auth()->user()->is_super_admin && $loan->company_id !== auth()->user()->getCurrentCompany()->id) {
            abort(403);
        }
    }

    protected function resolveOpenCashSession(int $companyId): ?\App\Models\CashRegisterSession
    {
        $personal = Personal::where('user_id', auth()->id())->first();
        if (!$personal) return null;

        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) return null;

        return \App\Models\CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    protected function getCashSessionError(int $companyId): ?string
    {
        $personal = Personal::where('user_id', auth()->id())->first();
        if (!$personal) {
            return 'Tu usuario no tiene un personal asociado al sistema. Contacta al administrador.';
        }
        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');
        if ($registerIds->isEmpty()) {
            return 'No tienes una caja asignada. Contacta al administrador para que te asigne una.';
        }
        $hasOpen = \App\Models\CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->exists();
        if (!$hasOpen) {
            return 'No hay ninguna caja abierta. Debes aperturar una caja antes de registrar operaciones.';
        }
        return null;
    }

    protected function updateOverdueCollateralStatuses(int $companyId): void
    {
        // Aplica la regla de morosidad por CONTEO de periodos vencidos impagos
        // a cada préstamo abierto de la empresa (genera periodos pendientes y
        // evalúa multa/colateral de forma consistente con la vista de detalle).
        Loan::where('company_id', $companyId)
            ->whereIn('status', Loan::OPEN_STATUSES)
            ->with(['creditCategory', 'collateral', 'interestPayments'])
            ->get()
            ->each(function (Loan $loan) {
                $loan->generatePendingInterestPeriods();
                $loan->checkOverdueStatus();
            });
    }

    protected function releaseCollateralFromLoan(
        Loan $loan,
        string $refPrefix = 'LOAN-REL-',
        string $note = 'Salida por liberación de prenda (préstamo pagado)'
    ): void {
        $collateral = LoanCollateral::where('loan_id', $loan->id)->whereIn('status', ['retained', 'stored', 'sellable'])->first();

        if (!$collateral) {
            return;
        }

        InventoryMovement::create([
            'company_id' => $loan->company_id,
            'warehouse_id' => $collateral->warehouse_id,
            'branch_id' => $collateral->branch_id,
            'product_id' => $collateral->product_id,
            'user_id' => auth()->id(),
            'type' => 'out',
            'quantity' => 1,
            'unit_cost' => $loan->product?->cost ?? 0,
            'reference' => $refPrefix . $loan->id,
            'notes' => $note,
            'movement_date' => now(),
        ]);

        $collateral->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }
}
