<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

/**
 * Reportes web. Las consultas y totales están en ReportService (los mismos que usan la exportación y la API).
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
        $this->middleware('check-permission:reports.view');
    }

    /** Empresa a filtrar: la del usuario; null = todas (solo el super admin sin empresa). */
    private function company()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        return $user->is_super_admin ? $company : ($company ?? abort(403));
    }

    private function filters(Request $request): array
    {
        return $request->only(ReportService::FILTERS);
    }

    /* ──────────────────────────────────────────────
     |  REPORTS DASHBOARD
     |──────────────────────────────────────────── */
    public function index()
    {
        $stats = $this->reports->summary($this->company());

        return view('reports.index', compact('stats'));
    }

    /* ──────────────────────────────────────────────
     |  LOANS REPORT
     |──────────────────────────────────────────── */
    public function loans(Request $request)
    {
        $company = $this->company();
        $query = $this->reports->loansQuery($company, $this->filters($request));

        return view('reports.loans', [
            'loans' => (clone $query)->paginate(20)->withQueryString(),
            'branches' => $this->reports->branches($company),
            'products' => $this->reports->products($company),
            'totals' => $this->reports->loansTotals($query),
            'filters' => $request->all(),
        ]);
    }

    /* ──────────────────────────────────────────────
     |  PAYMENTS REPORT
     |──────────────────────────────────────────── */
    public function payments(Request $request)
    {
        $company = $this->company();
        $query = $this->reports->paymentsQuery($company, $this->filters($request));

        return view('reports.payments', [
            'payments' => (clone $query)->paginate(20)->withQueryString(),
            'totals' => $this->reports->paymentsTotals($query),
            'methods' => $this->reports->paymentMethods($company),
            'filters' => $request->all(),
        ]);
    }

    /* ──────────────────────────────────────────────
     |  MOVEMENTS MODULE
     |──────────────────────────────────────────── */
    public function movements(Request $request)
    {
        $company = $this->company();
        $f = $this->filters($request);

        $tab = in_array($request->get('tab'), ['transactions', 'closures'], true)
            ? $request->get('tab')
            : 'transactions';

        $movementsQuery = $this->reports->movementsQuery($company, $f);
        $sessionsQuery = $this->reports->closuresQuery($company, $f, true);
        $closures = $this->reports->closuresTotals($sessionsQuery);

        return view('reports.movements', [
            'tab' => $tab,
            'transactions' => (clone $movementsQuery)->paginate(20, ['*'], 'transactions_page')->withQueryString(),
            'sessions' => (clone $sessionsQuery)->paginate(12, ['*'], 'closures_page')->withQueryString(),
            'cashRegisters' => $this->reports->cashRegisters($company),
            'branches' => $this->reports->branches($company),
            'totals' => $this->reports->movementsTotals($movementsQuery) + [
                'sessions' => $closures['sessions'],
                'open_sessions' => $closures['open_sessions'],
                'closed_sessions' => $closures['closed_sessions'],
            ],
            'filters' => $request->all(),
            'selectedBranchId' => (int) ($f['branch_id'] ?? 0),
        ]);
    }

    /* ──────────────────────────────────────────────
     |  CLIENTS REPORT
     |──────────────────────────────────────────── */
    public function clients(Request $request)
    {
        $query = $this->reports->clientsQuery($this->company(), $this->filters($request));

        return view('reports.clients', [
            'clients' => (clone $query)->paginate(20)->withQueryString(),
            'totals' => $this->reports->clientsTotals($query),
            'filters' => $request->all(),
        ]);
    }

    /* ──────────────────────────────────────────────
     |  WAREHOUSES REPORT
     |──────────────────────────────────────────── */
    public function warehouses(Request $request)
    {
        $warehouses = $this->reports->warehouses($this->company());

        return view('reports.warehouses', [
            'warehouses' => $warehouses,
            'totals' => $this->reports->warehousesTotals($warehouses),
            'filters' => $request->all(),
        ]);
    }

    /* ──────────────────────────────────────────────
     |  CASH REGISTER SESSIONS REPORT
     |──────────────────────────────────────────── */
    public function cashRegisterSessions(Request $request)
    {
        $company = $this->company();
        $query = $this->reports->closuresQuery($company, $this->filters($request))
            ->withCount('loanPayments')
            ->withSum('loanPayments as collected_amount', 'amount')
            ->withSum('loanPayments as collected_capital', 'capital')
            ->withSum('loanPayments as collected_interest', 'interest');

        $sessionIds = (clone $query)->reorder()->pluck('id');
        $payments = \App\Models\LoanPayment::whereIn('cash_register_session_id', $sessionIds)
            ->selectRaw('COUNT(*) as total_payments, SUM(amount) as total_amount, SUM(capital) as total_capital, SUM(interest) as total_interest')
            ->first();
        $closures = $this->reports->closuresTotals($query);

        return view('reports.cash-register-sessions', [
            'sessions' => (clone $query)->paginate(20)->withQueryString(),
            'cashRegisters' => $this->reports->cashRegisters($company),
            'totals' => [
                'sessions' => $closures['sessions'],
                'open_sessions' => $closures['open_sessions'],
                'closed_sessions' => $closures['closed_sessions'],
                'payments' => (int) ($payments->total_payments ?? 0),
                'amount' => (float) ($payments->total_amount ?? 0),
                'capital' => (float) ($payments->total_capital ?? 0),
                'interest' => (float) ($payments->total_interest ?? 0),
            ],
            'filters' => $request->all(),
        ]);
    }
}
