<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Empresas (tenants). Solo superadmin (middleware 'super-admin' en la ruta). */
class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with('subscription.plan')->paginate(15);

        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('admin.companies.create', [
            'plans' => Plan::where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Alta de empresa (flujo del superadmin): empresa → suscripción → usuario administrador.
     */
    public function store(StoreCompanyRequest $request)
    {
        $data = $request->validated();

        $extra = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'subscription_status' => ['required', Rule::in(['trial', 'active'])],
            'current_period_end' => ['nullable', 'date', 'after:today'],
            'admin_name' => ['required', 'string', 'max:255', 'regex:/^\S+$/', Rule::unique('users', 'name')],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8'],
        ], [
            'admin_name.regex' => 'El usuario no puede contener espacios.',
            'admin_name.unique' => 'Ese nombre de usuario ya existe.',
            'admin_email.unique' => 'Ese email ya está registrado.',
            'current_period_end.after' => 'El fin del periodo debe ser una fecha futura.',
        ]);

        $data['interest_next_day'] = $request->boolean('interest_next_day');

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('companies/logos', 'public');
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('companies/covers', 'public');
        }

        $company = DB::transaction(function () use ($data, $extra) {
            $company = Company::create($data);
            $plan = Plan::findOrFail($extra['plan_id']);

            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => $extra['subscription_status'],
                'trial_ends_at' => $extra['subscription_status'] === 'trial'
                    ? now()->addDays(max(1, (int) $plan->trial_days))->endOfDay()
                    : null,
                'current_period_end' => $extra['subscription_status'] === 'active' && !empty($extra['current_period_end'])
                    ? Carbon::parse($extra['current_period_end'])->endOfDay()
                    : null,
                'created_by' => auth()->id(),
            ]);

            $admin = User::create([
                'name' => $extra['admin_name'],
                'email' => $extra['admin_email'],
                'password' => $extra['admin_password'],
                'active' => true,
            ]);

            $adminRole = Role::whereNull('company_id')->where('slug', 'admin')->firstOrFail();
            $admin->companies()->attach($company->id, ['role_id' => $adminRole->id, 'active' => true]);

            return $company;
        });

        return redirect()->route('companies.show', $company)->with('success', 'Empresa creada con su suscripción y usuario administrador.');
    }

    public function show(Company $company)
    {
        $company->load('subscription.plan');
        $users = $company->users()->paginate(10);

        $usage = collect(Plan::LIMITS)->keys()->mapWithKeys(fn ($key) => [$key => [
            'usage' => $company->usageFor($key),
            'max' => $company->effectiveLimit($key),
        ]]);

        return view('admin.companies.show', compact('company', 'users', 'usage'));
    }

    public function edit(Company $company)
    {
        return view('admin.companies.edit', compact('company'));
    }

    public function update(StoreCompanyRequest $request, Company $company)
    {
        $data = $request->validated();
        $data['interest_next_day'] = $request->boolean('interest_next_day');

        if ($request->hasFile('logo')) {
            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $request->file('logo')->store('companies/logos', 'public');
        }

        if ($request->hasFile('cover_image')) {
            if ($company->cover_image && Storage::disk('public')->exists($company->cover_image)) {
                Storage::disk('public')->delete($company->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('companies/covers', 'public');
        }

        $company->update($data);

        return redirect()->route('companies.show', $company)->with('success', 'Empresa actualizada exitosamente');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada exitosamente');
    }

    // ── Suscripción de la empresa (plan, estado, fechas y overrides) ─────────

    public function editSubscription(Company $company)
    {
        $subscription = $company->subscription ?? new Subscription([
            'company_id' => $company->id,
            'status' => 'trial',
            'grace_days' => 3,
        ]);

        return view('admin.companies.subscription', [
            'company' => $company,
            'subscription' => $subscription,
            'plans' => Plan::orderBy('sort_order')->get(),
            'usage' => collect(Plan::LIMITS)->keys()->mapWithKeys(fn ($key) => [$key => $company->usageFor($key)]),
        ]);
    }

    public function updateSubscription(Request $request, Company $company)
    {
        $validated = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'status' => ['required', Rule::in(array_keys(Subscription::STATUSES))],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_end' => ['nullable', 'date'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'max_users_override' => ['nullable', 'integer', 'min:0'],
            'max_branches_override' => ['nullable', 'integer', 'min:0'],
            'max_products_override' => ['nullable', 'integer', 'min:0'],
            'max_loans_override' => ['nullable', 'integer', 'min:0'],
            'customize_features' => ['nullable', 'boolean'],
            'features_override' => ['nullable', 'array'],
            'features_override.*' => [Rule::in(array_keys(Plan::MODULES))],
        ]);

        $payload = collect($validated)->except(['customize_features'])->all();
        $payload['trial_ends_at'] = !empty($validated['trial_ends_at']) ? Carbon::parse($validated['trial_ends_at'])->endOfDay() : null;
        $payload['current_period_end'] = !empty($validated['current_period_end']) ? Carbon::parse($validated['current_period_end'])->endOfDay() : null;
        // Sin "personalizar módulos", la empresa hereda los del plan.
        $payload['features_override'] = $request->boolean('customize_features')
            ? array_values($validated['features_override'] ?? [])
            : null;

        Subscription::updateOrCreate(
            ['company_id' => $company->id],
            $payload + ['created_by' => $company->subscription?->created_by ?? auth()->id()]
        );

        return redirect()->route('companies.show', $company)->with('success', 'Suscripción actualizada.');
    }
}
