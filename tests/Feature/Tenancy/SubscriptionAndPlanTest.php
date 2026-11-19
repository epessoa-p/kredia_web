<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class SubscriptionAndPlanTest extends TestCase
{
    use RefreshDatabase, BuildsTenants;

    public function test_expired_subscription_redirects_to_blocked_screen(): void
    {
        $company = $this->company(null, ['current_period_end' => now()->subDays(10), 'grace_days' => 3]);
        $user = $this->member($company, $this->role('admin', ['clients.view']));

        $this->actingInCompany($user, $company)->get(route('clients.index'))
            ->assertRedirect(route('subscription.blocked'));
    }

    public function test_suspended_subscription_is_blocked_even_with_future_dates(): void
    {
        $company = $this->company(null, ['status' => 'suspended', 'current_period_end' => now()->addMonth()]);
        $user = $this->member($company, $this->role('admin', ['clients.view']));

        $this->actingInCompany($user, $company)->get(route('clients.index'))
            ->assertRedirect(route('subscription.blocked'));
    }

    public function test_grace_period_allows_reading_but_not_writing_on_web(): void
    {
        $company = $this->company(null, ['current_period_end' => now()->subDay(), 'grace_days' => 3]);
        $user = $this->member($company, $this->role('admin', ['clients.view', 'clients.create', 'loans.view']));

        $this->actingInCompany($user, $company)->get(route('clients.index'))->assertOk();

        $this->actingInCompany($user, $company)
            ->post(route('clients.store'), ['name' => 'Nuevo'])
            ->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('clients', ['name' => 'NUEVO']);
        $this->assertDatabaseMissing('clients', ['name' => 'Nuevo']);
    }

    public function test_grace_period_is_read_only_on_api(): void
    {
        $company = $this->company(null, ['current_period_end' => now()->subDay(), 'grace_days' => 3]);
        $user = $this->member($company, $this->role('admin', ['clients.view', 'clients.create', 'loans.view']));
        $headers = $this->apiHeaders($user, $company);

        $this->getJson('/api/v1/clients', $headers)->assertOk();
        $this->postJson('/api/v1/clients', ['name' => 'Nuevo'], $headers)
            ->assertStatus(403)
            ->assertJsonPath('code', 'subscription_grace_readonly');
    }

    public function test_api_blocks_inactive_subscription(): void
    {
        $company = $this->company(null, ['status' => 'cancelled']);
        $user = $this->member($company, $this->role('admin', ['clients.view']));

        $this->getJson('/api/v1/clients', $this->apiHeaders($user, $company))
            ->assertStatus(403)
            ->assertJsonPath('code', 'subscription_inactive');
    }

    public function test_module_outside_plan_is_denied_even_with_permission(): void
    {
        $company = $this->company($this->plan(['pawn', 'mobile_app']));
        $user = $this->member($company, $this->role('admin', ['sales.view', 'loans.view']));

        $this->actingInCompany($user, $company)->get(route('sales.index'))->assertForbidden();
        $this->assertFalse($user->hasPermissionInCompany('sales.view', $company));
        $this->assertTrue($user->hasPermissionInCompany('loans.view', $company));
    }

    public function test_features_override_replaces_plan_modules(): void
    {
        $company = $this->company($this->plan(['pawn']), ['features_override' => ['pawn', 'sales']]);

        $this->assertTrue($company->planAllows('sales'));
    }

    public function test_api_without_mobile_app_module_is_rejected(): void
    {
        $company = $this->company($this->plan(['pawn']));
        $user = $this->member($company, $this->role('admin', ['clients.view']));

        $this->getJson('/api/v1/clients', $this->apiHeaders($user, $company))
            ->assertStatus(403)
            ->assertJsonPath('code', 'plan_module_disabled');
    }

    public function test_branch_limit_blocks_creation_and_override_raises_it(): void
    {
        $company = $this->company($this->plan(['pawn'], ['max_branches' => 1]));
        $user = $this->member($company, $this->role('admin', ['branches.view', 'branches.create']));
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Central', 'code' => 'ALM-1']);
        Branch::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Matriz']);

        $payload = ['name' => 'Segunda', 'warehouse_id' => $warehouse->id];

        $this->actingInCompany($user, $company)->post(route('branches.store'), $payload)
            ->assertSessionHasErrors('error');
        $this->assertSame(1, Branch::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $company->subscription->update(['max_branches_override' => 2]);
        $this->assertTrue($company->fresh()->withinLimit('branches'));
    }

    public function test_super_admin_bypasses_subscription_and_plan(): void
    {
        $company = $this->company($this->plan([]), ['status' => 'cancelled']);
        $root = $this->superAdmin();

        $this->actingAs($root)->withSession(['current_company_id' => $company->id])
            ->get(route('sales.index'))
            ->assertOk();
    }

    public function test_me_exposes_plan_and_subscription(): void
    {
        $company = $this->company($this->plan(['pawn', 'mobile_app']));
        $user = $this->member($company, $this->role('admin', ['loans.view']));

        $this->getJson('/api/v1/auth/me', $this->apiHeaders($user, $company))
            ->assertOk()
            ->assertJsonPath('plan.features', ['pawn', 'mobile_app'])
            ->assertJsonPath('subscription.allows_write', true)
            ->assertJsonPath('permissions', ['loans.view']);
    }
}
