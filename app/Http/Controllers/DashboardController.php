<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CompanyCapital;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\LoanInterestPayment;
use App\Models\LoanPayment;
use App\Models\Personal;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user      = auth()->user();
        $company   = $user->getCurrentCompany();
        $companyId = $company?->id;

        $canSeeManager = $user->is_super_admin
            || $user->hasPermissionInCompany('dashboard.manager', $company);

        $branches = Branch::where('active', true)
            ->when(!$user->is_super_admin, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        $branchIds = $branches->pluck('id');

        $selectedManagerBranchId = $request->integer('sucursal_id', 0);
        if ($selectedManagerBranchId && !$branchIds->contains($selectedManagerBranchId)) {
            $selectedManagerBranchId = 0;
        }

        $personal          = Personal::where('user_id', $user->id)->first();
        $isPersonalManager = $personal?->isManager() ?? false;

        if ($isPersonalManager) {
            $selectedWorkerBranchId = $request->integer('c_sid', 0);
            if ($selectedWorkerBranchId && !$branchIds->contains($selectedWorkerBranchId)) {
                $selectedWorkerBranchId = 0;
            }
        } else {
            $selectedWorkerBranchId = $personal?->branch_id ?? 0;
        }

        $managerData = $canSeeManager ? $this->getManagerData($user, $company, $selectedManagerBranchId) : [];
        $workerData  = $this->getWorkerData($user, $company, $selectedWorkerBranchId);

        return view('dashboard.combined', array_merge($managerData, $workerData, [
            'company'                 => $company,
            'showManagerTab'          => $canSeeManager,
            'showWorkerTab'           => true,
            'branches'                => $branches,
            'selectedManagerBranchId' => $selectedManagerBranchId,
            'selectedWorkerBranchId'  => $selectedWorkerBranchId,
            'isPersonalManager'       => $isPersonalManager,
        ]));
    }

    private function getManagerData($user, $company, int $branchId = 0): array
    {
        $companyId = $company?->id;

        if ($user->is_super_admin) {
            if ($branchId > 0) {
                $q              = Loan::where('branch_id', $branchId);
                $totalLoans     = (clone $q)->count();
                $activeLoans    = (clone $q)->where('status', Loan::STATUS_ACTIVE)->count();
                $completedLoans = (clone $q)->where('status', Loan::STATUS_FINISHED)->count();
                $overdueLoans   = (clone $q)->where('status', Loan::STATUS_OVERDUE)->count();
                $totalAmount    = (clone $q)->sum('amount');
                $totalPaid      = (clone $q)->sum('total_paid');
                $recentLoans    = (clone $q)->with('client')->latest()->limit(10)->get();
            } else {
                $totalLoans     = Loan::count();
                $activeLoans    = Loan::where('status', Loan::STATUS_ACTIVE)->count();
                $completedLoans = Loan::where('status', Loan::STATUS_FINISHED)->count();
                $overdueLoans   = Loan::where('status', Loan::STATUS_OVERDUE)->count();
                $totalAmount    = Loan::sum('amount');
                $totalPaid      = Loan::sum('total_paid');
                $recentLoans    = Loan::with('client')->latest()->limit(10)->get();
            }
        } else {
            $q              = Loan::where('company_id', $companyId)
                ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId));
            $totalLoans     = (clone $q)->count();
            $activeLoans    = (clone $q)->where('status', Loan::STATUS_ACTIVE)->count();
            $completedLoans = (clone $q)->where('status', Loan::STATUS_FINISHED)->count();
            $overdueLoans   = (clone $q)->where('status', Loan::STATUS_OVERDUE)->count();
            $totalAmount    = (clone $q)->sum('amount');
            $totalPaid      = (clone $q)->sum('total_paid');
            $recentLoans    = (clone $q)->with('client')->latest()->limit(10)->get();
        }
        $totalPending = $totalAmount - $totalPaid;

        $stockRows = InventoryMovement::query()
            ->join('products', 'products.id', '=', 'inventory_movements.product_id')
            ->when(!$user->is_super_admin, fn($q) => $q->where('inventory_movements.company_id', $companyId))
            ->when($branchId > 0, fn($q) => $q->where('inventory_movements.branch_id', $branchId))
            ->whereNull('inventory_movements.deleted_at')
            ->whereNull('products.deleted_at')
            ->selectRaw("products.cost, products.price,
                SUM(CASE WHEN inventory_movements.type='in' THEN inventory_movements.quantity ELSE -inventory_movements.quantity END) as stock")
            ->groupBy('inventory_movements.product_id', 'products.cost', 'products.price')
            ->get();

        $inventoryValueCost  = $stockRows->filter(fn($r) => $r->stock > 0)->sum(fn($r) => $r->stock * $r->cost);
        $inventoryValuePrice = $stockRows->filter(fn($r) => $r->stock > 0)->sum(fn($r) => $r->stock * $r->price);

        $capitalTotals = CompanyCapital::when(!$user->is_super_admin, fn($q) => $q->where('company_id', $companyId))
            ->selectRaw("SUM(CASE WHEN type='input' THEN amount ELSE 0 END) as total_input,
                         SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) as total_withdrawal")
            ->first();
        $netCapital = (float)($capitalTotals->total_input ?? 0) - (float)($capitalTotals->total_withdrawal ?? 0);

        // Cajas y su saldo actual (apertura + ingresos - egresos de la sesión abierta)
        // Grupos de categorías para el desglose de cada caja abierta.
        $loanCollectionCategories = ['loan_payment', 'interest_payment', 'penalty_payment', 'amortization', 'liquidation'];
        $saleCategories           = ['sale', 'sale_installment', 'sale_down_payment', 'sale_liquidation'];
        $manualExpenseCategories  = ['service_expense', 'staff_payment', 'operating_expense'];

        $cashRegisters = CashRegister::query()
            ->where('active', true)
            ->when(!$user->is_super_admin, fn($q) => $q->where('company_id', $companyId))
            ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
            ->with([
                'branch',
                'assignedPersonal',
                'lastClosedSession.closedByUser',
                'activeSession' => fn($q) => $q
                    ->with('openedByUser')
                    ->withCount('cashMovements as movements_count')
                    ->withSum(['cashMovements as income_amount' => fn($s) => $s->where('type', 'income')], 'amount')
                    ->withSum(['cashMovements as expense_amount' => fn($s) => $s->where('type', 'expense')], 'amount')
                    ->withSum(['cashMovements as disbursed_amount' => fn($s) => $s->where('category', 'loan_disbursement')], 'amount')
                    ->withCount(['cashMovements as disbursed_count' => fn($s) => $s->where('category', 'loan_disbursement')])
                    ->withSum(['cashMovements as loan_collected_amount' => fn($s) => $s->whereIn('category', $loanCollectionCategories)], 'amount')
                    ->withSum(['cashMovements as sales_amount' => fn($s) => $s->whereIn('category', $saleCategories)], 'amount')
                    ->withSum(['cashMovements as manual_expense_amount' => fn($s) => $s->whereIn('category', $manualExpenseCategories)], 'amount'),
            ])
            ->orderBy('name')
            ->get();

        $cashRegisters->each(function (CashRegister $register) {
            $session = $register->activeSession;
            $register->is_open = (bool) $session;
            $register->session_income  = (float) ($session->income_amount ?? 0);
            $register->session_expense = (float) ($session->expense_amount ?? 0);
            $register->session_disbursed      = (float) ($session->disbursed_amount ?? 0);
            $register->session_disbursed_count = (int) ($session->disbursed_count ?? 0);
            $register->session_loan_collected = (float) ($session->loan_collected_amount ?? 0);
            $register->session_sales          = (float) ($session->sales_amount ?? 0);
            $register->session_manual_expense = (float) ($session->manual_expense_amount ?? 0);
            $register->session_movements      = (int) ($session->movements_count ?? 0);
            $register->balance = $session
                ? round((float) $session->opening_amount + $register->session_income - $register->session_expense, 2)
                : 0.0;
        });

        $cashTotalBalance = (float) $cashRegisters->sum('balance');
        $cashOpenCount    = $cashRegisters->where('is_open', true)->count();

        return compact(
            'totalLoans', 'activeLoans', 'completedLoans', 'overdueLoans',
            'totalAmount', 'totalPaid', 'totalPending', 'recentLoans',
            'inventoryValueCost', 'inventoryValuePrice', 'netCapital',
            'cashRegisters', 'cashTotalBalance', 'cashOpenCount'
        );
    }

    private function getWorkerData($user, $company, int $branchId = 0): array
    {
        $companyId = $company?->id;
        $loanQuery = Loan::where('company_id', $companyId)
            ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId));
        $today     = now()->toDateString();
        $in7Days   = now()->addDays(7)->toDateString();
        $in30Days  = now()->addDays(30)->toDateString();

        $ipBase = fn() => LoanInterestPayment::whereHas('loan', fn($q) => $q
            ->where('company_id', $companyId)
            ->when($branchId > 0, fn($q) => $q->where('branch_id', $branchId))
        )->whereIn('status', ['pending', 'partial'])->with('loan.client');

        $todayDue        = $ipBase()->whereDate('period_end', $today)->orderBy('period_end')->limit(20)->get();
        $upcomingDue     = $ipBase()->whereDate('period_end', '>', $today)->whereDate('period_end', '<=', $in7Days)->orderBy('period_end')->limit(30)->get();
        $monthDue        = $ipBase()->whereDate('period_end', '>', $in7Days)->whereDate('period_end', '<=', $in30Days)->orderBy('period_end')->limit(30)->get();
        $overdueInterests= $ipBase()->whereDate('period_end', '<', $today)->orderBy('period_end')->limit(30)->get();

        $overdueLoansCollection = (clone $loanQuery)->whereIn('status', Loan::DELINQUENT_STATUSES)->with('client')->latest()->limit(15)->get();
        $activeCount  = (clone $loanQuery)->where('status', Loan::STATUS_ACTIVE)->count();
        $overdueCount = (clone $loanQuery)->whereIn('status', Loan::DELINQUENT_STATUSES)->count();
        $todayCount   = $todayDue->count();
        $weekCount    = $upcomingDue->count() + $todayCount;

        return compact(
            'todayDue', 'upcomingDue', 'monthDue', 'overdueInterests',
            'overdueLoansCollection', 'activeCount', 'overdueCount', 'todayCount', 'weekCount'
        );
    }
}
