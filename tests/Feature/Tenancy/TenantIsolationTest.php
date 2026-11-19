<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase, BuildsTenants;

    public function test_queries_are_filtered_by_the_active_company(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        Client::create(['company_id' => $companyA->id, 'name' => 'Cliente A']);
        Client::create(['company_id' => $companyB->id, 'name' => 'Cliente B']);

        app(Tenancy::class)->set($companyA->id);
        $this->assertSame(['Cliente A'], Client::pluck('name')->all());

        app(Tenancy::class)->set(null);
        $this->assertSame(2, Client::count());
    }

    public function test_creating_fills_company_from_active_tenant(): void
    {
        $company = $this->company();
        app(Tenancy::class)->set($company->id);

        $client = Client::create(['name' => 'Autocompletado']);

        $this->assertSame($company->id, (int) $client->company_id);
    }

    public function test_web_user_cannot_open_client_of_another_company(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $user = $this->member($companyA, $this->role('admin', ['clients.view', 'loans.view']));
        $foreign = Client::create(['company_id' => $companyB->id, 'name' => 'Ajeno']);

        $this->actingInCompany($user, $companyA)
            ->get(route('clients.show', $foreign))
            ->assertNotFound();
    }

    public function test_api_lists_only_clients_of_token_company(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $user = $this->member($companyA, $this->role('admin', ['clients.view']));
        Client::create(['company_id' => $companyA->id, 'name' => 'Propio']);
        Client::create(['company_id' => $companyB->id, 'name' => 'Ajeno']);

        $response = $this->getJson('/api/v1/clients', $this->apiHeaders($user, $companyA))->assertOk();

        $this->assertStringContainsString('Propio', $response->getContent());
        $this->assertStringNotContainsString('Ajeno', $response->getContent());
    }

    public function test_api_rejects_mismatched_company_header(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $user = $this->member($companyA, $this->role('admin', ['clients.view']));

        $this->getJson('/api/v1/clients', $this->apiHeaders($user, $companyA) + ['X-Company-Id' => $companyB->id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'company_mismatch');
    }
}
