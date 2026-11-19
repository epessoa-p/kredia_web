<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditCategory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseImage;
use App\Support\Currency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Compras (el negocio compra un artículo a un cliente): alta y eliminación.
 * Una sola fuente de verdad para la web (Purchases\PurchaseController) y la API móvil
 * (Api\PurchaseController).
 *
 * El alta corre en una transacción y bloquea la sesión de caja (lockForUpdate) para que
 * dos compras simultáneas no gasten más efectivo del que hay. La eliminación bloquea la
 * compra para no revertirla dos veces. Los errores de negocio se lanzan como
 * PurchaseException con el mensaje listo para el usuario.
 */
class PurchaseService
{
    public const KARATS = ['10K', '14K', '18K', '21K', '22K', '24K'];
    public const MATERIALS = ['Oro', 'Plata', 'Platino', 'Acero', 'Otro'];

    public static function validationRules(): array
    {
        return [
            'credit_category_id' => 'required|integer|exists:credit_categories,id',
            'product_name' => 'nullable|string|max:255',
            'product_brand' => 'nullable|string|max:255',
            'product_model_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'product_plate' => 'nullable|string|max:120',
            'product_chassis' => 'nullable|string|max:120',
            'product_serial_number' => 'nullable|string|max:120',
            'product_item_model' => 'nullable|string|max:120',
            'product_item_type' => 'nullable|string|max:120',
            'product_karat' => 'nullable|string|max:20',
            'product_material_type' => 'nullable|string|max:60',
            'product_price' => 'nullable|numeric|min:0',
            'product_details' => 'nullable|string|max:1000',
            'client_id' => 'nullable|integer|exists:clients,id',
            'client_name' => 'nullable|required_without:client_id|string|max:255',
            'client_id_number' => 'nullable|string|max:30',
            'client_phone' => 'nullable|string|max:20',
            'client_email' => 'nullable|email|max:255',
            'client_address' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:20',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    /** Mensajes de validación en español (web y API). */
    public static function validationMessages(): array
    {
        return [
            'credit_category_id.required' => 'Selecciona la categoría del producto.',
            'credit_category_id.integer' => 'La categoría no es válida.',
            'credit_category_id.exists' => 'La categoría no es válida.',
            'product_model_year.integer' => 'El año del modelo debe ser un número.',
            'product_model_year.min' => 'El año del modelo no es válido.',
            'product_model_year.max' => 'El año del modelo no es válido.',
            'product_price.numeric' => 'El precio de venta debe ser un número.',
            'product_price.min' => 'El precio de venta no puede ser negativo.',
            'product_details.max' => 'Las observaciones no pueden superar los 1000 caracteres.',
            'client_id.integer' => 'El cliente no es válido.',
            'client_id.exists' => 'El cliente no es válido.',
            'client_name.required_without' => 'Selecciona un cliente o escribe el nombre del cliente nuevo.',
            'client_name.max' => 'El nombre del cliente es demasiado largo.',
            'client_id_number.max' => 'El documento del cliente es demasiado largo.',
            'client_phone.max' => 'El teléfono del cliente es demasiado largo.',
            'client_email.email' => 'El correo del cliente no es válido.',
            'amount.required' => 'Ingresa el monto de la compra.',
            'amount.numeric' => 'El monto de la compra debe ser un número.',
            'amount.min' => 'El monto de la compra debe ser mayor a 0.',
            'notes.max' => 'Las notas no pueden superar los 2000 caracteres.',
            'images.max' => 'Puedes adjuntar como máximo 20 imágenes.',
            'images.*.image' => 'Cada archivo debe ser una imagen.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WEBP.',
            'images.*.max' => 'Cada imagen puede pesar como máximo 5 MB.',
            // Comodines al final: Laravel usa la primera clave que coincide.
            '*.max' => 'El texto es demasiado largo.',
            '*.string' => 'El valor debe ser texto.',
        ];
    }

    /**
     * Registra la compra: producto (existente o nuevo), cliente (existente o nuevo), egreso en
     * caja e ingreso del producto al almacén de la sucursal de la caja.
     *
     * @param  UploadedFile[]  $images
     */
    public function create(Company $company, int $userId, CashRegisterSession $session, array $data, array $images = []): Purchase
    {
        // La sucursal la manda la CAJA abierta (igual que préstamos y ventas).
        $session->loadMissing('cashRegister.branch');
        $branch = $session->cashRegister?->branch
            ?? (isset($data['branch_id']) ? Branch::find($data['branch_id']) : null);
        if (!$branch || (int) $branch->company_id !== (int) $company->id) {
            throw new PurchaseException('La caja abierta no tiene una sucursal válida.');
        }

        $category = CreditCategory::find($data['credit_category_id'] ?? null);
        if (!$category || (int) $category->company_id !== (int) $company->id || !$category->active) {
            throw new PurchaseException('La categoría no es válida o está inactiva.', 'credit_category_id');
        }
        $categoryId = (int) $category->id;
        // Opciones fijas: se normalizan (la web pasa los textos a MAYÚSCULAS).
        if (isset($data['product_karat'])) {
            $data['product_karat'] = strtoupper(trim((string) $data['product_karat']));
        }
        if (!empty($data['product_material_type'])) {
            $data['product_material_type'] = ucfirst(mb_strtolower(trim((string) $data['product_material_type'])));
        }
        $this->validateProductFields($categoryId, $data);

        $amount = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($company, $userId, $session, $data, $images, $branch, $category, $categoryId, $amount) {
            // Bloquea la caja: dos compras simultáneas no pueden gastar el mismo efectivo.
            $session = CashRegisterSession::lockForUpdate()->find($session->id);
            if (!$session || $session->closed_at) {
                throw new PurchaseException('La caja ya fue cerrada. Abre una caja para registrar la compra.');
            }
            $available = $session->currentBalance();
            if ($amount > $available + 0.001) {
                throw new PurchaseException(
                    'El monto de la compra (' . Currency::format($amount, $company) . ') supera el saldo disponible en caja ('
                    . Currency::format($available, $company) . '). No puedes gastar más de lo que hay en la caja.',
                    'amount'
                );
            }

            $client = $this->resolveClient($company, $data);
            $productName = $this->productName($category, $data);
            $product = $this->createProduct($company, $category, $productName, $data, $amount);

            $purchase = $company->purchases()->create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'credit_category_id' => $category->id,
                'client_id' => $client->id,
                'created_by' => $userId,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
                'status' => 'completed',
                'purchase_date' => now()->toDateString(),
            ]);
            // El producto lleva el código de la compra (CO-00007) y su sucursal.
            $product->assignOrigin($this->code($purchase), $purchase->branch_id);
            $code = $this->code($purchase);

            CashMovement::create([
                'company_id' => $company->id,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'user_id' => $userId,
                'type' => 'expense',
                'category' => 'purchase',
                'amount' => $amount,
                'reference_type' => Purchase::class,
                'reference_id' => $purchase->id,
                'description' => 'Compra ' . $code . ' - ' . $productName,
                'movement_date' => now(),
            ]);

            // El producto entra al almacén de la sucursal para la venta.
            if ($branch->warehouse_id) {
                InventoryMovement::create([
                    'company_id' => $company->id,
                    'warehouse_id' => $branch->warehouse_id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'type' => 'in',
                    'quantity' => 1,
                    'unit_cost' => $amount,
                    'reference' => 'COMPRA-' . $purchase->id,
                    'notes' => 'Ingreso por compra ' . $code . ' (para venta)',
                    'movement_date' => now(),
                ]);
            }

            foreach ($images as $image) {
                if (!$image instanceof UploadedFile) {
                    continue;
                }
                PurchaseImage::create([
                    'purchase_id' => $purchase->id,
                    'path' => $image->store('purchase-images/purchase-' . $purchase->id, 'public'),
                    'original_name' => $image->getClientOriginalName(),
                    'mime_type' => $image->getClientMimeType(),
                    'size_bytes' => $image->getSize(),
                ]);
            }

            return $purchase;
        });
    }

    /** Motivo por el que la compra no se puede eliminar, o null si se puede. */
    public function deleteBlockReason(Purchase $purchase): ?string
    {
        if ($purchase->status !== 'completed') {
            return 'Esta compra ya no se puede eliminar.';
        }
        if ($purchase->product_id && $this->productStock((int) $purchase->product_id) < 1) {
            return 'No se puede eliminar: el producto de esta compra ya salió del inventario (vendido o retirado).';
        }

        return null;
    }

    /**
     * Elimina la compra: devuelve a la caja abierta del usuario lo pagado y saca el producto
     * del almacén. Devuelve el monto devuelto a la caja.
     */
    public function delete(Purchase $purchase, int $userId, CashRegisterSession $session): float
    {
        return DB::transaction(function () use ($purchase, $userId, $session) {
            // Bloquea la compra: dos eliminaciones simultáneas no devuelven el dinero dos veces.
            $purchase = Purchase::lockForUpdate()->find($purchase->id);
            if (!$purchase) {
                throw new PurchaseException('La compra ya fue eliminada.');
            }
            if ($reason = $this->deleteBlockReason($purchase)) {
                throw new PurchaseException($reason);
            }

            $code = $this->code($purchase);
            $spent = (float) CashMovement::where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->where('type', 'expense')
                ->sum('amount');

            if ($spent > 0) {
                CashMovement::create([
                    'company_id' => $purchase->company_id,
                    'cash_register_id' => $session->cash_register_id,
                    'cash_register_session_id' => $session->id,
                    'user_id' => $userId,
                    'type' => 'income',
                    'category' => 'purchase_deletion',
                    'amount' => $spent,
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'description' => 'Reversión por eliminación compra ' . $code,
                    'movement_date' => now(),
                ]);
            }

            $warehouseId = Branch::find($purchase->branch_id)?->warehouse_id;
            if ($warehouseId && $purchase->product_id) {
                InventoryMovement::create([
                    'company_id' => $purchase->company_id,
                    'warehouse_id' => $warehouseId,
                    'branch_id' => $purchase->branch_id,
                    'product_id' => $purchase->product_id,
                    'user_id' => $userId,
                    'type' => 'out',
                    'quantity' => 1,
                    'unit_cost' => (float) $purchase->amount,
                    'reference' => 'COMPRA-DEL-' . $purchase->id,
                    'notes' => 'Salida por eliminación de la compra ' . $code,
                    'movement_date' => now(),
                ]);
            }

            $purchase->delete();

            return $spent;
        });
    }

    public function code(Purchase $purchase): string
    {
        return 'CO-' . str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT);
    }

    /* ── Internos ──────────────────────────────────────────────── */

    /** Campos obligatorios según la categoría (igual que en préstamos). */
    public function validateProductFields(int $categoryId, array $data): void
    {
        $required = match (true) {
            $categoryId === CreditCategory::CATEGORIA_VEHICULO => [
                'product_name' => 'El nombre es obligatorio para vehículos.',
                'product_brand' => 'La marca es obligatoria para vehículos.',
                'product_model_year' => 'El modelo (año) es obligatorio para vehículos.',
                'product_plate' => 'La placa es obligatoria para vehículos.',
                'product_chassis' => 'El chasis es obligatorio para vehículos.',
            ],
            $categoryId === CreditCategory::CATEGORIA_JOYAS => [
                'product_item_type' => 'El tipo de joya es obligatorio.',
                'product_karat' => 'Selecciona el kilataje de la joya.',
            ],
            $categoryId === CreditCategory::CATEGORIA_GARRAFAS => [],
            // Cualquier otra categoría usa la regla de Artículos.
            default => [
                'product_name' => 'El nombre es obligatorio para artículos.',
                'product_brand' => 'La marca es obligatoria para artículos.',
                'product_serial_number' => 'El serial es obligatorio para artículos.',
            ],
        };

        foreach ($required as $field => $message) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new PurchaseException($message, $field);
            }
        }

        if ($categoryId === CreditCategory::CATEGORIA_JOYAS) {
            if (!in_array((string) $data['product_karat'], self::KARATS, true)) {
                throw new PurchaseException('El kilataje no es válido.', 'product_karat');
            }
            $material = $data['product_material_type'] ?? null;
            if ($material !== null && $material !== '' && !in_array($material, self::MATERIALS, true)) {
                throw new PurchaseException('El tipo de material no es válido.', 'product_material_type');
            }
        }
    }

    /** Nombre del producto: el tipo de joya, el nombre de la categoría (garrafas) o el escrito. */
    public function productName(CreditCategory $category, array $data): string
    {
        return match ((int) $category->id) {
            CreditCategory::CATEGORIA_JOYAS => trim((string) ($data['product_item_type'] ?? '')),
            CreditCategory::CATEGORIA_GARRAFAS => (string) $category->name,
            default => trim((string) ($data['product_name'] ?? '')),
        };
    }

    private function resolveClient(Company $company, array $data): Client
    {
        if (!empty($data['client_id'])) {
            $client = Client::find($data['client_id']);
            if (!$client || (int) $client->company_id !== (int) $company->id) {
                throw new PurchaseException('El cliente no es válido.', 'client_id');
            }
            if (!$client->active) {
                throw new PurchaseException('El cliente está inactivo.', 'client_id');
            }

            return $client;
        }

        return Client::create([
            'company_id' => $company->id,
            'name' => $data['client_name'],
            'id_number' => $data['client_id_number'] ?? null,
            'phone' => $data['client_phone'] ?? null,
            'email' => $data['client_email'] ?? null,
            'address' => $data['client_address'] ?? null,
            'active' => true,
        ]);
    }

    /** Cada compra crea su propio producto (cantidad 1, con su propio valor). */
    private function createProduct(Company $company, CreditCategory $category, string $productName, array $data, float $amount): Product
    {
        return Product::create([
            'company_id' => $company->id,
            'credit_category_id' => $category->id,
            'name' => $productName,
            'sku' => Product::makeSku($productName),
            'brand' => $data['product_brand'] ?? null,
            'item_model' => $data['product_item_model'] ?? null,
            'material_type' => $data['product_material_type'] ?? null,
            'model_year' => $data['product_model_year'] ?? null,
            'plate' => $data['product_plate'] ?? null,
            'chassis' => $data['product_chassis'] ?? null,
            'serial_number' => $data['product_serial_number'] ?? null,
            'karat' => $data['product_karat'] ?? null,
            'item_type' => $data['product_item_type'] ?? null,
            'description' => $data['product_details'] ?? null,
            'unit' => 'unidad',
            'cost' => $amount,
            'price' => $data['product_price'] ?? 0,
            'active' => true,
        ]);
    }

    private function productStock(int $productId): int
    {
        return (int) InventoryMovement::where('product_id', $productId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) as s")
            ->value('s');
    }
}
