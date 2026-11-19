<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportService;
use App\Services\Reports\ReportTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reportes para la app (permiso reports.view, como la web). Mismas consultas y totales que la web
 * (ReportService) y la misma exportación (ReportExporter), con la empresa del token.
 */
class ReportController extends Controller
{
    private const REPORTS = ['loans', 'payments', 'movements', 'closures', 'clients', 'warehouses'];

    public function __construct(private ReportService $reports, private ReportExporter $exporter)
    {
    }

    /** GET /reports/summary — tarjetas del centro de reportes. */
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'summary' => $this->reports->summary($this->company($request)),
            'reports' => collect(ReportExporter::REPORTS)->map(fn ($r, $key) => ['key' => $key, 'title' => $r[0]])->values(),
        ]);
    }

    /** GET /reports/options — valores para los filtros. */
    public function options(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'branches' => $this->reports->branches($company)->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'cash_registers' => $this->reports->cashRegisters($company)->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'payment_methods' => $this->reports->paymentMethods($company)->values(),
            'loan_statuses' => collect(Loan::STATUS_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'client_statuses' => [
                ['key' => 'active', 'label' => 'Activos'],
                ['key' => 'inactive', 'label' => 'Inactivos'],
                ['key' => 'with_loans', 'label' => 'Con préstamos'],
                ['key' => 'overdue', 'label' => 'Con mora'],
            ],
            'movement_categories' => collect(['loan_disbursement', 'interest_payment', 'penalty_payment', 'amortization',
                'liquidation', 'purchase', 'sale', 'sale_installment', 'sale_down_payment', 'sale_liquidation', 'sale_return',
                'service_expense', 'staff_payment', 'operating_expense', 'adjustment', 'cash_deposit', 'loan_deletion',
                'purchase_deletion'])
                ->map(fn ($c) => ['key' => $c, 'label' => (new CashMovement(['category' => $c]))->category_label])->values(),
        ]);
    }

    /** GET /reports/{report} — totales y una página de filas. */
    public function show(Request $request, string $report): JsonResponse
    {
        $this->validReport($report);
        $company = $this->company($request);
        $f = $request->only(ReportService::FILTERS);
        $perPage = max(1, min(100, $request->integer('per_page', 20)));

        [$totals, $page, $map] = match ($report) {
            'loans' => [
                $this->reports->loansTotals($q = $this->reports->loansQuery($company, $f)), $q->paginate($perPage),
                fn ($l) => [
                    'id' => $l->id,
                    'code' => 'PR-' . str_pad((string) $l->numero, 5, '0', STR_PAD_LEFT),
                    'client' => $l->client?->name,
                    'product' => $l->product?->name,
                    'category' => $l->creditCategory?->name,
                    'branch' => $l->branch?->name,
                    'amount' => (float) $l->amount,
                    'paid' => (float) $l->total_paid,
                    'pending' => ReportService::loanPending($l),
                    'interest_rate' => (float) $l->interest_rate,
                    'status' => $l->status,
                    'status_label' => Loan::STATUS_LABELS[$l->status] ?? $l->status,
                    'date' => $l->created_at?->toDateString(),
                ],
            ],
            'payments' => [
                $this->reports->paymentsTotals($q = $this->reports->paymentsQuery($company, $f)), $q->paginate($perPage),
                fn ($p) => [
                    'id' => $p->id,
                    'date' => $p->payment_date?->toDateString(),
                    'loan_id' => $p->loan_id,
                    'loan_code' => $p->loan ? 'PR-' . str_pad((string) $p->loan->numero, 5, '0', STR_PAD_LEFT) : null,
                    'client' => $p->loan?->client?->name,
                    'type' => $p->payment_type,
                    'type_label' => LoanPayment::typeLabel($p->payment_type),
                    'method_label' => LoanPayment::methodLabel($p->payment_method),
                    'method' => $p->payment_method,
                    'reference' => $p->reference,
                    'amount' => (float) $p->amount,
                    'capital' => (float) $p->capital,
                    'interest' => (float) $p->interest,
                    'cash_register' => $p->cashRegister?->name,
                    'registered_by' => $p->registeredBy?->name,
                    'branch' => $p->loan?->branch ? ['name' => $p->loan->branch->name, 'color' => $p->loan->branch->color] : null,
                ],
            ],
            'movements' => [
                $this->reports->movementsTotals($q = $this->reports->movementsQuery($company, $f)) + [
                    'by_category' => $this->reports->movementsByCategory($q),
                ],
                $q->paginate($perPage),
                fn ($m) => $this->movementRow($m),
            ],
            'closures' => [
                $this->reports->closuresTotals($q = $this->reports->closuresQuery($company, $f)), $q->paginate($perPage),
                fn ($s) => [
                    'id' => $s->id,
                    'cash_register' => $s->cashRegister?->name,
                    'opened_at' => $s->opened_at?->toIso8601String(),
                    'closed_at' => $s->closed_at?->toIso8601String(),
                    'opened_by' => $s->openedByUser?->name,
                    'closed_by' => $s->closedByUser?->name,
                    'opening_amount' => (float) $s->opening_amount,
                    'closing_amount' => $s->closing_amount !== null ? (float) $s->closing_amount : null,
                    'income' => (float) $s->income_amount,
                    'expense' => (float) $s->expense_amount,
                    'expected' => ReportService::closureExpected($s),
                    'mismatch' => ReportService::closureMismatch($s),
                    'movements_count' => (int) $s->cash_movements_count,
                ],
            ],
            'clients' => [
                $this->reports->clientsTotals($q = $this->reports->clientsQuery($company, $f)), $q->paginate($perPage),
                fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'id_number' => $c->id_number,
                    'phone' => $c->phone,
                    'active' => (bool) $c->active,
                    'loans_count' => (int) $c->loans_count,
                    'active_loans' => (int) $c->active_loans_count,
                    'overdue_loans' => (int) $c->overdue_loans_count,
                    'borrowed' => (float) ($c->total_borrowed ?? 0),
                    'paid' => (float) ($c->total_paid ?? 0),
                    'pending' => max(0, (float) ($c->total_borrowed ?? 0) - (float) ($c->total_paid ?? 0)),
                ],
            ],
            'warehouses' => $this->warehousesPage($company),
        };

        return response()->json([
            'report' => $report,
            'title' => ReportExporter::REPORTS[$report][0],
            'totals' => $totals,
            'data' => $page->getCollection()->map($map)->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * GET /reports/{report}/export?format=pdf|excel&detail=summary|detailed (+ los mismos filtros)
     * Devuelve el archivo (PDF o .xlsx) para descargarlo y compartirlo desde el celular.
     */
    public function export(Request $request, string $report)
    {
        $this->validReport($report);
        $request->validate([
            'format' => 'required|in:pdf,excel',
            'detail' => 'nullable|in:summary,detailed',
        ], [
            'format.required' => 'Elige el formato del archivo.',
            'format.in' => 'El formato debe ser PDF o Excel.',
            'detail.in' => 'El detalle debe ser resumen o detallado.',
        ]);

        $company = $this->company($request);
        $f = $request->only(ReportService::FILTERS);
        $detail = $request->get('detail', 'summary');

        try {
            [$filename, $content] = $request->get('format') === 'excel'
                ? $this->exporter->xlsx($report, $company, $f, $detail)
                : $this->exporter->pdf($report, $company, $f, $detail);
        } catch (ReportTooLargeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'report_too_large'], 422);
        } catch (Throwable $e) {
            Log::error('API: error al exportar reporte', ['report' => $report, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'No fue posible generar el archivo. Intenta con menos datos (filtra por fechas).'], 500);
        }

        $mime = str_ends_with($filename, '.pdf')
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * GET /reports/closures/{session} — detalle de una sesión de caja: resumen del cierre
     * (mismos cálculos que el reporte) y todos sus movimientos.
     */
    public function closure(Request $request, int $session): JsonResponse
    {
        $company = $this->company($request);
        $s = $this->reports->closuresQuery($company, [], true)->find($session);
        abort_unless($s, 404, 'Sesión de caja no encontrada.');

        return response()->json([
            'session' => [
                'id' => $s->id,
                'cash_register' => $s->cashRegister?->name,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
                'opened_by' => $s->openedByUser?->name,
                'closed_by' => $s->closedByUser?->name,
                'opening_amount' => (float) $s->opening_amount,
                'closing_amount' => $s->closing_amount !== null ? (float) $s->closing_amount : null,
                'income' => (float) $s->income_amount,
                'expense' => (float) $s->expense_amount,
                'expected' => ReportService::closureExpected($s),
                'mismatch' => ReportService::closureMismatch($s),
                'opening_notes' => $s->opening_notes,
                'closing_notes' => $s->closing_notes,
                'movements_count' => (int) $s->cash_movements_count,
            ],
            'movements' => $s->cashMovements->map(fn ($m) => $this->movementRow($m))->values(),
        ]);
    }

    /** Fila de movimiento de caja (reporte y detalle de cierre). reference_kind: loan | sale | purchase | null. */
    private function movementRow(CashMovement $m): array
    {
        return [
            'id' => $m->id,
            'date' => $m->movement_date?->toIso8601String(),
            'type' => $m->type,
            'category' => $m->category,
            'category_label' => $m->category_label,
            'description' => $m->description,
            'amount' => (float) $m->amount,
            'cash_register' => $m->cashRegister?->name,
            'user' => $m->user?->name,
            'reference_kind' => match ($m->reference_type) {
                Loan::class => 'loan',
                Sale::class => 'sale',
                Purchase::class => 'purchase',
                default => null,
            },
            'reference_id' => $m->reference_id,
            'reference_label' => $m->reference_label,
        ];
    }

    /** Almacenes no se pagina (son pocos): se arma un paginador de una sola página. */
    private function warehousesPage($company): array
    {
        $warehouses = $this->reports->warehouses($company);
        $page = new \Illuminate\Pagination\LengthAwarePaginator($warehouses, $warehouses->count(), max(1, $warehouses->count()), 1);

        return [
            $this->reports->warehousesTotals($warehouses),
            $page,
            fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'branch' => $w->primaryBranch?->name,
                'active' => (bool) $w->active,
                'retained' => $w->retained_count,
                'stored' => $w->stored_count,
                'sellable' => $w->sellable_count,
                'total' => $w->collateral_count,
            ],
        ];
    }

    private function company(Request $request)
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');
        if (!$user->is_super_admin && !$company) {
            abort(403, 'No tienes empresa activa asignada.');
        }

        return $company;
    }

    private function validReport(string $report): void
    {
        if (!in_array($report, self::REPORTS, true)) {
            abort(404, 'Reporte no encontrado.');
        }
    }
}
