<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\LoanCollateral;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleDeleteGrant;
use App\Models\SaleImage;
use App\Models\SaleInstallment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Ventas: productos disponibles, registro (contado / crédito con cuotas), pago de cuota,
 * liquidación y devolución. Una sola fuente de verdad para la web (Sales\SaleController,
 * Sales\SaleInstallmentController) y la API móvil (Api\SaleController).
 *
 * Cada operación corre en una transacción y bloquea el producto o la venta
 * (lockForUpdate) para que dos envíos simultáneos no vendan la misma unidad ni
 * cobren dos veces la misma cuota. Los errores de negocio se lanzan como
 * SaleException con el mensaje listo para el usuario.
 */
class SaleService
{
    /** Mensajes de validación en español para el alta y la devolución (web y API). */
    public static function validationMessages(): array
    {
        return [
            'branch_id.required' => 'Selecciona la sucursal.',
            'branch_id.exists' => 'La sucursal no es válida.',
            'product_id.required' => 'Selecciona el producto a vender.',
            'product_id.exists' => 'El producto no es válido.',
            'product_id.integer' => 'El producto no es válido.',
            'payment_type.required' => 'Selecciona el tipo de pago.',
            'payment_type.in' => 'El tipo de pago no es válido.',
            'client_id.exists' => 'El cliente no es válido.',
            'client_id.integer' => 'El cliente no es válido.',
            'client_name.required_without' => 'Selecciona un cliente o escribe el nombre del cliente nuevo.',
            'client_name.max' => 'El nombre del cliente es demasiado largo.',
            'client_email.email' => 'El correo del cliente no es válido.',
            'amount.required' => 'Ingresa el precio de venta.',
            'amount.numeric' => 'El precio de venta debe ser un número.',
            'amount.min' => 'El precio de venta debe ser mayor a 0.',
            'credit_additional_percentage.numeric' => 'El recargo de crédito debe ser un número.',
            'credit_additional_percentage.max' => 'El recargo de crédito es demasiado alto.',
            'down_payment.numeric' => 'La cuota inicial debe ser un número.',
            'down_payment.min' => 'La cuota inicial no puede ser negativa.',
            'installments.array' => 'Las cuotas no son válidas.',
            'installments.min' => 'Debes registrar al menos una cuota.',
            'installments.*.due_date.required_if' => 'Cada cuota debe tener fecha de vencimiento.',
            'installments.*.due_date.date' => 'La fecha de una cuota no es válida.',
            'installments.*.percentage.required_if' => 'Cada cuota debe tener porcentaje.',
            'installments.*.percentage.numeric' => 'El porcentaje de una cuota no es válido.',
            'installments.*.percentage.min' => 'El porcentaje de cada cuota debe ser mayor a 0.',
            'installments.*.percentage.max' => 'El porcentaje de una cuota no puede superar 100%.',
            'installments.*.amount.required_if' => 'Cada cuota debe tener monto.',
            'installments.*.amount.numeric' => 'El monto de una cuota no es válido.',
            'installments.*.amount.min' => 'El monto de cada cuota debe ser mayor a 0.',
            'images.max' => 'Puedes adjuntar como máximo 20 imágenes.',
            'images.*.image' => 'Cada archivo debe ser una imagen.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WEBP.',
            'images.*.max' => 'Cada imagen puede pesar como máximo 5 MB.',
            'return_reason.required' => 'Indica el motivo de la devolución.',
            'return_reason.max' => 'El motivo es demasiado largo.',
            'return_notes.max' => 'Las notas no pueden superar los 1000 caracteres.',
            'payment_notes.max' => 'Las notas no pueden superar los 500 caracteres.',
            'notes.string' => 'Las notas deben ser texto.',
        ];
    }

    /* ── Productos disponibles ─────────────────────────────────── */

    /**
     * Stock libre de un producto en un almacén: entradas − salidas − prendas retenidas/guardadas.
     */
    public function freeStock(int $companyId, int $productId, int $warehouseId): float
    {
        $stock = (float) InventoryMovement::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->selectRaw('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) as stock')
            ->value('stock');

        $held = (int) LoanCollateral::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', ['retained', 'stored'])
            ->whereNull('deleted_at')
            ->count();

        return max(0, $stock - $held);
    }

    /**
     * Productos con stock libre (o prenda vendible) en los almacenes de las sucursales dadas.
     *
     * @param iterable<Branch> $branches
     */
    public function availableProducts(int $companyId, iterable $branches): array
    {
        $result = [];

        foreach ($branches as $branch) {
            if (!$branch->warehouse_id) continue;

            // Productos con stock positivo en este almacén
            $stockItems = InventoryMovement::where('warehouse_id', $branch->warehouse_id)
                ->whereNull('deleted_at')
                ->selectRaw('product_id, SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) as stock')
                ->groupBy('product_id')
                ->havingRaw('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) > 0')
                ->pluck('stock', 'product_id');

            if ($stockItems->isEmpty()) continue;

            // Unidades retenidas por prendas (retained + stored = comprometidas)
            $heldCounts = LoanCollateral::where('company_id', $companyId)
                ->whereIn('status', ['retained', 'stored'])
                ->where('warehouse_id', $branch->warehouse_id)
                ->whereNull('deleted_at')
                ->whereIn('product_id', $stockItems->keys())
                ->selectRaw('product_id, COUNT(*) as held')
                ->groupBy('product_id')
                ->pluck('held', 'product_id');

            // Se venden los que tienen stock libre o una prenda vendible (préstamo incumplido)
            $sellableCollaterals = LoanCollateral::where('company_id', $companyId)
                ->where('status', 'sellable')
                ->where('warehouse_id', $branch->warehouse_id)
                ->whereNull('deleted_at')
                ->whereIn('product_id', $stockItems->keys())
                ->pluck('id', 'product_id');

            $availableIds = $stockItems->keys()->filter(function ($productId) use ($stockItems, $heldCounts, $sellableCollaterals) {
                $freeStock = max(0, (float) $stockItems[$productId] - (int) ($heldCounts[$productId] ?? 0));
                return $freeStock > 0 || $sellableCollaterals->has($productId);
            });

            if ($availableIds->isEmpty()) continue;

            $products = Product::whereIn('id', $availableIds)
                ->where('active', true)
                ->with(['creditCategory', 'inventoryMovements.images'])
                ->get();

            $purchaseOrigins = Purchase::with('images')
                ->where('company_id', $companyId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereIn('product_id', $availableIds)
                ->latest('id')
                ->get()
                ->unique('product_id')
                ->keyBy('product_id');

            foreach ($products as $product) {
                $origin = 'other';
                if ($sellableCollaterals->has($product->id)) {
                    $origin = 'collateral';
                } elseif ($purchaseOrigins->has($product->id)) {
                    $origin = 'purchase';
                }

                $availableStock = max(0, (float) ($stockItems[$product->id] ?? 0) - (int) ($heldCounts[$product->id] ?? 0));

                // Imágenes: primero las de la compra, luego las de los movimientos del almacén
                $imageUrls = collect();
                if ($origin === 'purchase') {
                    foreach ($purchaseOrigins->get($product->id)?->images ?? [] as $img) {
                        $imageUrls->push(asset('storage/' . $img->path));
                    }
                }
                $movementImages = $product->inventoryMovements
                    ->where('warehouse_id', $branch->warehouse_id)
                    ->sortByDesc('movement_date')
                    ->flatMap->images;
                foreach ($movementImages as $img) {
                    $url = asset('storage/' . $img->path);
                    if (!$imageUrls->contains($url)) {
                        $imageUrls->push($url);
                    }
                }
                $imageUrls = $imageUrls->values()->all();

                $result[] = [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'model_year' => $product->model_year,
                    'plate' => $product->plate,
                    'chassis' => $product->chassis,
                    'serial_number' => $product->serial_number,
                    'item_type' => $product->item_type,
                    'karat' => $product->karat,
                    'description' => $product->description,
                    'cost' => (float) $product->cost,
                    'price' => (float) $product->price,
                    'category_id' => $product->credit_category_id,
                    'category_name' => $product->creditCategory?->name ?? 'Sin categoría',
                    'stock' => $availableStock,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'warehouse_id' => $branch->warehouse_id,
                    'origin' => $origin,
                    'image_url' => $imageUrls[0] ?? null,
                    'image_urls' => $imageUrls,
                    'origin_label' => match ($origin) {
                        'collateral' => 'Préstamo incumplido',
                        'purchase' => 'Compra',
                        default => 'Inventario',
                    },
                ];
            }
        }

        return $result;
    }

    /* ── Registrar venta ───────────────────────────────────────── */

    /**
     * Registra una venta en la sucursal de la caja abierta.
     *
     * @param array $data product_id, payment_type (CONTADO|CREDITO), amount, client_id | client_name (+ client_id_number,
     *                    client_phone, client_email, client_address), credit_additional_percentage, down_payment,
     *                    notes, installments [{due_date, percentage, amount}], branch_id (si la caja no tiene sucursal)
     * @param UploadedFile[] $images
     */
    public function create(Company $company, int $userId, CashRegisterSession $session, array $data, array $images = []): Sale
    {
        // La sucursal la manda la CAJA abierta: solo se vende lo del almacén de la caja del usuario.
        $session->loadMissing('cashRegister.branch');
        $branch = $session->cashRegister?->branch
            ?? (isset($data['branch_id']) ? Branch::find($data['branch_id']) : null);
        if (!$branch || (int) $branch->company_id !== (int) $company->id) {
            throw new SaleException('La caja abierta no tiene una sucursal válida.');
        }
        $warehouseId = $branch->warehouse_id;
        if (!$warehouseId) {
            throw new SaleException('La sucursal no tiene un almacén asignado.');
        }

        $paymentType = strtoupper((string) $data['payment_type']);
        if (!in_array($paymentType, [Sale::PAYMENT_CASH, Sale::PAYMENT_CREDIT], true)) {
            throw new SaleException('El tipo de pago no es válido.', 'payment_type');
        }

        $amount = round((float) $data['amount'], 2);
        $downPayment = $paymentType === Sale::PAYMENT_CREDIT ? round((float) ($data['down_payment'] ?? 0), 2) : 0.0;
        $creditAdditionalPercentage = 0.0;
        $creditAdditionalAmount = 0.0;
        $creditTotalAmount = $amount;

        if ($paymentType === Sale::PAYMENT_CREDIT) {
            $creditAdditionalPercentage = (float) ($data['credit_additional_percentage'] ?? 0);
            if ($creditAdditionalPercentage <= 0) {
                throw new SaleException('El porcentaje adicional de crédito es obligatorio y debe ser mayor a 0 para ventas al crédito.', 'credit_additional_percentage');
            }
            $creditAdditionalAmount = round($amount * ($creditAdditionalPercentage / 100), 2);
            $creditTotalAmount = round($amount + $creditAdditionalAmount, 2);
        }

        $financedByInstallments = max(0, $creditTotalAmount - $downPayment);
        $installments = $paymentType === Sale::PAYMENT_CREDIT ? array_values($data['installments'] ?? []) : [];

        if ($paymentType === Sale::PAYMENT_CREDIT) {
            if ($financedByInstallments > 0 && empty($installments)) {
                throw new SaleException('Debes registrar al menos una cuota para el saldo a financiar.', 'installments');
            }
            if (!empty($installments)) {
                $sumPercent = collect($installments)->sum(fn ($it) => (float) ($it['percentage'] ?? 0));
                $sumAmount = collect($installments)->sum(fn ($it) => (float) ($it['amount'] ?? 0));
                if (abs($sumPercent - 100) > 0.1) {
                    throw new SaleException('La suma de porcentajes de cuotas debe ser 100%.', 'installments');
                }
                if (abs($sumAmount - $financedByInstallments) > 0.1) {
                    throw new SaleException('La suma de montos de cuotas debe coincidir con el saldo a financiar (total − cuota inicial).', 'installments');
                }
            }
        }

        return DB::transaction(function () use ($company, $userId, $session, $data, $images, $branch, $warehouseId, $paymentType, $amount, $downPayment, $creditAdditionalPercentage, $creditAdditionalAmount, $financedByInstallments, $installments) {
            // Bloquea el producto: dos ventas simultáneas no pueden vender la misma unidad.
            $product = Product::lockForUpdate()->find($data['product_id']);
            if (!$product || (int) $product->company_id !== (int) $company->id) {
                throw new SaleException('El producto no es válido.', 'product_id');
            }
            if ($this->freeStock($company->id, $product->id, $warehouseId) <= 0) {
                throw new SaleException('Este producto no tiene stock disponible en el almacén de la sucursal seleccionada.', 'product_id');
            }

            // Cliente: existente de la empresa o uno nuevo
            $clientId = null;
            if (!empty($data['client_id'])) {
                $client = Client::find($data['client_id']);
                if (!$client || (int) $client->company_id !== (int) $company->id) {
                    throw new SaleException('El cliente no es válido.', 'client_id');
                }
                $clientId = $client->id;
            } elseif (!empty($data['client_name'])) {
                $clientId = Client::create([
                    'company_id' => $company->id,
                    'name' => $data['client_name'],
                    'id_number' => $data['client_id_number'] ?? null,
                    'phone' => $data['client_phone'] ?? null,
                    'email' => $data['client_email'] ?? null,
                    'address' => $data['client_address'] ?? null,
                    'active' => true,
                ])->id;
            }

            // Origen: prenda vendible de un préstamo incumplido o compra
            $collateral = LoanCollateral::where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->where('status', 'sellable')
                ->whereNull('deleted_at')
                ->first();
            $purchaseOrigin = Purchase::where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->latest()
                ->first();

            $cost = (float) $product->cost;
            $isCredit = $paymentType === Sale::PAYMENT_CREDIT;

            $sale = $company->sales()->create([
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouseId,
                'product_id' => $product->id,
                'credit_category_id' => $product->credit_category_id,
                'client_id' => $clientId,
                'created_by' => $userId,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'loan_collateral_id' => $collateral?->id,
                'purchase_id' => $purchaseOrigin?->id,
                'amount' => $amount,
                'payment_type' => $paymentType,
                'credit_additional_percentage' => $isCredit ? $creditAdditionalPercentage : 0,
                'credit_additional_amount' => $isCredit ? $creditAdditionalAmount : 0,
                'down_payment' => $isCredit ? $downPayment : 0,
                'cost' => $cost,
                'profit' => round($amount - $cost, 2),
                'notes' => $data['notes'] ?? null,
                'status' => Sale::STATUS_COMPLETED,
                'sale_date' => now()->toDateString(),
            ]);

            if ($isCredit) {
                // El recargo se reparte proporcionalmente en las cuotas.
                $interestRatio = $financedByInstallments > 0 ? $creditAdditionalAmount / $financedByInstallments : 0.0;
                foreach ($installments as $index => $installment) {
                    $amt = (float) $installment['amount'];
                    $interest = round($amt * $interestRatio, 2);
                    SaleInstallment::create([
                        'sale_id' => $sale->id,
                        'installment_no' => $index + 1,
                        'due_date' => $installment['due_date'],
                        'percentage' => (float) $installment['percentage'],
                        'amount' => $amt,
                        'principal_amount' => round($amt - $interest, 2),
                        'interest_amount' => $interest,
                        'status' => SaleInstallment::STATUS_PENDIENTE,
                    ]);
                }
            }

            $collateral?->update(['status' => 'sold']);

            // Ingreso en caja: contado completo, o cuota inicial si es crédito
            $code = $this->code($sale);
            if (!$isCredit) {
                $this->cashMovement($sale, $session, $userId, 'income', 'sale', $amount, 'Venta ' . $code . ' - ' . $product->name);
            } elseif ($downPayment > 0) {
                $this->cashMovement($sale, $session, $userId, 'income', 'sale_down_payment', $downPayment, 'Cuota inicial venta ' . $code . ' - ' . $product->name);
            }

            // Salida del producto del almacén
            InventoryMovement::create([
                'company_id' => $company->id,
                'warehouse_id' => $warehouseId,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => $userId,
                'type' => 'out',
                'quantity' => 1,
                'unit_cost' => $amount,
                'reference' => 'VENTA-' . $sale->id,
                'notes' => 'Salida por venta ' . $code,
                'movement_date' => now(),
            ]);

            foreach ($images as $image) {
                if (!$image) continue;
                SaleImage::create([
                    'sale_id' => $sale->id,
                    'path' => $image->store('sales/' . $sale->id, 'public'),
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $image->getMimeType(),
                    'size_bytes' => $image->getSize(),
                ]);
            }

            return $sale;
        });
    }

    /* ── Cuotas, liquidación y devolución ──────────────────────── */

    /** Cobra una cuota de una venta al crédito. */
    public function payInstallment(SaleInstallment $installment, int $userId, CashRegisterSession $session, ?string $notes = null): SaleInstallment
    {
        return DB::transaction(function () use ($installment, $userId, $session, $notes) {
            $installment = SaleInstallment::lockForUpdate()->findOrFail($installment->id);
            $sale = Sale::with('product')->findOrFail($installment->sale_id);

            if ($installment->status === SaleInstallment::STATUS_PAGADA) {
                throw new SaleException('Esta cuota ya fue pagada.');
            }
            if ($installment->status === SaleInstallment::STATUS_ANULADA || $sale->status !== Sale::STATUS_COMPLETED) {
                throw new SaleException('Esta cuota está anulada: la venta ya no está vigente.');
            }

            $installment->update([
                'status' => SaleInstallment::STATUS_PAGADA,
                'paid_at' => now(),
                'payment_notes' => $notes,
            ]);

            CashMovement::create([
                'company_id' => $sale->company_id,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'user_id' => $userId,
                'type' => 'income',
                'category' => 'sale_installment',
                'amount' => $installment->amount,
                'reference_type' => SaleInstallment::class,
                'reference_id' => $installment->id,
                'description' => sprintf(
                    'Cuota %d/%d - Venta #%s (%s)',
                    $installment->installment_no,
                    $sale->installments()->count(),
                    str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT),
                    $sale->product?->name ?? '-'
                ),
                'movement_date' => now(),
            ]);

            return $installment;
        });
    }

    /**
     * Monto para liquidar: capital de las cuotas impagas + interés solo de las vencidas
     * (o que vencen hoy). Las cuotas sin desglose cuentan completas como capital.
     *
     * @return array{principal: float, interest: float, total: float, unpaid_count: int}
     */
    public function liquidationBreakdown(Sale $sale): array
    {
        $unpaid = $sale->installments()
            ->whereIn('status', [SaleInstallment::STATUS_PENDIENTE, SaleInstallment::STATUS_VENCIDA])
            ->get();

        $withBreakdown = $unpaid->filter(fn ($i) => (float) $i->principal_amount > 0);
        $withoutBreakdown = $unpaid->filter(fn ($i) => (float) $i->principal_amount == 0);

        $principal = (float) $withBreakdown->sum('principal_amount') + (float) $withoutBreakdown->sum('amount');
        $interest = (float) $withBreakdown
            ->filter(fn ($i) => $i->status === SaleInstallment::STATUS_VENCIDA
                || ($i->due_date && $i->due_date->lte(now()->startOfDay())))
            ->sum('interest_amount');

        return [
            'principal' => round($principal, 2),
            'interest' => round($interest, 2),
            'total' => round($principal + $interest, 2),
            'unpaid_count' => $unpaid->count(),
        ];
    }

    /** Liquida (cancela) todas las cuotas impagas de una venta al crédito. Devuelve el monto cobrado. */
    public function liquidate(Sale $sale, int $userId, CashRegisterSession $session): float
    {
        return DB::transaction(function () use ($sale, $userId, $session) {
            $sale = Sale::with('product')->lockForUpdate()->findOrFail($sale->id);

            if (!$sale->isCredit()) {
                throw new SaleException('Solo se puede liquidar ventas al crédito.');
            }
            if ($sale->status !== Sale::STATUS_COMPLETED) {
                throw new SaleException('Solo se pueden liquidar ventas con estado "Completada".');
            }

            $breakdown = $this->liquidationBreakdown($sale);
            if ($breakdown['unpaid_count'] === 0) {
                throw new SaleException('No hay cuotas pendientes para liquidar.');
            }

            $sale->installments()
                ->whereIn('status', [SaleInstallment::STATUS_PENDIENTE, SaleInstallment::STATUS_VENCIDA])
                ->update(['status' => SaleInstallment::STATUS_PAGADA, 'paid_at' => now()]);

            $this->cashMovement($sale, $session, $userId, 'income', 'sale_liquidation', $breakdown['total'],
                sprintf('Liquidación venta %s (%s)', $this->code($sale), $sale->product?->name ?? '-'));

            return $breakdown['total'];
        });
    }

    /**
     * Devolución: reingresa el producto, devuelve en caja lo cobrado (contado completo, o cuota
     * inicial + cuotas pagadas), anula las cuotas pendientes, la prenda vuelve a "vendible" y la
     * venta queda devuelta. Consume la autorización puntual [$grant] si se usó una.
     */
    public function processReturn(Sale $sale, int $userId, CashRegisterSession $session, string $reason, ?string $notes, ?SaleDeleteGrant $grant = null): Sale
    {
        return DB::transaction(function () use ($sale, $userId, $session, $reason, $notes, $grant) {
            $sale = Sale::with(['installments', 'loanCollateral', 'product'])->lockForUpdate()->findOrFail($sale->id);

            if (!$sale->isReturnable()) {
                throw new SaleException('Esta venta no puede ser devuelta (estado: ' . (Sale::STATUS_LABELS[$sale->status] ?? $sale->status) . ').');
            }

            $code = $this->code($sale);

            // 1. Reingreso del producto al almacén
            InventoryMovement::create([
                'company_id' => $sale->company_id,
                'warehouse_id' => $sale->warehouse_id,
                'branch_id' => $sale->branch_id,
                'product_id' => $sale->product_id,
                'user_id' => $userId,
                'type' => 'in',
                'quantity' => 1,
                'unit_cost' => $sale->cost,
                'reference' => 'DEVOLUCION-' . $sale->id,
                'notes' => 'Devolución de venta ' . $code,
                'movement_date' => now(),
            ]);

            // 2. Devolver en caja lo cobrado
            if ($sale->isCash()) {
                $this->cashMovement($sale, $session, $userId, 'expense', 'sale_return', (float) $sale->amount,
                    'Devolución venta ' . $code . ' - ' . ($sale->product?->name ?? ''));
            } else {
                $down = (float) ($sale->down_payment ?? 0);
                if ($down > 0) {
                    $this->cashMovement($sale, $session, $userId, 'expense', 'sale_return', $down,
                        'Devolución cuota inicial - venta ' . $code);
                }
                foreach ($sale->installments->where('status', SaleInstallment::STATUS_PAGADA) as $installment) {
                    $this->cashMovement($sale, $session, $userId, 'expense', 'sale_return', (float) $installment->amount,
                        'Devolución cuota ' . $installment->installment_no . ' - venta ' . $code);
                }
            }

            // 3. Anular cuotas pendientes / vencidas
            $sale->installments()
                ->whereIn('status', [SaleInstallment::STATUS_PENDIENTE, SaleInstallment::STATUS_VENCIDA])
                ->update(['status' => SaleInstallment::STATUS_ANULADA]);

            // 4. La prenda vuelve a estar a la venta
            $sale->loanCollateral?->update(['status' => 'sellable']);

            // 5. Venta devuelta
            $sale->update([
                'status' => Sale::STATUS_RETURNED,
                'returned_at' => now(),
                'returned_by' => $userId,
                'return_reason' => $reason,
                'return_notes' => $notes,
            ]);

            // 6. La autorización puntual es de un solo uso (dentro de la transacción: si algo falla no se gasta)
            $grant?->update(['used_at' => now()]);

            return $sale;
        });
    }

    /* ── Internos ──────────────────────────────────────────────── */

    protected function code(Sale $sale): string
    {
        return 'VE-' . str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT);
    }

    protected function cashMovement(Sale $sale, CashRegisterSession $session, int $userId, string $type, string $category, float $amount, string $description): void
    {
        CashMovement::create([
            'company_id' => $sale->company_id,
            'cash_register_id' => $session->cash_register_id,
            'cash_register_session_id' => $session->id,
            'user_id' => $userId,
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'description' => $description,
            'movement_date' => now(),
        ]);
    }
}
