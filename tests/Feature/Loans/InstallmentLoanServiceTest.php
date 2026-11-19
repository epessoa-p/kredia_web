<?php

namespace Tests\Feature\Loans;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Cargo;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditCategory;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Personal;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Installments\InstallmentLoanService;
use App\Services\LoanActionException;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class InstallmentLoanServiceTest extends TestCase
{
    use RefreshDatabase, BuildsTenants;

    private Company $company;
    private User $user;
    private Branch $branch;
    private Client $client;
    private InstallmentLoanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 10:00:00'); // lunes

        $this->company = $this->company($this->plan(['pawn', 'installments', 'collections', 'mobile_app']));
        $role = $this->role('admin', ['loans.view', 'loans.create', 'payments.create', 'payments.void', 'loans.refinance']);
        $this->user = $this->member($this->company, $role);
        $this->actingAs($this->user);
        app(Tenancy::class)->set($this->company->id);

        $warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Central', 'code' => 'A1']);
        $this->branch = Branch::create(['company_id' => $this->company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Matriz']);
        $cargo = Cargo::create(['company_id' => $this->company->id, 'role_id' => $role->id, 'name' => 'Cobrador']);
        $personal = Personal::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'cargo_id' => $cargo->id,
            'user_id' => $this->user->id, 'full_name' => 'Cobrador Uno', 'active' => true,
        ]);
        $register = CashRegister::create([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Caja 1',
            'assigned_personal_id' => $personal->id, 'active' => true,
        ]);
        CashRegisterSession::create([
            'cash_register_id' => $register->id, 'opened_by' => $this->user->id, 'opening_amount' => 5000, 'opened_at' => now(),
        ]);

        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Cliente Cuotas']);
        $this->service = app(InstallmentLoanService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function newLoan(array $overrides = []): Loan
    {
        return $this->service->create($this->company->id, array_merge([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'amount' => 1000,
            'interest_rate' => 10,
            'installment_method' => 'flat',
            'payment_frequency' => 'weekly',
            'installments_count' => 4,
            'guarantors' => [['name' => 'Garante Uno', 'id_number' => '123', 'relationship' => 'Hermano']],
        ], $overrides), $this->user->id);
    }

    public function test_create_builds_schedule_disbursement_and_guarantors(): void
    {
        $loan = $this->newLoan();

        $this->assertSame(Loan::KIND_INSTALLMENT, $loan->loan_kind);
        $this->assertCount(4, $loan->installments);
        $this->assertSame('Garante Uno', $loan->guarantors->first()->name);
        // 10% mensual → semanal 7/30 → 4 semanas: 1000 × 0.10 × 7/30 × 4 = 93.33
        $this->assertSame(1093.33, round((float) $loan->total_to_pay, 2));
        $this->assertDatabaseHas('cash_movements', ['reference_id' => $loan->id, 'type' => 'expense', 'category' => 'loan_disbursement', 'amount' => 1000]);
        $this->assertTrue(ActivityLog::where('subject_id', $loan->id)->where('action', 'created')->exists());
    }

    public function test_payment_applies_oldest_first_interest_before_capital(): void
    {
        $loan = $this->newLoan();
        $first = $loan->installments[0];

        $payment = $this->service->pay($loan, ['amount' => 300, 'latitude' => -17.78, 'longitude' => -63.18], $this->user->id);

        $first->refresh();
        $second = $loan->installments[1]->fresh();
        $this->assertSame(LoanInstallment::STATUS_PAID, $first->status);
        $this->assertSame(LoanInstallment::STATUS_PARTIAL, $second->status);
        // Lo que sobra de la 1.ª cuota va primero al interés de la 2.ª
        $this->assertSame((float) $second->interest, (float) $second->paid_interest);
        $this->assertSame(300.0, (float) $payment->amount);
        $this->assertSame(-17.78, (float) $payment->latitude);
        $this->assertCount(2, $payment->allocations);
        $this->assertDatabaseHas('cash_movements', ['reference_id' => $loan->id, 'type' => 'income', 'amount' => 300]);
    }

    public function test_paying_everything_finishes_the_loan(): void
    {
        $loan = $this->newLoan();
        $this->service->pay($loan, ['amount' => 1093.33], $this->user->id);

        $loan->refresh();
        $this->assertSame(Loan::STATUS_FINISHED, $loan->status);
        $this->assertSame(0.0, (float) $loan->current_capital);
    }

    public function test_overpayment_is_rejected(): void
    {
        $loan = $this->newLoan();

        $this->expectException(LoanActionException::class);
        $this->service->pay($loan, ['amount' => 5000], $this->user->id);
    }

    public function test_same_request_id_does_not_charge_twice(): void
    {
        $loan = $this->newLoan();

        $a = $this->service->pay($loan, ['amount' => 100, 'request_id' => 'abc-1'], $this->user->id);
        $b = $this->service->pay($loan, ['amount' => 100, 'request_id' => 'abc-1'], $this->user->id);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $loan->payments()->count());
        $this->assertSame(1, CashMovement::where('reference_id', $loan->id)->where('type', 'income')->count());
    }

    public function test_void_reverts_installments_totals_and_cash(): void
    {
        $loan = $this->newLoan();
        $payment = $this->service->pay($loan, ['amount' => 1093.33], $this->user->id);

        $this->service->void($payment, 'Error de digitación', $this->user->id);

        $loan->refresh();
        $this->assertNotSame(Loan::STATUS_FINISHED, $loan->status);
        $this->assertSame(1000.0, (float) $loan->current_capital);
        $this->assertSame(0.0, (float) $loan->total_paid);
        $this->assertSame(4, $loan->installments()->where('status', 'pending')->count());
        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertDatabaseHas('cash_movements', ['reference_id' => $loan->id, 'category' => 'loan_payment_void', 'amount' => 1093.33]);

        $this->expectException(LoanActionException::class);
        $this->service->void($payment->fresh(), 'otra vez', $this->user->id);
    }

    public function test_overdue_installments_get_penalty_and_loan_goes_to_mora(): void
    {
        $category = CreditCategory::create([
            'company_id' => $this->company->id, 'name' => 'Diario', 'slug' => 'diario',
            'penalty_grace_days' => 2, 'penalty_fixed_amount' => 15, 'active' => true,
        ]);
        $loan = $this->newLoan(['credit_category_id' => $category->id]);

        Carbon::setTestNow('2026-03-13 09:00:00'); // 1.ª cuota (09/03) con 4 días de atraso
        $this->service->refreshOverdue($loan->fresh());

        $loan->refresh();
        $this->assertSame(Loan::STATUS_OVERDUE, $loan->status);
        $this->assertSame(15.0, (float) $loan->installments()->first()->penalty);
        $this->assertSame(0.0, (float) $loan->installments()->where('number', 2)->first()->penalty);

        // Al pagar lo vencido (cuota + mora) vuelve a ACTIVO.
        $first = $loan->installments()->first();
        $this->service->pay($loan, ['amount' => $first->pendingTotal()], $this->user->id);
        $this->service->refreshOverdue($loan->fresh());
        $this->assertSame(Loan::STATUS_ACTIVE, $loan->fresh()->status);
    }

    public function test_refinance_closes_old_loan_and_only_disburses_new_money(): void
    {
        $loan = $this->newLoan();
        $this->service->pay($loan, ['amount' => 300], $this->user->id);
        $balance = round($loan->installments()->get()->sum(fn ($i) => $i->pendingCapital()), 2);

        $new = $this->service->refinance($loan, ['extra_amount' => 500, 'installments_count' => 6], $this->user->id);

        $this->assertSame($loan->id, $new->parent_loan_id);
        $this->assertSame(round($balance + 500, 2), (float) $new->amount);
        $this->assertSame(Loan::STATUS_FINISHED, $loan->fresh()->status);
        $this->assertNotNull($loan->fresh()->refinanced_at);
        $this->assertDatabaseHas('cash_movements', ['reference_id' => $new->id, 'category' => 'loan_disbursement', 'amount' => 500]);
        $this->assertCount(1, $new->guarantors);
    }

    /* ── API móvil ──────────────────────────────────────────────── */

    public function test_api_creates_pays_idempotently_and_lists_collections(): void
    {
        $this->role('admin', ['loans.view', 'loans.create', 'payments.create', 'collections.view', 'collections.manage']);
        $headers = $this->apiHeaders($this->user, $this->company);

        $created = $this->postJson('/api/v1/installment-loans', [
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'amount' => 600,
            'interest_rate' => 20,
            'installment_method' => 'flat',
            'payment_frequency' => 'daily',
            'installments_count' => 6,
            'skip_sundays' => true,
            'guarantors' => [['name' => 'Aval Dos']],
        ], $headers)->assertCreated()->json('loan');

        $this->assertCount(6, $created['installments']);
        $loanId = $created['id'];

        // Cobro offline reenviado dos veces con el mismo request_id: se registra una sola vez.
        $body = ['amount' => 50, 'request_id' => 'offline-1', 'latitude' => -17.7, 'longitude' => -63.1];
        $this->postJson("/api/v1/loans/{$loanId}/installment-payments", $body, $headers)->assertCreated();
        $this->postJson("/api/v1/loans/{$loanId}/installment-payments", $body, $headers)
            ->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, Loan::find($loanId)->payments()->count());

        // Dos días después hay cuotas vencidas para cobrar.
        Carbon::setTestNow('2026-03-05 08:00:00');
        $this->getJson('/api/v1/collections/today', $headers)
            ->assertOk()
            ->assertJsonPath('totals.count', 1)
            ->assertJsonPath('items.0.loan_id', $loanId);
    }

    public function test_api_cannot_pay_loan_of_another_company(): void
    {
        $loan = $this->newLoan();
        $other = $this->company($this->plan(['pawn', 'installments', 'mobile_app']));
        $intruder = $this->member($other, $this->role('admin', ['payments.create']));

        $this->postJson("/api/v1/loans/{$loan->id}/installment-payments", ['amount' => 10], $this->apiHeaders($intruder, $other))
            ->assertNotFound();
    }

    public function test_company_max_rate_is_enforced(): void
    {
        $this->company->update(['max_interest_rate' => 8]);

        $this->expectException(LoanActionException::class);
        $this->newLoan(['interest_rate' => 10]);
    }
}
