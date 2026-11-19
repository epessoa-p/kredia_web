<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $query = Personal::with(['cargo.role', 'company', 'user'])->latest();

        if (!$authUser->is_super_admin) {
            $query->where('company_id', $authUser->getCurrentCompany()?->id);
        }

        $personals = $query->paginate(15);

        return view('admin.personal.index', compact('personals'));
    }

    public function create()
    {
        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin ? request()->integer('company_id') : $authUser->getCurrentCompany()?->id;

        $cargos = Cargo::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('active', true)
            ->with('role')
            ->orderBy('name')
            ->get();

        $branches = Branch::where('active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        return view('admin.personal.create', [
            'cargos' => $cargos,
            'branches' => $branches,
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
            $validated = $request->validate(
                $this->personalValidationRules(),
                $this->personalValidationMessages(),
                $this->personalValidationAttributes()
            );

            if (!$companyId) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            // Cada personal crea su usuario de acceso: cuenta para el límite de usuarios.
            if ($limitError = $this->planLimitError($companyId, ['users'])) {
                return back()->withInput()->withErrors(['error' => $limitError]);
            }

            $cargo = Cargo::findOrFail($validated['cargo_id']);
            if ($cargo->company_id !== $companyId) {
                return back()->withInput()->withErrors(['cargo_id' => 'El cargo seleccionado no pertenece a la empresa actual.']);
            }

            DB::transaction(function () use ($validated, $companyId, $cargo, $request) {
                $username = $this->makeUniqueUsername($validated['full_name']);

                $user = User::create([
                    'name' => $username,
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'phone' => $validated['phone'] ?? null,
                    'active' => $request->boolean('active', true),
                ]);

                $user->companies()->syncWithoutDetaching([
                    $companyId => [
                        'role_id' => $cargo->role_id,
                        'active' => true,
                    ],
                ]);

                Personal::create([
                    'company_id' => $companyId,
                    'branch_id' => $validated['branch_id'] ?? null,
                    'cargo_id' => $cargo->id,
                    'user_id' => $user->id,
                    'full_name' => trim($validated['full_name']),
                    'id_number' => $validated['id_number'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'],
                    'address' => $validated['address'] ?? null,
                    'hire_date' => $validated['hire_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'active' => $request->boolean('active', true),
                ]);
            });

            return redirect()->route('personal.index')->with('success', 'Personal creado y usuario generado automáticamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear personal', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear el personal.']);
        }
    }

    public function edit(Personal $personal)
    {
        $this->authorizePersonal($personal);
        $authUser = auth()->user();

        $companyId = $authUser->is_super_admin
            ? $personal->company_id
            : $authUser->getCurrentCompany()?->id;

        $cargos = Cargo::where('company_id', $companyId)
            ->where('active', true)
            ->with('role')
            ->orderBy('name')
            ->get();

        $personal->load('user', 'cargo.role');

        $branches = Branch::where('active', true)
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return view('admin.personal.edit', [
            'personal' => $personal,
            'cargos' => $cargos,
            'branches' => $branches,
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(Request $request, Personal $personal)
    {
        $this->authorizePersonal($personal);

        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id', $personal->company_id)
            : (int) $personal->company_id;

        try {
            $validated = $request->validate(
                $this->personalValidationRules($personal->user_id),
                $this->personalValidationMessages(),
                $this->personalValidationAttributes()
            );

            $cargo = Cargo::findOrFail($validated['cargo_id']);
            if ($cargo->company_id !== $companyId) {
                return back()->withInput()->withErrors(['cargo_id' => 'El cargo seleccionado no pertenece a la empresa actual.']);
            }

            DB::transaction(function () use ($validated, $personal, $cargo, $companyId, $request) {
                $user = $personal->user;

                $userData = [
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'active' => $request->boolean('active', false),
                ];

                if (!empty($validated['password'])) {
                    $userData['password'] = Hash::make($validated['password']);
                }

                $user->update($userData);

                $user->companies()->syncWithoutDetaching([
                    $companyId => [
                        'role_id' => $cargo->role_id,
                        'active' => true,
                    ],
                ]);

                $personal->update([
                    'company_id' => $companyId,
                    'branch_id' => $validated['branch_id'] ?? null,
                    'cargo_id' => $cargo->id,
                    'full_name' => trim($validated['full_name']),
                    'id_number' => $validated['id_number'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'],
                    'address' => $validated['address'] ?? null,
                    'hire_date' => $validated['hire_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'active' => $request->boolean('active', false),
                ]);
            });

            return redirect()->route('personal.index')->with('success', 'Personal actualizado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar personal', ['personal_id' => $personal->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el personal.']);
        }
    }

    public function destroy(Personal $personal)
    {
        $this->authorizePersonal($personal);

        DB::transaction(function () use ($personal) {
            $user = $personal->user;
            $personal->delete();
            if ($user) {
                $user->delete();
            }
        });

        return redirect()->route('personal.index')->with('success', 'Registro de personal eliminado.');
    }

    protected function authorizePersonal(Personal $personal): void
    {
        $authUser = auth()->user();
        if (!$authUser->is_super_admin && $personal->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    protected function makeUniqueUsername(string $fullName): string
    {
        $base = Str::of($fullName)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]/', '')
            ->trim()
            ->replace(' ', '_')
            ->toString();

        if ($base === '') {
            $base = 'usuario';
        }

        $candidate = $base;
        $counter = 1;

        while (User::where('name', $candidate)->exists()) {
            $candidate = $base . '_' . $counter;
            $counter++;
        }

        return $candidate;
    }

    protected function personalValidationRules(?int $ignoreUserId = null): array
    {
        $emailUniqueRule = 'unique:users,email';
        if ($ignoreUserId) {
            $emailUniqueRule .= ',' . $ignoreUserId;
        }

        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'cargo_id' => ['required', 'exists:cargos,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', $emailUniqueRule],
            'address' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'password' => [$ignoreUserId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function personalValidationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto válido.',
            'email' => 'El campo :attribute debe ser un correo válido.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max.string' => 'El campo :attribute no debe superar :max caracteres.',
            'min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
            'unique' => 'El :attribute ya está registrado.',
            'exists' => 'El :attribute seleccionado no es válido.',
            'date' => 'El campo :attribute debe tener un formato de fecha válido.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'validation.min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
            'validation.unique' => 'El :attribute ya está registrado.',
        ];
    }

    protected function personalValidationAttributes(): array
    {
        return [
            'company_id' => 'empresa',
            'branch_id' => 'sucursal',
            'cargo_id' => 'cargo',
            'full_name' => 'nombre completo',
            'id_number' => 'documento',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'address' => 'dirección',
            'hire_date' => 'fecha de ingreso',
            'notes' => 'notas',
            'password' => 'contraseña',
            'active' => 'estado',
        ];
    }
}
