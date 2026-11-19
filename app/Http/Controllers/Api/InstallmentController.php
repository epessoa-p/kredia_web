<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\CreditCategoryRule;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\Personal;
use App\Services\Installments\InstallmentLoanService;
use App\Services\Installments\ScheduleCalculator;
use App\Services\LoanActionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * API de préstamos en CUOTAS y cobranza (app móvil). Misma lógica que la web: InstallmentLoanService.
 * Los datos ya vienen filtrados por la empresa del token (BelongsToCompany).
 */
class InstallmentController extends Controller
{
    public function __construct(private InstallmentLoanService $service)
    {
    }

    /** GET /installment-loans/options — datos para el formulario de alta. */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'methods' => ScheduleCalculator::METHODS,
            'frequencies' => ScheduleCalculator::FREQUENCIES,
            'branches' => Branch::where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'collectors' => Personal::where('active', true)->orderBy('full_name')->get(['id', 'full_name']),
            'rules' => CreditCategoryRule::with('creditCategory:id,name,color')
                ->where('active', true)->where('loan_kind', 'installment')
                ->get(['id', 'credit_category_id', 'name', 'interest_rate', 'installment_method', 'payment_frequency', 'skip_sundays', 'min_amount', 'max_amount']),
            'max_interest_rate' => $request->attributes->get('apiCompany')?->max_interest_rate,
        ]);
    }

    /** POST /installment-loans/preview — plan de pagos sin guardar. */
    public function preview(Request $request): JsonResponse
    {
        $v = $request->validate($this->scheduleRules());

        try {
            $rows = ScheduleCalculator::build((float) $v['amount'], (float) $v['interest_rate'], $v['installment_method'],
                $v['payment_frequency'], (int) $v['installments_count'], now()->parse($v['start_date'] ?? today()), (bool) ($v['skip_sundays'] ?? false));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'installments' => $rows,
            'total' => round(array_sum(array_column($rows, 'amount')), 2),
            'interest' => round(array_sum(array_column($rows, 'interest')), 2),
        ]);
    }

    /** POST /installment-loans — alta con desembolso desde la caja abierta. */
    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        if ($limitError = $this->planLimitError((int) $company->id, ['loans'])) {
            return response()->json(['message' => $limitError, 'code' => 'plan_limit_reached'], 403);
        }

        $v = $request->validate($this->scheduleRules() + [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('company_id', $company->id)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'credit_category_rule_id' => ['nullable', 'integer', Rule::exists('credit_category_rules', 'id')->where('company_id', $company->id)],
            'collector_id' => ['nullable', 'integer', Rule::exists('personals', 'id')->where('company_id', $company->id)],
            'notes' => 'nullable|string|max:1000',
            'guarantors' => 'nullable|array|max:5',
            'guarantors.*.name' => 'required_with:guarantors|string|max:255',
            'guarantors.*.id_number' => 'nullable|string|max:50',
            'guarantors.*.phone' => 'nullable|string|max:30',
            'guarantors.*.address' => 'nullable|string|max:255',
            'guarantors.*.relationship' => 'nullable|string|max:100',
            'request_id' => 'nullable|string|max:64',
        ]);

        if (!empty($v['credit_category_rule_id'])) {
            $v['credit_category_id'] = CreditCategoryRule::find($v['credit_category_rule_id'])?->credit_category_id;
        }

        return $this->run($request, function () use ($company, $v, $request) {
            $loan = $this->service->create((int) $company->id, $v, (int) $request->user()->id);

            return response()->json(['message' => 'Préstamo ' . $loan->code . ' registrado.', 'loan' => $this->loanPayload($loan)], 201);
        });
    }

    /** GET /loans/{loan}/installments — plan de pagos, resumen y garantes. */
    public function show(Loan $loan): JsonResponse
    {
        abort_unless($loan->isInstallment(), 404);
        $this->service->refreshOverdue($loan);

        return response()->json(['loan' => $this->loanPayload($loan->fresh())]);
    }

    /** POST /loans/{loan}/installment-payments — cobro (con GPS opcional). Idempotente por request_id. */
    public function pay(Request $request, Loan $loan): JsonResponse
    {
        abort_unless($loan->isInstallment(), 404);

        $v = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'request_id' => 'nullable|string|max:64',
            'paid_at' => 'nullable|date|before_or_equal:now', // cobro hecho sin conexión
        ], [
            'amount.required' => 'Ingresa el monto.',
            'amount.min' => 'El monto debe ser mayor a 0.',
        ]);

        if (!empty($v['paid_at'])) {
            $v['payment_date'] = now()->parse($v['paid_at'])->toDateString();
        }

        return $this->run($request, function () use ($loan, $v, $request) {
            $payment = $this->service->pay($loan, $v, (int) $request->user()->id);
            $duplicate = (bool) $payment->getAttribute('was_duplicate');

            return response()->json([
                'message' => $duplicate ? 'Este cobro ya estaba registrado.' : 'Cobro registrado.',
                'duplicate' => $duplicate,
                'payment' => $this->paymentPayload($payment),
                'loan' => $this->loanPayload($loan->fresh()),
            ], $duplicate ? 200 : 201);
        }, useCacheLock: false);
    }

    /** POST /loan-payments/{payment}/void — anula un cobro de cuotas (motivo obligatorio). */
    public function void(Request $request, LoanPayment $payment): JsonResponse
    {
        $loan = Loan::find($payment->loan_id);
        abort_unless($loan, 404); // de otra empresa (el préstamo está fuera del filtro)

        $v = $request->validate(['reason' => 'required|string|min:5|max:500'], [
            'reason.required' => 'Indica el motivo de la anulación.',
            'reason.min' => 'Describe el motivo con al menos 5 caracteres.',
        ]);

        return $this->run($request, function () use ($payment, $v, $request, $loan) {
            $this->service->void($payment, $v['reason'], (int) $request->user()->id);

            return response()->json(['message' => 'Cobro anulado.', 'loan' => $this->loanPayload($loan->fresh())]);
        });
    }

    /** POST /loans/{loan}/refinance */
    public function refinance(Request $request, Loan $loan): JsonResponse
    {
        abort_unless($loan->isInstallment(), 404);

        $v = $request->validate([
            'extra_amount' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'installment_method' => ['nullable', Rule::in(array_keys(ScheduleCalculator::METHODS))],
            'payment_frequency' => ['nullable', Rule::in(array_keys(ScheduleCalculator::FREQUENCIES))],
            'installments_count' => 'nullable|integer|min:1|max:730',
            'notes' => 'nullable|string|max:1000',
            'request_id' => 'nullable|string|max:64',
        ]);

        return $this->run($request, function () use ($loan, $v, $request) {
            $new = $this->service->refinance($loan, $v, (int) $request->user()->id);

            return response()->json(['message' => 'Refinanciado en ' . $new->code . '.', 'loan' => $this->loanPayload($new)], 201);
        });
    }

    /**
     * GET /collections/today — cuotas para cobrar hoy (vencen hoy o están atrasadas).
     * El cobrador ve solo su cartera (préstamos asignados o clientes de sus rutas); quien gestiona
     * la cobranza (collections.manage) ve todo y puede filtrar por cobrador.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');
        $personal = Personal::where('user_id', $user->id)->first();
        $canManage = $user->is_super_admin || $user->hasPermissionInCompany('collections.manage', $company);

        $collectorId = $canManage ? ($request->integer('collector_id') ?: null) : $personal?->id;
        if (!$canManage && !$personal) {
            return response()->json(['date' => today()->toDateString(), 'items' => [], 'totals' => ['count' => 0, 'due' => 0]]);
        }

        $items = $this->service->collectionList($collectorId, $request->integer('route_id') ?: null);

        return response()->json([
            'date' => today()->toDateString(),
            'items' => $items,
            'totals' => ['count' => $items->count(), 'due' => round($items->sum('due_today'), 2)],
        ]);
    }

    /* ── Internos ──────────────────────────────────────────────── */

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

    private function run(Request $request, \Closure $action, bool $useCacheLock = true): JsonResponse
    {
        $requestId = trim((string) $request->input('request_id', ''));
        $lockKey = $useCacheLock && $requestId !== '' ? 'api-installment:' . $request->user()->id . ':' . $requestId : null;

        if ($lockKey && !\Cache::add($lockKey, true, now()->addMinutes(10))) {
            return response()->json(['message' => 'Esta operación ya fue enviada. Actualiza para ver el resultado.'], 409);
        }

        try {
            return $action();
        } catch (LoanActionException $e) {
            $lockKey && \Cache::forget($lockKey);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            $lockKey && \Cache::forget($lockKey);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $lockKey && \Cache::forget($lockKey);
            Log::error('API cuotas: error', ['route' => $request->path(), 'message' => $e->getMessage()]);

            return response()->json(['message' => 'No fue posible registrar la operación. Intenta nuevamente.'], 500);
        }
    }

    private function loanPayload(Loan $loan): array
    {
        $loan->loadMissing(['installments', 'guarantors', 'client:id,name,phone', 'collector:id,full_name']);

        return [
            'id' => $loan->id,
            'code' => $loan->code,
            'status' => $loan->status,
            'loan_kind' => $loan->loan_kind,
            'amount' => (float) $loan->amount,
            'interest_rate' => (float) $loan->interest_rate,
            'installment_method' => $loan->installment_method,
            'payment_frequency' => $loan->payment_frequency,
            'installments_count' => $loan->installments_count,
            'installment_amount' => (float) $loan->installment_amount,
            'parent_loan_id' => $loan->parent_loan_id,
            'refinanced_at' => $loan->refinanced_at?->toIso8601String(),
            'client' => $loan->client?->only(['id', 'name', 'phone']),
            'collector' => $loan->collector?->only(['id', 'full_name']),
            'summary' => $this->service->summary($loan),
            'installments' => $loan->installments->map(fn (LoanInstallment $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'due_date' => $i->due_date->toDateString(),
                'capital' => (float) $i->capital,
                'interest' => (float) $i->interest,
                'penalty' => (float) $i->penalty,
                'amount' => (float) $i->amount,
                'pending' => $i->pendingTotal(),
                'status' => $i->status,
                'days_late' => $i->daysLate(),
            ])->values(),
            'guarantors' => $loan->guarantors->map->only(['id', 'name', 'id_number', 'phone', 'address', 'relationship'])->values(),
        ];
    }

    private function paymentPayload(LoanPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'capital' => (float) $payment->capital,
            'interest' => (float) $payment->interest,
            'penalty' => (float) $payment->penalty,
            'payment_date' => $payment->payment_date?->toDateString(),
            'request_id' => $payment->request_id,
            'voided_at' => $payment->voided_at?->toIso8601String(),
        ];
    }
}
