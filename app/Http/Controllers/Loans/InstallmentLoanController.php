<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\CreditCategoryRule;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Personal;
use App\Services\Installments\InstallmentLoanService;
use App\Services\Installments\ScheduleCalculator;
use App\Services\LoanActionException;
use App\Services\LoanPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Préstamos en CUOTAS (web). Misma lógica que la API: InstallmentLoanService. */
class InstallmentLoanController extends Controller
{
    public function __construct(private InstallmentLoanService $service, private LoanPaymentService $payments)
    {
    }

    public function create(Request $request)
    {
        $company = auth()->user()->getCurrentCompany();

        return view('loans.installments.create', [
            'clients' => Client::where('active', true)->orderBy('name')->get(['id', 'name', 'id_number']),
            'branches' => Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
            'collectors' => Personal::where('active', true)->orderBy('full_name')->get(['id', 'full_name']),
            'rules' => CreditCategoryRule::with('creditCategory:id,name')->where('active', true)
                ->where('loan_kind', 'installment')->get(),
            'methods' => ScheduleCalculator::METHODS,
            'frequencies' => ScheduleCalculator::FREQUENCIES,
            'maxRate' => $company?->max_interest_rate,
            'selectedClientId' => $request->integer('client_id') ?: null,
            'cashError' => $company ? $this->payments->getCashSessionError(auth()->id(), $company->id) : null,
        ]);
    }

    /** Vista previa del plan de pagos (AJAX desde el formulario). */
    public function preview(Request $request)
    {
        $v = $request->validate($this->scheduleRules());

        try {
            $rows = ScheduleCalculator::build((float) $v['amount'], (float) $v['interest_rate'], $v['installment_method'],
                $v['payment_frequency'], (int) $v['installments_count'], now()->parse($v['start_date'] ?? today()), $request->boolean('skip_sundays'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'installments' => $rows,
            'total' => round(array_sum(array_column($rows, 'amount')), 2),
            'interest' => round(array_sum(array_column($rows, 'interest')), 2),
        ]);
    }

    public function store(Request $request)
    {
        $company = auth()->user()->getCurrentCompany();
        abort_unless($company, 403);

        if ($limitError = $this->planLimitError($company->id, ['loans'])) {
            return back()->withInput()->withErrors(['error' => $limitError]);
        }

        $v = $request->validate($this->scheduleRules() + [
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $company->id)],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'credit_category_rule_id' => ['nullable', Rule::exists('credit_category_rules', 'id')->where('company_id', $company->id)],
            'collector_id' => ['nullable', Rule::exists('personals', 'id')->where('company_id', $company->id)],
            'notes' => 'nullable|string|max:1000',
            'guarantors' => 'nullable|array|max:5',
            'guarantors.*.name' => 'nullable|string|max:255',
            'guarantors.*.id_number' => 'nullable|string|max:50',
            'guarantors.*.phone' => 'nullable|string|max:30',
            'guarantors.*.address' => 'nullable|string|max:255',
            'guarantors.*.relationship' => 'nullable|string|max:100',
        ], [
            'client_id.required' => 'Selecciona el cliente.',
            'branch_id.required' => 'Selecciona la sucursal.',
        ]);
        $v['skip_sundays'] = $request->boolean('skip_sundays');

        if (!empty($v['credit_category_rule_id'])) {
            $v['credit_category_id'] = CreditCategoryRule::find($v['credit_category_rule_id'])?->credit_category_id;
        }

        try {
            $loan = $this->service->create($company->id, $v, auth()->id());
        } catch (LoanActionException|\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('loans.installments.show', $loan)->with('success', 'Préstamo ' . $loan->code . ' registrado.');
    }

    public function show(Loan $loan)
    {
        abort_unless($loan->isInstallment(), 404);
        $this->service->refreshOverdue($loan);
        $loan->refresh()->load(['installments', 'guarantors', 'client', 'branch', 'collector', 'parentLoan', 'refinancedBy',
            'payments' => fn ($q) => $q->latest('id')->with('allocations.installment')]);

        $user = auth()->user();
        $company = $user->getCurrentCompany();

        return view('loans.installments.show', [
            'loan' => $loan,
            'summary' => $this->service->summary($loan),
            'lockedReason' => $this->payments->getActionsLockedReason($loan),
            'cashError' => $company ? $this->payments->getCashSessionError($user->id, $company->id) : null,
            'methods' => ScheduleCalculator::METHODS,
            'frequencies' => ScheduleCalculator::FREQUENCIES,
            'can' => [
                'pay' => $user->hasPermissionInCompany('payments.create', $company),
                'void' => $user->hasPermissionInCompany('payments.void', $company),
                'refinance' => $user->hasPermissionInCompany('loans.refinance', $company),
            ],
        ]);
    }

    public function pay(Request $request, Loan $loan)
    {
        abort_unless($loan->isInstallment(), 404);

        $v = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'request_id' => 'nullable|string|max:64',
        ], ['amount.required' => 'Ingresa el monto.', 'amount.min' => 'El monto debe ser mayor a 0.']);

        try {
            $this->service->pay($loan, $v, auth()->id());
        } catch (LoanActionException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Cobro registrado.');
    }

    public function void(Request $request, Loan $loan, LoanPayment $payment)
    {
        abort_unless($payment->loan_id === $loan->id, 404);

        $v = $request->validate(['reason' => 'required|string|min:5|max:500'], [
            'reason.required' => 'Indica el motivo de la anulación.',
            'reason.min' => 'Describe el motivo con al menos 5 caracteres.',
        ]);

        try {
            $this->service->void($payment, $v['reason'], auth()->id());
        } catch (LoanActionException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Cobro anulado.');
    }

    public function refinance(Request $request, Loan $loan)
    {
        abort_unless($loan->isInstallment(), 404);

        $v = $request->validate([
            'extra_amount' => 'nullable|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'installment_method' => ['required', Rule::in(array_keys(ScheduleCalculator::METHODS))],
            'payment_frequency' => ['required', Rule::in(array_keys(ScheduleCalculator::FREQUENCIES))],
            'installments_count' => 'required|integer|min:1|max:730',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $new = $this->service->refinance($loan, $v, auth()->id());
        } catch (LoanActionException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('loans.installments.show', $new)->with('success', $loan->code . ' refinanciado en ' . $new->code . '.');
    }

    private function scheduleRules(): array
    {
        return [
            'amount' => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'installment_method' => ['required', Rule::in(array_keys(ScheduleCalculator::METHODS))],
            'payment_frequency' => ['required', Rule::in(array_keys(ScheduleCalculator::FREQUENCIES))],
            'installments_count' => 'required|integer|min:1|max:730',
            'start_date' => 'nullable|date',
            'skip_sundays' => 'nullable|boolean',
        ];
    }
}
