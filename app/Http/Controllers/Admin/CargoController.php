<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CargoController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $query = Cargo::with(['company', 'role'])->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        $cargos = $query->paginate(15);

        return view('admin.cargos.index', compact('cargos'));
    }

    public function create()
    {
        $authUser = auth()->user();

        return view('admin.cargos.create', [
            'permissions' => Permission::groupedByModule(),
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id')
            : (int) $authUser->getCurrentCompany()?->id;

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'name' => [
                    'required',
                    'string',
                    'max:150',
                    Rule::unique('cargos', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
                ],
                'description' => ['nullable', 'string', 'max:2000'],
                'active' => ['nullable', 'boolean'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['string', 'exists:permissions,slug'],
            ], self::messages());

            if (!$companyId) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            DB::transaction(function () use ($validated, $companyId, $request) {
                // Cada cargo tiene su propio rol, con el mismo nombre del cargo.
                $role = $this->createRoleFor($validated['name'], $companyId);
                $this->syncPermissions($role, $validated['permissions'] ?? []);

                Cargo::create([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', true),
                ]);
            });

            return redirect()->route('cargos.index')->with('success', 'Cargo creado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear cargo', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear el cargo.']);
        }
    }

    public function edit(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);
        $authUser = auth()->user();

        $cargo->load('role.permissions');

        return view('admin.cargos.edit', [
            'cargo' => $cargo,
            // Otros cargos con el mismo rol: al cambiar los permisos también les afecta (cargos antiguos).
            'sharedCargos' => $cargo->role_id
                ? Cargo::where('role_id', $cargo->role_id)->where('id', '!=', $cargo->id)->orderBy('name')->pluck('name')
                : collect(),
            'permissions' => Permission::groupedByModule(),
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(Request $request, Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id', $cargo->company_id)
            : (int) $cargo->company_id;

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'name' => [
                    'required',
                    'string',
                    'max:150',
                    Rule::unique('cargos', 'name')
                        ->ignore($cargo->id)
                        ->where(fn ($q) => $q->where('company_id', $companyId)),
                ],
                'description' => ['nullable', 'string', 'max:2000'],
                'active' => ['nullable', 'boolean'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['string', 'exists:permissions,slug'],
            ], self::messages());

            DB::transaction(function () use ($validated, $cargo, $companyId, $request) {
                // Se editan los permisos del rol del cargo SOLO si es un rol propio de la empresa.
                // Un rol de sistema (compartido entre empresas) nunca se modifica desde un cargo:
                // se le crea al cargo su propio rol con esos permisos.
                $role = $cargo->role;
                if (!$role || $role->isSystem() || (int) $role->company_id !== $companyId) {
                    $role = $this->createRoleFor($validated['name'], $companyId);
                }
                $this->syncPermissions($role, $validated['permissions'] ?? []);

                // Si el cargo cambió de rol, su personal con usuario pasa al rol nuevo en esta empresa.
                if ((int) $cargo->role_id !== (int) $role->id) {
                    $userIds = $cargo->personals()->whereNotNull('user_id')->pluck('user_id');
                    if ($userIds->isNotEmpty()) {
                        DB::table('company_user')
                            ->where('company_id', $companyId)
                            ->whereIn('user_id', $userIds)
                            ->update(['role_id' => $role->id, 'updated_at' => now()]);
                    }
                }

                $cargo->update([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', false),
                ]);
            });

            return redirect()->route('cargos.index')->with('success', 'Cargo actualizado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar cargo', ['cargo_id' => $cargo->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el cargo.']);
        }
    }

    public function destroy(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        if ($cargo->personals()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un cargo con personal asignado.']);
        }

        $cargo->delete();

        return redirect()->route('cargos.index')->with('success', 'Cargo eliminado exitosamente.');
    }

    /**
     * API: return permissions for a given role (AJAX).
     */
    /** Crea el rol propio de un cargo: mismo nombre que el cargo y slug único. */
    protected function createRoleFor(string $cargoName, int $companyId): Role
    {
        $name = trim($cargoName);
        $baseSlug = Str::of($name)->lower()->ascii()->replace(' ', '_')->replaceMatches('/[^a-z0-9_]/', '')->toString() ?: 'cargo';
        $slug = $baseSlug;
        $counter = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $counter++;
        }

        return Role::create(['name' => $name, 'slug' => $slug, 'company_id' => $companyId]);
    }

    /** Guarda los permisos marcados (sin ninguno marcado, el rol queda sin permisos). */
    protected function syncPermissions(Role $role, array $slugs): void
    {
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id')->all());
    }

    protected static function messages(): array
    {
        return [
            'company_id.exists' => 'La empresa no existe.',
            'name.required' => 'El nombre del cargo es obligatorio.',
            'name.max' => 'El nombre del cargo no puede tener más de 150 caracteres.',
            'name.unique' => 'Ya existe un cargo con ese nombre en la empresa.',
            'description.max' => 'La descripción es demasiado larga.',
            'permissions.array' => 'Los permisos no son válidos.',
            'permissions.*.exists' => 'Uno de los permisos no existe.',
        ];
    }

    public function rolePermissions(Role $role)
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && !$role->isVisibleTo($authUser->getCurrentCompany()?->id)) {
            abort(403);
        }

        return response()->json($role->permissions->pluck('slug'));
    }

    protected function authorizeCargo(Cargo $cargo): void
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && $cargo->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
