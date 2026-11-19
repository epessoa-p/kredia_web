<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;

/** Catálogo de roles de SISTEMA. Solo superadmin (middleware 'super-admin' en la ruta). */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->paginate(15);
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::all()->groupBy('module');
        return view('admin.roles.create', compact('permissions'));
    }

    public function store()
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create($validated);

        if (request('permissions')) {
            $role->permissions()->attach($validated['permissions']);
        }

        return redirect()->route('roles.index')->with('success', 'Rol creado exitosamente');
    }

    public function show(Role $role)
    {
        $role->load('permissions');
        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all()->groupBy('module');
        $role->load('permissions');
        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Role $role)
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug,' . $role->id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role->update($validated);

        // Sin ningún permiso marcado, el rol queda sin permisos.
        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Rol actualizado exitosamente');
    }

    public function destroy(Role $role)
    {
        // Prevenir eliminar roles que están en uso
        if ($role->users()->count() > 0) {
            return back()->withErrors(['error' => 'No puedes eliminar un rol que está siendo utilizado']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado exitosamente');
    }
}
