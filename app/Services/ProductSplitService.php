<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Separa los productos compartidos por varios préstamos, compras o ingresos manuales en un producto por cada uno
 * (cantidad 1, con su propio costo y código). Antes, al registrar un préstamo o una compra se reutilizaba un producto
 * igual (ej. "GARRAFAS"), y el inventario mostraba cantidades mayores a 1.
 *
 * Cada "unidad de origen" es un préstamo (incluidos los eliminados), una compra o una entrada manual de inventario.
 * La más antigua se queda con el producto original; cada una de las demás recibe una copia, y se le pasan sus
 * movimientos de inventario, su garantía y sus ventas. Se puede ejecutar varias veces: si ya no hay productos
 * compartidos no hace nada.
 */
class ProductSplitService
{
    /** Movimientos de entrada que no vienen de un préstamo, una compra ni una devolución = ingresos manuales. */
    private const NON_MANUAL_PREFIXES = ['LOAN-', 'COMPRA-', 'DEVOLUCION-', 'VENTA-'];

    /** Productos compartidos: [product_id => cantidad de unidades de origen]. */
    public function sharedProducts(?int $companyId = null): Collection
    {
        $manualIn = DB::table('inventory_movements')
            ->select('product_id')
            ->where('type', 'in')
            ->whereNotNull('product_id')
            ->where(function ($q) {
                foreach (self::NON_MANUAL_PREFIXES as $prefix) {
                    $q->where(fn ($w) => $w->whereNull('reference')->orWhere('reference', 'not like', $prefix . '%'));
                }
            })
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $units = DB::table('loans')->select('product_id')->whereNotNull('product_id')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->unionAll(DB::table('purchases')->select('product_id')->whereNotNull('product_id')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId)))
            ->unionAll($manualIn);

        return DB::query()->fromSub($units, 'u')
            ->select('product_id', DB::raw('COUNT(*) as units'))
            ->groupBy('product_id')
            ->having('units', '>', 1)
            ->pluck('units', 'product_id');
    }

    /** Resumen para mostrar antes de separar. */
    public function preview(?int $companyId = null): array
    {
        $shared = $this->sharedProducts($companyId);

        // Unidades de esos productos que hoy están en inventario (el resto ya salió: liquidadas, vendidas, eliminadas).
        $inStock = $shared->isEmpty() ? 0 : (float) DB::query()->fromSub(
            DB::table('inventory_movements')
                ->select('product_id', 'warehouse_id', DB::raw("SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as stock"))
                ->whereNull('deleted_at')
                ->whereIn('product_id', $shared->keys())
                ->groupBy('product_id', 'warehouse_id'),
            's'
        )->where('stock', '>', 0)->sum('stock');

        return [
            'products' => $shared->count(),
            'origins' => (int) $shared->sum(),
            'in_stock' => (int) round($inStock),
            'new_products' => (int) $shared->sum() - $shared->count(),
        ];
    }

    /**
     * Separa los productos compartidos. Devuelve products (separados), created (copias creadas) y
     * notes (avisos, ej. códigos que ya estaban en uso).
     */
    public function split(?int $companyId = null): array
    {
        $result = ['products' => 0, 'created' => 0, 'notes' => []];

        DB::transaction(function () use ($companyId, &$result) {
            foreach ($this->sharedProducts($companyId)->keys() as $productId) {
                $product = Product::withTrashed()->lockForUpdate()->find($productId);
                if (!$product) {
                    continue;
                }

                $units = $this->units($product);
                if ($units->count() < 2) {
                    continue;
                }

                $result['products']++;

                // Las ventas sin garantía de préstamo pertenecen a la primera unidad que no es un préstamo.
                $firstStockUnit = $units->first(fn ($u) => $u['type'] !== 'loan');
                $first = $units->shift();

                // La unidad más antigua se queda con el producto original y su código de origen.
                $this->applyOrigin($product, $first, $result);

                foreach ($units as $unit) {
                    $copy = $this->makeCopy($product, $unit, $result);
                    $this->moveUnit($product, $copy, $unit);
                    if ($firstStockUnit && $unit === $firstStockUnit) {
                        $this->moveStandaloneSales($product, $copy);
                    }
                    $result['created']++;
                }
            }
        });

        return $result;
    }

    /** Unidades de origen del producto, de la más antigua a la más nueva. */
    private function units(Product $product): Collection
    {
        $loans = DB::table('loans')->where('product_id', $product->id)
            ->get(['id', 'branch_id', 'numero', 'amount', 'created_at'])
            ->map(fn ($l) => [
                'type' => 'loan', 'id' => $l->id, 'branch_id' => $l->branch_id, 'amount' => (float) $l->amount,
                'code' => Product::formatCode('PR', $l->numero ?? $l->id), 'date' => (string) $l->created_at,
            ]);

        $purchases = DB::table('purchases')->where('product_id', $product->id)
            ->get(['id', 'branch_id', 'numero', 'amount', 'created_at'])
            ->map(fn ($p) => [
                'type' => 'purchase', 'id' => $p->id, 'branch_id' => $p->branch_id, 'amount' => (float) $p->amount,
                'code' => Product::formatCode('CO', $p->numero ?? $p->id), 'date' => (string) $p->created_at,
            ]);

        $manual = DB::table('inventory_movements')->where('product_id', $product->id)->where('type', 'in')
            ->where(function ($q) {
                foreach (self::NON_MANUAL_PREFIXES as $prefix) {
                    $q->where(fn ($w) => $w->whereNull('reference')->orWhere('reference', 'not like', $prefix . '%'));
                }
            })
            ->get(['id', 'branch_id', 'unit_cost', 'created_at'])
            ->map(fn ($m) => [
                'type' => 'manual', 'id' => $m->id, 'branch_id' => $m->branch_id, 'amount' => (float) $m->unit_cost,
                'code' => null, 'date' => (string) $m->created_at,
            ]);

        return $loans->concat($purchases)->concat($manual)
            ->sortBy(fn ($u) => $u['date'] . sprintf('%012d', $u['id']))
            ->values();
    }

    /** El producto original toma el código, la sucursal y el costo de su unidad de origen. */
    private function applyOrigin(Product $product, array $unit, array &$result): void
    {
        $data = ['branch_id' => $unit['branch_id']];
        if ($unit['type'] !== 'manual') {
            $data['cost'] = $unit['amount'];
        }

        $code = $unit['code'] ?? $product->code;
        if ($code && !Product::codeTaken((int) $product->company_id, $unit['branch_id'], $code, $product->id)) {
            $data['code'] = $code;
        } elseif ($code !== $product->code) {
            $result['notes'][] = "El código {$code} ya estaba en uso; el producto #{$product->id} conserva {$product->code}.";
        }

        $product->update($data);
    }

    private function makeCopy(Product $product, array $unit, array &$result): Product
    {
        $copy = $product->replicate(['numero', 'code', 'sku', 'branch_id']);
        $copy->sku = Product::makeSku($product->name);
        $copy->branch_id = $unit['branch_id'];
        if ($unit['type'] !== 'manual') {
            $copy->cost = $unit['amount'];
        }

        $code = $unit['code'];
        if ($code && Product::codeTaken((int) $product->company_id, $unit['branch_id'], $code)) {
            $result['notes'][] = "El código {$code} ya estaba en uso; la copia del producto #{$product->id} recibe el siguiente PD-.";
            $code = null;
        }
        $copy->code = $code ?? Product::nextPdCode((int) $product->company_id);
        $copy->created_at = $product->created_at;
        $copy->save();

        return $copy;
    }

    /** Pasa a la copia lo que pertenece a la unidad: el préstamo o la compra, sus movimientos, garantía y ventas. */
    private function moveUnit(Product $product, Product $copy, array $unit): void
    {
        $id = $unit['id'];
        $movements = fn () => DB::table('inventory_movements')->where('product_id', $product->id);

        if ($unit['type'] === 'loan') {
            DB::table('loans')->where('id', $id)->update(['product_id' => $copy->id]);
            $movements()->whereIn('reference', ["LOAN-RET-{$id}", "LOAN-REL-{$id}", "LOAN-DEL-{$id}"])
                ->update(['product_id' => $copy->id]);

            $collateralIds = DB::table('loan_collaterals')->where('loan_id', $id)->pluck('id');
            DB::table('loan_collaterals')->whereIn('id', $collateralIds)->update(['product_id' => $copy->id]);

            $saleIds = DB::table('sales')->where('product_id', $product->id)
                ->whereIn('loan_collateral_id', $collateralIds)->pluck('id');
            $this->moveSales($product, $copy, $saleIds);
        } elseif ($unit['type'] === 'purchase') {
            DB::table('purchases')->where('id', $id)->update(['product_id' => $copy->id]);
            $movements()->whereIn('reference', ["COMPRA-{$id}", "COMPRA-DEL-{$id}"])
                ->update(['product_id' => $copy->id]);
        } else {
            $movements()->where('id', $id)->update(['product_id' => $copy->id]);
        }
    }

    /** Ventas sin garantía de préstamo (de stock comprado o ingresado a mano). */
    private function moveStandaloneSales(Product $product, Product $copy): void
    {
        $saleIds = DB::table('sales')->where('product_id', $product->id)->whereNull('loan_collateral_id')->pluck('id');
        $this->moveSales($product, $copy, $saleIds);
    }

    private function moveSales(Product $product, Product $copy, Collection $saleIds): void
    {
        if ($saleIds->isEmpty()) {
            return;
        }

        DB::table('sales')->whereIn('id', $saleIds)->update(['product_id' => $copy->id]);
        $references = $saleIds->flatMap(fn ($id) => ["VENTA-{$id}", "DEVOLUCION-{$id}"])->all();
        DB::table('inventory_movements')->where('product_id', $product->id)
            ->whereIn('reference', $references)->update(['product_id' => $copy->id]);
    }
}
