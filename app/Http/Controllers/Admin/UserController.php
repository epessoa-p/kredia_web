<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestión de usuarios.
 * - Superadmin: ve y gestiona todos; puede marcar superadmins y elegir empresa.
 * - Resto: solo usuarios de SU empresa activa; los crea siempre en ella y con roles asignables.
 */
class UserController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->is_super_admin) {
            $users = User::paginate(15);
        } else {
            $company = $user->getCurrentCompany();
            $users = $company->users()->paginate(15);
        }

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $authUser = auth()->user();
        $company = $authUser->getCurrentCompany();

        return view('admin.users.create', [
            'roles' => Role::assignableFor($company?->id)->orderBy('name')->get(),
            'companies' => $authUser->is_super_admin ? Company::orderBy('name')->get() : collect([$company])->filter(),
            'currentCompanyId' => $company?->id,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $authUser = auth()->user();

        $companyId = $authUser->is_super_admin
            ? ((int) $request->input('company_id') ?: null)
            : (int) $authUser->getCurrentCompany()?->id;

        $roleId = (int) $request->input('role_id') ?: null;

        if ($companyId && $roleId) {
            $role = Role::find($roleId);
            if (!$role || !$role->isVisibleTo($companyId)) {
                return back()->withInput()->withErrors(['role_id' => 'El rol seleccionado no es válido para la empresa.']);
            }
        }

        if (!$authUser->is_super_admin && (!$companyId || !$roleId)) {
            return back()->withInput()->withErrors(['role_id' => 'Debes seleccionar el rol del usuario.']);
        }

        if ($companyId && $this->planLimitReached($companyId, 'users')) {
            return back()->withInput()->withErrors(['error' => 'Alcanzaste el límite de usuarios de tu plan. Contacta a tu proveedor para ampliarlo.']);
        }

        try {
            DB::transaction(function () use ($request, $authUser, $companyId, $roleId) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => $request->password,
                    'phone' => $request->phone,
                ]);

                if ($authUser->is_super_admin && $request->boolean('is_super_admin')) {
                    $user->forceFill(['is_super_admin' => true])->save();
                }

                if ($companyId && $roleId) {
                    $user->companies()->attach($companyId, ['role_id' => $roleId]);
                }
            });

            return redirect()->route('users.index')->with('success', 'Usuario creado exitosamente');
        } catch (\Throwable $exception) {
            Log::error('Error al crear usuario', [
                'user' => $request->only('name', 'email'),
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible crear el usuario.']);
        }
    }

    public function show(User $user)
    {
        $this->authorizeTarget($user);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorizeTarget($user);

        $authUser = auth()->user();
        $company = $authUser->getCurrentCompany();

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::assignableFor($company?->id)->orderBy('name')->get(),
            'companies' => $authUser->is_super_admin ? Company::orderBy('name')->get() : collect([$company])->filter(),
        ]);
    }

    public function update(StoreUserRequest $request, User $user)
    {
        $this->authorizeTarget($user);

        $authUser = auth()->user();

        try {
            $data = $request->safe()->except(['is_super_admin', 'role_id', 'company_id']);

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);

            if ($authUser->is_super_admin && $request->has('is_super_admin') && $user->id !== $authUser->id) {
                $user->forceFill(['is_super_admin' => $request->boolean('is_super_admin')])->save();
            }

            return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente');
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar usuario', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el usuario.']);
        }
    }

    public function destroy(User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->user()->id) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario']);
        }

        $authUser = auth()->user();

        // Un admin de empresa no borra la cuenta global: solo la quita de SU empresa
        // (el usuario puede pertenecer a otras empresas).
        if (!$authUser->is_super_admin) {
            $user->companies()->detach($authUser->getCurrentCompany()->id);

            return redirect()->route('users.index')->with('success', 'Usuario quitado de la empresa');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado exitosamente');
    }

    /**
     * Asignar rol a usuario en una empresa
     */
    public function assignRole(User $user, Company $company, Role $role)
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && $authUser->getCurrentCompany()?->id !== $company->id) {
            abort(403);
        }

        if (!$role->isVisibleTo($company->id)) {
            abort(403, 'Rol no válido para la empresa.');
        }

        $user->companies()->syncWithoutDetaching([$company->id => ['role_id' => $role->id]]);

        return back()->with('success', 'Rol asignado exitosamente');
    }

    /**
     * Un usuario que no es superadmin solo puede gestionar usuarios de su empresa activa,
     * y nunca a un superadmin.
     */
    protected function authorizeTarget(User $target): void
    {
        $authUser = auth()->user();

        if ($authUser->is_super_admin) {
            return;
        }

        if ($target->is_super_admin || !$target->belongsToCompanyId($authUser->getCurrentCompany()?->id)) {
            abort(403);
        }
    }
}
