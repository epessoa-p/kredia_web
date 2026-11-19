<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\LoanPayment;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas y totales de los reportes. Los usan la web (Reports\ReportController y ExportController) y la API
 * (Api\ReportController), para que la pantalla, la exportación y la app den los mismos números.
 *
 * $company = null significa "todas las empresas" (solo el super admin sin empresa elegida); si no, se filtra por
 * esa empresa. $f son los filtros tal como llegan en la petición.
 */
class ReportService
{
    /** Filtros aceptados (web y API usan los mismos nombres). */
    public const FILTERS = ['q', 'branch_id', 'status', 'product_id', 'date_from', 'date_to', 'payment_method',
        'cash_register_id', 'type', 'category'];

    /* ── Resumen ─────────────────────────────────────────────── */

    public function summary(?Company $company): array
    {
        $loans = Loan::query()->when($company, fn ($q) => $q->where('company_id', $company->id));
        $clients = Client::query()->when($company, fn ($q) => $q->where('company_id', $company->id));

        return [
            'total_loans' => (clone $loans)->count(),
            'active_loans' => (clone $loans)->whereIn('status', Loan::OPEN_STATUSES)->count(),
            'total_clients' => $clients->count(),
            'total_disbursed' => (float) (clone $loans)->where('status', '!=', Loan::STATUS_CANCELLED)->sum('amount'),
            'total_collected' => (float) (clone $loans)->sum('total_paid'),
            'total_pending' => (float) ((clone $loans)->whereIn('status', Loan::OPEN_STATUSES)
                ->selectRaw('SUM(total_to_pay - total_paid) as pending')->value('pending') ?? 0),
            'overdue_loans' => (clone $loans)->where('status', Loan::STATUS_OVERDUE)->count(),
            'defaulted_loans' => (clone $loans)->where('status', Loan::STATUS_DEFAULTED)->count(),
        ];
    }

    /* ── Préstamos ───────────────────────────────────────────── */

    public function loansQuery(?Company $company, array $f): Builder
    {
        $query = Loan::with('client', 'product', 'branch', 'creditCategory')->latest()
            ->when($company, fn ($q) => $q->where('company_id', $company->id));

        if ($this->filled($f, 'branch_id')) {
            $query->where('branch_id', (int) $f['branch_id']);
        }
        if ($this->filled($f, 'status') && ($status = Loan::normalizeStatus((string) $f['status']))) {
            $query->where('status', $status);
        }
        if ($this->filled($f, 'product_id')) {
            $query->where('product_id', (int) $f['product_id']);
        }
        $this->dateRange($query, $f, 'created_at');
        if ($this->filled($f, 'q')) {
            $q = trim((string) $f['q']);
            $numero = (int) preg_replace('/\D/', '', $q);
            $query->where(function ($sub) use ($q, $numero) {
                $sub->whereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%"));
                if ($numero > 0) {
                    $sub->orWhere('numero', $numero); // "PR-00012" o "12"
                }
            });
        }

        return $query;
    }

    /** Pendiente = lo de self::loanPending() sumado (solo préstamos abiertos). */
    public function loansTotals(Builder $query): array
    {
        $open = "'" . implode("','", Loan::OPEN_STATUSES) . "'";
        $row = (clone $query)->reorder()->toBase()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as amount, COALESCE(SUM(total_paid),0) as paid,'
                . " COALESCE(SUM(CASE WHEN status IN ($open) THEN GREATEST(total_to_pay - total_paid, 0) ELSE 0 END),0) as pending")
            ->first();

        return [
            'count' => (int) $row->n,
            'amount' => (float) $row->amount,
            'paid' => (float) $row->paid,
            'pending' => (float) $row->pending,
        ];
    }

    /**
     * Lo que falta cobrar: total a pagar − pagado, solo en préstamos abiertos (activo, en mora, incumplido),
     * como la tarjeta "Por cobrar" del resumen. Un préstamo finalizado o cancelado no tiene pendiente.
     */
    public static function loanPending(Loan $loan): float
    {
        if (!in_array($loan->status, Loan::OPEN_STATUSES, true)) {
            return 0.0;
        }

        return max(0, (float) $loan->total_to_pay - (float) $loan->total_paid);
    }

    /* ── Pagos de préstamos ──────────────────────────────────── */

    public function paymentsQuery(?Company $company, array $f): Builder
    {
        $query = LoanPayment::with(['loan.client', 'loan.product', 'loan.branch', 'registeredBy', 'cashRegister', 'cashRegisterSession'])
            ->latest('payment_date')->latest('id')
            ->when($company, fn ($q) => $q->whereHas('loan', fn ($l) => $l->where('company_id', $company->id)));

        $this->dateRange($query, $f, 'payment_date');
        if ($this->filled($f, 'payment_method')) {
            $query->where('payment_method', $f['payment_method']);
        }
        if ($this->filled($f, 'q')) {
            $search = trim((string) $f['q']);
            $query->where(function ($sub) use ($search) {
                $sub->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('loan.client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function paymentsTotals(Builder $query): array
    {
        $row = (clone $query)->reorder()->toBase()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as amount, COALESCE(SUM(capital),0) as capital,'
                . ' COALESCE(SUM(interest),0) as interest')
            ->first();

        return [
            'count' => (int) $row->n,
            'amount' => (float) $row->amount,
            'capital' => (float) $row->capital,
            'interest' => (float) $row->interest,
        ];
    }

    public function paymentMethods(?Company $company): Collection
    {
        return LoanPayment::query()
            ->when($company, fn ($q) => $q->whereHas('loan', fn ($l) => $l->where('company_id', $company->id)))
            ->whereNotNull('payment_method')
            ->distinct()->orderBy('payment_method')->pluck('payment_method');
    }

    /* ── Movimientos de caja ─────────────────────────────────── */

    public function movementsQuery(?Company $company, array $f): Builder
    {
        $query = CashMovement::query()
            ->with(['cashRegister', 'cashRegisterSession', 'user', 'reference'])
            ->latest('movement_date')->latest('id')
            ->when($company, fn ($q) => $q->where('company_id', $company->id));

        if ((int) ($f['branch_id'] ?? 0) > 0) {
            $query->whereHas('cashRegister', fn ($q) => $q->where('branch_id', (int) $f['branch_id']));
        }
        $this->dateRange($query, $f, 'movement_date');
        if ($this->filled($f, 'cash_register_id')) {
            $query->where('cash_register_id', (int) $f['cash_register_id']);
        }
        if (in_array($f['type'] ?? null, ['income', 'expense'], true)) {
            $query->where('type', $f['type']);
        }
        if ($this->filled($f, 'category')) {
            $query->where('category', $f['category']);
        }
        if ($this->filled($f, 'q')) {
            $search = trim((string) $f['q']);
            $query->where(function ($sub) use ($search) {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhere('reference_id', $search)
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Los aportes a caja mueven efectivo pero no son ganancia: quedan fuera de los ingresos y egresos
     * operativos y, por tanto, de la utilidad.
     */
    public function movementsTotals(Builder $query): array
    {
        $base = (clone $query)->reorder();
        $aportes = (float) (clone $base)->where('type', 'income')
            ->whereIn('category', CashMovement::NON_OPERATIONAL_CATEGORIES)->sum('amount');
        $ingresos = (float) (clone $base)->where('type', 'income')
            ->whereNotIn('category', CashMovement::NON_OPERATIONAL_CATEGORIES)->sum('amount');
        $egresos = (float) (clone $base)->where('type', 'expense')
            ->whereNotIn('category', CashMovement::NON_OPERATIONAL_CATEGORIES)->sum('amount');

        return [
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'utilidad' => $ingresos - $egresos,
            'aportes' => $aportes,
            'transacciones' => (clone $base)->count(),
        ];
    }

    /** Ingresos, egresos y neto por categoría (resumen del reporte de movimientos). */
    public function movementsByCategory(Builder $query): Collection
    {
        $rows = (clone $query)->reorder()->toBase()
            ->selectRaw("category, COUNT(*) as n,"
                . " COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END),0) as income,"
                . " COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END),0) as expense")
            ->groupBy('category')->orderBy('category')->get();

        return $rows->map(fn ($r) => [
            'category' => $r->category,
            'label' => (new CashMovement(['category' => $r->category]))->category_label,
            'count' => (int) $r->n,
            'income' => (float) $r->income,
            'expense' => (float) $r->expense,
            'net' => (float) $r->income - (float) $r->expense,
        ])->values();
    }

    /* ── Cierres de caja ─────────────────────────────────────── */

    public function closuresQuery(?Company $company, array $f, bool $withMovements = false): Builder
    {
        $with = ['cashRegister.company', 'openedByUser', 'closedByUser'];
        if ($withMovements) {
            $with['cashMovements'] = fn ($q) => $q->with(['user', 'reference'])->latest('movement_date')->latest('id');
        }

        $query = CashRegisterSession::query()
            ->with($with)
            ->withCount('cashMovements')
            ->withSum(['cashMovements as income_amount' => fn ($q) => $q->where('type', 'income')], 'amount')
            ->withSum(['cashMovements as expense_amount' => fn ($q) => $q->where('type', 'expense')], 'amount')
            // Aportes a caja (no operativos). income_amount los incluye: es el bruto del cierre esperado.
            ->withSum(['cashMovements as deposit_amount' => fn ($q) => $q->where('type', 'income')
                ->whereIn('category', CashMovement::NON_OPERATIONAL_CATEGORIES)], 'amount')
            ->withCount(['cashMovements as loan_deletion_count' => fn ($q) => $q->where('category', 'loan_deletion')])
            ->latest('opened_at')
            ->when($company, fn ($q) => $q->whereHas('cashRegister', fn ($r) => $r->where('company_id', $company->id)));

        if ((int) ($f['branch_id'] ?? 0) > 0) {
            $query->whereHas('cashRegister', fn ($q) => $q->where('branch_id', (int) $f['branch_id']));
        }
        if ($this->filled($f, 'cash_register_id')) {
            $query->where('cash_register_id', (int) $f['cash_register_id']);
        }
        if (($f['status'] ?? null) === 'open') {
            $query->whereNull('closed_at');
        } elseif (($f['status'] ?? null) === 'closed') {
            $query->whereNotNull('closed_at');
        }
        $this->dateRange($query, $f, 'opened_at');

        return $query;
    }

    /** Esperado = apertura + ingresos − egresos; descuadre = declarado − esperado (solo sesiones cerradas). */
    public static function closureExpected(CashRegisterSession $s): float
    {
        return (float) $s->opening_amount + (float) $s->income_amount - (float) $s->expense_amount;
    }

    public static function closureMismatch(CashRegisterSession $s): ?float
    {
        if (!$s->closed_at || $s->closing_amount === null) {
            return null;
        }

        return round((float) $s->closing_amount - self::closureExpected($s), 2);
    }

    public function closuresTotals(Builder $query): array
    {
        $base = (clone $query)->reorder();
        $sessions = (clone $base)->without(['cashRegister', 'openedByUser', 'closedByUser', 'cashMovements'])
            ->get(['id', 'opening_amount', 'closing_amount', 'closed_at']);
        $mismatches = $sessions->filter(fn ($s) => abs((float) self::closureMismatch($s)) > 0.01)->count();

        return [
            'sessions' => $sessions->count(),
            'open_sessions' => $sessions->whereNull('closed_at')->count(),
            'closed_sessions' => $sessions->whereNotNull('closed_at')->count(),
            'mismatches' => $mismatches,
        ];
    }

    /* ── Cartera de clientes ─────────────────────────────────── */

    public function clientsQuery(?Company $company, array $f): Builder
    {
        $query = Client::query()
            ->with('company')
            ->withCount('loans')
            ->withSum('loans as total_borrowed', 'amount')
            ->withSum('loans as total_paid', 'total_paid')
            ->withCount(['loans as active_loans_count' => fn ($q) => $q->whereIn('status', Loan::OPEN_STATUSES)])
            ->withCount(['loans as overdue_loans_count' => fn ($q) => $q->whereIn('status', Loan::DELINQUENT_STATUSES)])
            ->latest()
            ->when($company, fn ($q) => $q->where('company_id', $company->id));

        if ($this->filled($f, 'q')) {
            $search = trim((string) $f['q']);
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        match ($f['status'] ?? null) {
            'active' => $query->where('active', true),
            'inactive' => $query->where('active', false),
            'with_loans' => $query->has('loans'),
            'overdue' => $query->whereHas('loans', fn ($q) => $q->whereIn('status', Loan::DELINQUENT_STATUSES)),
            default => null,
        };

        return $query;
    }

    /** Totales de TODOS los clientes filtrados (no solo de la página). */
    public function clientsTotals(Builder $query): array
    {
        $ids = (clone $query)->reorder()->toBase()->select('clients.id');
        $row = DB::table('loans')->whereIn('client_id', $ids)->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as borrowed, COALESCE(SUM(total_paid),0) as paid')
            ->first();

        return [
            'clients' => (clone $query)->reorder()->count(),
            'loans' => (int) $row->n,
            'borrowed' => (float) $row->borrowed,
            'paid' => (float) $row->paid,
            'pending' => max(0, (float) $row->borrowed - (float) $row->paid),
        ];
    }

    /* ── Almacenes ───────────────────────────────────────────── */

    /** Almacenes con la cantidad de prendas en custodia por estado. */
    public function warehouses(?Company $company): Collection
    {
        $warehouses = Warehouse::with(['company', 'primaryBranch'])->latest()
            ->when($company, fn ($q) => $q->where('company_id', $company->id))
            ->get();

        $counts = LoanCollateral::query()
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->whereIn('status', ['retained', 'stored', 'sellable'])
            ->selectRaw('warehouse_id, status, COUNT(*) as n')
            ->groupBy('warehouse_id', 'status')
            ->get()
            ->groupBy('warehouse_id');

        foreach ($warehouses as $wh) {
            $byStatus = ($counts[$wh->id] ?? collect())->pluck('n', 'status');
            $wh->retained_count = (int) ($byStatus['retained'] ?? 0);
            $wh->stored_count = (int) ($byStatus['stored'] ?? 0);
            $wh->sellable_count = (int) ($byStatus['sellable'] ?? 0);
            $wh->collateral_count = $wh->retained_count + $wh->stored_count + $wh->sellable_count;
        }

        return $warehouses;
    }

    public function warehousesTotals(Collection $warehouses): array
    {
        return [
            'warehouses' => $warehouses->count(),
            'active' => $warehouses->where('active', true)->count(),
            'total_collateral' => $warehouses->sum('collateral_count'),
            'total_retained' => $warehouses->sum('retained_count'),
            'total_stored' => $warehouses->sum('stored_count'),
            'total_sellable' => $warehouses->sum('sellable_count'),
        ];
    }

    /* ── Opciones de filtros ─────────────────────────────────── */

    public function branches(?Company $company): Collection
    {
        return Branch::where('active', true)->when($company, fn ($q) => $q->where('company_id', $company->id))
            ->orderBy('name')->get();
    }

    public function products(?Company $company): Collection
    {
        return Product::where('active', true)->when($company, fn ($q) => $q->where('company_id', $company->id))
            ->orderBy('name')->get();
    }

    public function cashRegisters(?Company $company): Collection
    {
        return CashRegister::where('active', true)->when($company, fn ($q) => $q->where('company_id', $company->id))
            ->orderBy('name')->get();
    }

    /* ── Utilidades ──────────────────────────────────────────── */

    private function filled(array $f, string $key): bool
    {
        return isset($f[$key]) && trim((string) $f[$key]) !== '';
    }

    private function dateRange(Builder $query, array $f, string $column): void
    {
        if ($this->filled($f, 'date_from')) {
            $query->whereDate($column, '>=', $f['date_from']);
        }
        if ($this->filled($f, 'date_to')) {
            $query->whereDate($column, '<=', $f['date_to']);
        }
    }
}
