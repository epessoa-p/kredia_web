<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class UserManagementSecurityTest extends TestCase
{
    use RefreshDatabase, BuildsTenants;

    public function test_company_admin_cannot_create_a_super_admin(): void
    {
        $company = $this->company();
        $admin = $this->member($company, $this->role('admin', ['users.view', 'users.create']));

        $this->actingInCompany($admin, $company)->post(route('users.store'), [
            'name' => 'hacker',
            'email' => 'hacker@test.local',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'role_id' => $admin->companies()->first()->pivot->role_id,
            'is_super_admin' => 1,
        ])->assertRedirect(route('users.index'));

        $created = User::where('name', 'hacker')->firstOrFail();
        $this->assertFalse($created->is_super_admin);
        $this->assertTrue($created->belongsToCompanyId($company->id));
    }

    public function test_company_admin_cannot_attach_user_to_another_company(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $admin = $this->member($companyA, $this->role('admin', ['users.view', 'users.create']));

        $this->actingInCompany($admin, $companyA)->post(route('users.store'), [
            'name' => 'intruso',
            'email' => 'intruso@test.local',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'role_id' => $admin->companies()->first()->pivot->role_id,
            'company_id' => $companyB->id,
        ]);

        $created = User::where('name', 'intruso')->firstOrFail();
        $this->assertFalse($created->belongsToCompanyId($companyB->id));
        $this->assertTrue($created->belongsToCompanyId($companyA->id));
    }

    public function test_company_admin_cannot_assign_super_admin_role(): void
    {
        $company = $this->company();
        $admin = $this->member($company, $this->role('admin', ['users.view', 'users.create']));
        $superRole = $this->role('super_admin');

        $this->actingInCompany($admin, $company)->post(route('users.store'), [
            'name' => 'escalada',
            'email' => 'escalada@test.local',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'role_id' => $superRole->id,
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['name' => 'escalada']);
    }

    public function test_company_admin_cannot_see_or_edit_users_of_another_company(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $role = $this->role('admin', ['users.view', 'users.edit', 'users.delete']);
        $admin = $this->member($companyA, $role);
        $foreign = $this->member($companyB, $role);

        $this->actingInCompany($admin, $companyA)->get(route('users.show', $foreign))->assertForbidden();
        $this->actingInCompany($admin, $companyA)->get(route('users.edit', $foreign))->assertForbidden();
        $this->actingInCompany($admin, $companyA)->delete(route('users.destroy', $foreign))->assertForbidden();
        $this->assertNotSoftDeleted($foreign);
    }

    public function test_pivot_super_admin_role_does_not_open_platform_admin(): void
    {
        $company = $this->company();
        $user = $this->member($company, $this->role('super_admin', ['companies.view']));

        $this->actingInCompany($user, $company)->get(route('companies.index'))->assertForbidden();
        $this->actingInCompany($user, $company)->get(route('roles.index'))->assertForbidden();
        $this->actingInCompany($user, $company)->get(route('plans.index'))->assertForbidden();
    }

    public function test_real_super_admin_opens_platform_admin(): void
    {
        $this->actingAs($this->superAdmin())->get(route('plans.index'))->assertOk();
    }

    public function test_session_company_must_belong_to_the_user(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $user = $this->member($companyA, $this->role('admin'));

        $this->actingAs($user)->withSession(['current_company_id' => $companyB->id])
            ->get(route('dashboard'));

        $this->assertSame($companyA->id, $user->getCurrentCompany()->id);
    }

    public function test_web_login_is_throttled_after_five_failures(): void
    {
        $company = $this->company();
        $this->member($company, $this->role('admin'), ['name' => 'cajero1']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['login' => 'cajero1', 'password' => 'claveincorrecta'])
                ->assertSessionHasErrors('login');
        }

        $this->post(route('login.store'), ['login' => 'cajero1', 'password' => 'secret-123'])
            ->assertSessionHasErrors('login');
        $this->assertStringStartsWith('Demasiados intentos', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_api_login_is_throttled_after_five_failures(): void
    {
        $company = $this->company();
        $this->member($company, $this->role('admin'), ['name' => 'cobrador1']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'cobrador1', 'password' => 'claveincorrecta'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['login' => 'cobrador1', 'password' => 'secret-123'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_attempts');
    }
}
