<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Loan;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = Branch::with('company')->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $user->getCurrentCompany()?->id);
        }

        return view('admin.branches.index', ['branches' => $query->paginate(15)]);
    }

    public function create()
    {
        $user = auth()->user();
        $companyId = $user->is_super_admin ? request('company_id') : $user->getCurrentCompany()?->id;

        $warehousesQuery = Warehouse::orderBy('name');
        if ($companyId) {
            $warehousesQuery->where('company_id', $companyId);
        } elseif (!$user->is_super_admin) {
            $warehousesQuery->whereRaw('1 = 0');
        }

        return view('admin.branches.create', [
            'companies' => $user->is_super_admin ? Company::orderBy('name')->get() : collect([$user->getCurrentCompany()])->filter(),
            'warehouses' => $warehousesQuery->get(),
        ]);
    }

    public function store()
    {
        try {
            $user = auth()->user();
            $companyId = $user->is_super_admin ? request('company_id') : $user->getCurrentCompany()?->id;

            $validated = request()->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'name' => 'required|string|max:255',
                'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')],
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:255',
                'manager_name' => 'nullable|string|max:255',
                'warehouse_id' => ['required', 'exists:warehouses,id'],
                'active' => 'sometimes|boolean',
                'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ]);

            if ($user->is_super_admin && empty($companyId)) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            if ($limitError = $this->planLimitError((int) $companyId, ['branches'])) {
                return back()->withInput()->withErrors(['error' => $limitError]);
            }

            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
            if ($warehouse->company_id !== $companyId) {
                abort(403);
            }

            Branch::create([
                'company_id' => $companyId,
                'warehouse_id' => $validated['warehouse_id'],
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'manager_name' => $validated['manager_name'] ?? null,
                'active' => request()->boolean('active', true),
                'color' => $validated['color'] ?? '#0d6efd',
            ]);

            return redirect()->route('branches.index')->with('success', 'Sucursal creada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al crear sucursal', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear la sucursal.']);
        }
    }

    public function show(Branch $branch)
    {
        $this->authorizeBranch($branch);
        $branch->load('company', 'warehouse');

        // Datos para el panel de reinicio (Zona de peligro).
        $resetCounts = [
            'loans'     => Loan::withTrashed()->where('branch_id', $branch->id)->count(),
            'sales'     => Sale::withTrashed()->where('branch_id', $branch->id)->count(),
            'purchases' => Purchase::withTrashed()->where('branch_id', $branch->id)->count(),
        ];
        // El inventario solo se borra si el almacén es exclusivo de esta sucursal.
        $warehouseExclusive = $branch->warehouse_id
            ? (Warehouse::where('id', $branch->warehouse_id)->first()?->defaultForBranches()->count() === 1)
            : false;

        return view('admin.branches.show', compact('branch', 'resetCounts', 'warehouseExclusive'));
    }

    public function edit(Branch $branch)
    {
        $this->authorizeBranch($branch);
        $user = auth()->user();

        return view('admin.branches.edit', [
            'branch' => $branch,
            'companies' => $user->is_super_admin ? Company::orderBy('name')->get() : collect([$user->getCurrentCompany()])->filter(),
            'warehouses' => Warehouse::where('company_id', $branch->company_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Branch $branch)
    {
        $this->authorizeBranch($branch);

        try {
            $user = auth()->user();
            $companyId = $user->is_super_admin ? request('company_id', $branch->company_id) : $branch->company_id;

            $validated = request()->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'name' => 'required|string|max:255',
                'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)],
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:255',
                'manager_name' => 'nullable|string|max:255',
                'warehouse_id' => ['required', 'exists:warehouses,id'],
                'active' => 'sometimes|boolean',
                'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ]);

            if ($user->is_super_admin && empty($companyId)) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
            if ($warehouse->company_id !== $companyId) {
                abort(403);
            }

            $branch->update([
                ...$validated,
                'company_id' => $companyId,
                'warehouse_id' => $validated['warehouse_id'],
                'active' => request()->boolean('active', false),
            ]);

            return redirect()->route('branches.index')->with('success', 'Sucursal actualizada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar sucursal', ['branch_id' => $branch->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar la sucursal.']);
        }
    }

    public function destroy(Branch $branch)
    {
        $this->authorizeBranch($branch);

        try {
            $branch->delete();
            return redirect()->route('branches.index')->with('success', 'Sucursal eliminada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar sucursal', ['branch_id' => $branch->id, 'message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible eliminar la sucursal.']);
        }
    }

    /**
     * Reinicia una sucursal: borra TODOS sus registros operativos
     * (préstamos, ventas, compras, pagos, caja, inventario…) conservando
     * personal, cajas configuradas, clientes y productos. Acción destructiva.
     */
    public function reset(Request $request, Branch $branch)
    {
        $this->authorizeBranch($branch);

        // Confirmación: hay que escribir el nombre exacto de la sucursal.
        $request->validate(['confirm_name' => 'required|string']);
        if (mb_strtolower(trim($request->input('confirm_name'))) !== mb_strtolower(trim((string) $branch->name))) {
            return back()->withErrors(['confirm_name' => 'El nombre no coincide con la sucursal.']);
        }

        // Cajas de la sucursal (la caja NO tiene branch_id en sus movimientos).
        $cashRegisterIds = CashRegister::where('branch_id', $branch->id)->pluck('id');

        // El inventario se borra solo si el almacén es exclusivo de esta sucursal.
        $warehouseId = $branch->warehouse_id;
        $warehouseExclusive = $warehouseId
            ? (Warehouse::where('id', $warehouseId)->first()?->defaultForBranches()->count() === 1)
            : false;

        try {
            DB::transaction(function () use ($branch, $cashRegisterIds, $warehouseId, $warehouseExclusive) {
                // Historial de caja (movimientos + sesiones), conservando las cajas.
                if ($cashRegisterIds->isNotEmpty()) {
                    DB::table('cash_movements')->whereIn('cash_register_id', $cashRegisterIds)->delete();
                    DB::table('cash_register_sessions')->whereIn('cash_register_id', $cashRegisterIds)->delete();
                }

                // Operaciones principales. Las FK en CASCADE limpian el detalle:
                //  loans     → pagos, intereses, amortizaciones, imágenes, contratos(+adjuntos), colaterales
                //  sales     → cuotas, imágenes, autorizaciones de eliminación
                //  purchases → imágenes
                // Se usa DB::table()->delete() (raw) para borrar también los
                // soft-deleted → los correlativos (numero) vuelven a 1.
                DB::table('loans')->where('branch_id', $branch->id)->delete();
                DB::table('sales')->where('branch_id', $branch->id)->delete();
                DB::table('purchases')->where('branch_id', $branch->id)->delete();

                // Inventario del almacén (stock a 0), solo si es exclusivo.
                if ($warehouseId && $warehouseExclusive) {
                    DB::table('inventory_movements')->where('warehouse_id', $warehouseId)->delete(); // cascade: movement_images
                }
            });
        } catch (\Throwable $exception) {
            Log::error('Error al reiniciar sucursal', ['branch_id' => $branch->id, 'message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible reiniciar la sucursal: ' . $exception->getMessage()]);
        }

        Log::warning('Sucursal reiniciada', [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'by_user' => auth()->id(),
        ]);

        $message = 'Sucursal "' . $branch->name . '" reiniciada correctamente.';
        if ($warehouseId && !$warehouseExclusive) {
            $message .= ' El inventario NO se borró porque el almacén es compartido con otra sucursal.';
        }

        return redirect()->route('branches.show', $branch)->with('success', $message);
    }

    protected function authorizeBranch(Branch $branch): void
    {
        if (!auth()->user()->is_super_admin && $branch->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}