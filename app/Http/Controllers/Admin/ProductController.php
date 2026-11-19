<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CreditCategory;
use App\Models\Product;
use App\Services\ProductSplitService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = Product::with('company', 'creditCategory')->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $user->getCurrentCompany()?->id);
        }

        return view('admin.products.index', [
            'products' => $query->paginate(15),
            // Solo el super admin ve el aviso de productos compartidos por varios préstamos o compras.
            'sharedPreview' => $user->is_super_admin ? app(ProductSplitService::class)->preview() : null,
        ]);
    }

    /** Separa los productos compartidos en un producto por préstamo, compra o ingreso (solo super admin). */
    public function splitShared(ProductSplitService $service)
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        try {
            $result = $service->split();
        } catch (\Throwable $exception) {
            Log::error('Error al separar productos compartidos', ['message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible separar los productos. No se cambió nada.']);
        }

        if ($result['products'] === 0) {
            return back()->with('success', 'No hay productos compartidos: cada producto ya pertenece a un solo préstamo o compra.');
        }

        $message = "Se separaron {$result['products']} productos compartidos: se crearon {$result['created']} productos nuevos, cada uno con su préstamo o compra y cantidad 1.";
        if (!empty($result['notes'])) {
            $message .= ' ' . implode(' ', array_slice($result['notes'], 0, 5));
        }

        return back()->with('success', $message);
    }

    /**
     * Un producto es un artículo único (cantidad 1): crearlo es ingresarlo al inventario de una sucursal.
     * Por eso "Nuevo producto" abre el mismo formulario que Inventario › Ingresar producto.
     */
    public function create()
    {
        return redirect()->route('inventory.stock-entry', array_filter(['sucursal_id' => request('sucursal_id')]));
    }

    public function edit(Product $product)
    {
        $this->authorizeProduct($product);
        $user = auth()->user();

        return view('admin.products.edit', [
            'product' => $product,
            'companies' => $user->is_super_admin ? Company::orderBy('name')->get() : collect([$user->getCurrentCompany()])->filter(),
            'categories' => CreditCategory::where('company_id', $product->company_id)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Product $product)
    {
        $this->authorizeProduct($product);

        try {
            $user = auth()->user();
            $companyId = $user->is_super_admin ? request('company_id', $product->company_id) : $product->company_id;

            $validated = request()->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'name' => 'required|string|max:255',
                'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
                'credit_category_id' => ['nullable', 'exists:credit_categories,id'],
                'description' => 'nullable|string',
                'unit' => 'required|string|max:50',
                'item_model' => 'nullable|string|max:255',
                'material_type' => 'nullable|string|max:255',
                'cost' => 'required|numeric|min:0',
                'price' => 'required|numeric|min:0',
                'active' => 'sometimes|boolean',
                'code' => 'required|string|max:30',
            ], self::messages());

            // El código se puede cambiar, pero no repetir en la sucursal del producto.
            $code = Product::normalizeCode($validated['code']);
            if (Product::codeTaken((int) $companyId, $product->branch_id, $code, $product->id)) {
                throw ValidationException::withMessages(['code' => "El código {$code} ya está en uso en esta sucursal."]);
            }

            $product->update([
                ...$validated,
                'code' => $code,
                'company_id' => $companyId,
                'active' => request()->boolean('active', false),
            ]);

            return redirect()->route('products.index')->with('success', 'Producto actualizado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar producto', ['product_id' => $product->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el producto.']);
        }
    }

    public function destroy(Product $product)
    {
        $this->authorizeProduct($product);

        try {
            $product->delete();
            return redirect()->route('products.index')->with('success', 'Producto eliminado exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar producto', ['product_id' => $product->id, 'message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible eliminar el producto.']);
        }
    }

    protected function authorizeProduct(Product $product): void
    {
        if (!auth()->user()->is_super_admin && $product->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    protected static function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'sku.required' => 'El SKU es obligatorio.',
            'sku.unique' => 'Ya existe un producto con ese SKU.',
            'unit.required' => 'La unidad es obligatoria.',
            'cost.required' => 'El costo es obligatorio.',
            'cost.numeric' => 'El costo debe ser un número.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'code.required' => 'El código del producto es obligatorio.',
            'code.max' => 'El código no puede tener más de 30 caracteres.',
            'max' => 'El valor es demasiado largo.',
            'min' => 'El valor no puede ser negativo.',
        ];
    }
}