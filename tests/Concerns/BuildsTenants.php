<?php

namespace Tests\Concerns;

use App\Models\ApiToken;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

/** Datos mínimos para probar empresas, planes, suscripciones y permisos. */
trait BuildsTenants
{
    protected function permission(string $slug): Permission
    {
        [$module] = explode('.', $slug);

        return Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => $module]);
    }

    protected function role(string $slug, array $permissions = [], ?int $companyId = null): Role
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'company_id' => $companyId]);
        $role->permissions()->sync(collect($permissions)->map(fn ($p) => $this->permission($p)->id)->all());
        Company::flushPermissionCache();

        return $role;
    }

    protected function plan(array $features = ['pawn', 'mobile_app'], array $limits = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plan ' . Str::random(4),
            'slug' => 'plan-' . Str::lower(Str::random(8)),
            'price' => 0,
            'features' => $features,
            'active' => true,
        ], $limits));
    }

    protected function company(?Plan $plan = null, array $subscription = []): Company
    {
        $company = Company::create(['name' => 'Empresa ' . Str::random(5), 'active' => true, 'currency_symbol' => 'Bs']);

        Subscription::create(array_merge([
            'company_id' => $company->id,
            'plan_id' => ($plan ?? $this->plan())->id,
            'status' => 'active',
            'current_period_end' => null,
            'grace_days' => 3,
        ], $subscription));

        return $company->fresh();
    }

    protected function member(Company $company, Role $role, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'u' . Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)) . '@test.local',
            'password' => 'secret-123',
            'active' => true,
        ], $attributes));

        $user->companies()->attach($company->id, ['role_id' => $role->id, 'active' => true]);

        return $user;
    }

    protected function superAdmin(): User
    {
        $user = User::create([
            'name' => 'root' . Str::lower(Str::random(5)),
            'email' => Str::lower(Str::random(8)) . '@root.local',
            'password' => 'secret-123',
            'active' => true,
        ]);
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    /** Sesión web dentro de una empresa. */
    protected function actingInCompany(User $user, Company $company): static
    {
        return $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    }

    /** Token de API ligado a una empresa; devuelve los headers. */
    protected function apiHeaders(User $user, ?Company $company): array
    {
        $plain = Str::random(60);
        ApiToken::create([
            'user_id' => $user->id,
            'company_id' => $company?->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addYear(),
        ]);

        return ['Authorization' => 'Bearer ' . $plain, 'Accept' => 'application/json'];
    }
}
