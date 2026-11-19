<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\LoanPayment;
use App\Models\Personal;
use App\Services\Reports\ReportService;
use App\Support\Currency;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de pagos de préstamos para la API.
 *
 * Gestiona el listado de pagos y el registro de nuevos pagos
 * sobre préstamos existentes, con integración de caja registradora.
 */
class PaymentController extends Controller
{
    /**
     * Lista los pagos de préstamos con paginación.
     *
     * Incluye el préstamo con su cliente y producto, el usuario que lo registró,
     * y la caja/sesión asociada. Soporta filtro por préstamo específico, búsqueda
     * en referencia o nombre de cliente, y rango de fechas de pago.
     *
     * Cada fila trae además los datos listos para mostrar (código PR-, sucursal con
     * color, nombre de quien cobró, tipo y método en español) y la respuesta incluye
     * `totals` (cantidad y monto de lo filtrado).
     *
     * @param  Request  $request  Parámetros: loan_id, q, date_from, date_to, branch_id,
     *                            payment_method, payment_type, per_page (1-100, def. 15)
     * @return JsonResponse  200 con colección paginada de pagos.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = LoanPayment::with(['loan.client', 'loan.product', 'loan.branch', 'registeredBy.personal', 'cashRegister', 'cashRegisterSession'])
            ->latest('payment_date')->latest('id');

        if (!$user->is_super_admin) {
            $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('company_id', $company?->id));
        } elseif ($company) {
            $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('company_id', $company->id));
        }

        if ($request->filled('loan_id')) {
            $query->where('loan_id', $request->integer('loan_id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('branch_id', $request->integer('branch_id')));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', strtolower((string) $request->get('payment_method')));
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', strtolower((string) $request->get('payment_type')));
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            // "PR-00012" o "12" buscan también por número de préstamo.
            $numero = preg_match('/^(?:PR-?)?0*(\d+)$/i', $q, $m) ? (int) $m[1] : null;
            $query->where(function ($subQuery) use ($q, $numero) {
                $subQuery->where('reference', 'like', "%{$q}%")
                    ->orWhereHas('loan.client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$q}%"));
                if ($numero !== null) {
                    $subQuery->orWhereHas('loan', fn ($loanQuery) => $loanQuery->where('numero', $numero));
                }
            });
        }

        $dateFrom = $request->get('date_from');
        if (!empty($dateFrom)) {
            $query->whereDate('payment_date', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('payment_date', '<=', $dateTo);
        }

        $totals = (clone $query)->reorder()->toBase()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as amount')
            ->first();

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $payments = $query->paginate($perPage)->withQueryString();
        $payments->getCollection()->transform(fn (LoanPayment $p) => $this->decorate($p));

        return response()->json(array_merge($payments->toArray(), [
            'totals' => ['count' => (int) $totals->n, 'amount' => (float) $totals->amount],
        ]));
    }

    /**
     * Opciones para los filtros de la pantalla de pagos.
     *
     * @return JsonResponse  200 con branches[{id,name,color}], methods[{key,label}], types[{key,label}].
     */
    public function options(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $reports = app(ReportService::class);

        return response()->json([
            'branches' => $reports->branches($company)->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'color' => $b->color])->values(),
            'methods' => $reports->paymentMethods($company)
                ->map(fn ($m) => ['key' => $m, 'label' => LoanPayment::methodLabel($m)])->values(),
            'types' => collect(LoanPayment::TYPE_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    /**
     * Detalle de un pago: préstamo, cliente, sucursal, caja, quién cobró, desglose y comprobante.
     */
    public function show(Request $request, LoanPayment $payment): JsonResponse
    {
        $payment->load(['loan.client', 'loan.product', 'loan.branch', 'registeredBy.personal', 'cashRegister', 'cashRegisterSession']);
        abort_unless($payment->loan, 404, 'El préstamo de este pago ya no existe.');
        $this->authorizeLoan($request, $payment->loan);

        $loan = $payment->loan;

        return response()->json([
            'payment' => $this->decorate($payment) + [
                'notes' => $payment->notes,
                'receipt_url' => $payment->receipt_path ? asset('storage/' . $payment->receipt_path) : null,
                'receipt_is_pdf' => $payment->receipt_path ? str_ends_with(strtolower($payment->receipt_path), '.pdf') : false,
                'cash_register' => $payment->cashRegister?->name,
                'created_at' => $payment->created_at?->toIso8601String(),
                'loan' => [
                    'id' => $loan->id,
                    'code' => $this->loanCode($loan),
                    'status' => $loan->status,
                    'status_label' => Loan::STATUS_LABELS[$loan->status] ?? $loan->status,
                    'amount' => (float) $loan->amount,
                    'product' => $loan->product?->name,
                    'pending' => ReportService::loanPending($loan),
                ],
                'client' => $loan->client ? [
                    'id' => $loan->client->id,
                    'name' => $loan->client->name,
                    'id_number' => $loan->client->id_number,
                    'phone' => $loan->client->phone,
                ] : null,
            ],
        ]);
    }

    /**
     * Recibo del pago en PDF (size: ticket = 80 mm, carta = hoja carta).
     */
    public function receipt(Request $request, LoanPayment $payment)
    {
        $payment->load(['loan.client', 'loan.product', 'loan.branch', 'loan.company', 'registeredBy.personal', 'cashRegister']);
        abort_unless($payment->loan, 404, 'El préstamo de este pago ya no existe.');
        $this->authorizeLoan($request, $payment->loan);

        $ticket = $request->get('size', 'ticket') !== 'carta';
        $loan = $payment->loan;

        $html = view('payments.receipt-pdf', [
            'payment' => $payment,
            'loan' => $loan,
            'company' => $loan->company,
            'ticket' => $ticket,
            'loanCode' => $this->loanCode($loan),
            'typeLabel' => LoanPayment::typeLabel($payment->payment_type),
            'methodLabel' => LoanPayment::methodLabel($payment->payment_method),
            'cashier' => $this->personName($payment->registeredBy),
            'pending' => ReportService::loanPending($loan),
            'money' => fn ($amount) => Currency::format($amount, $loan->company),
        ])->render();

        // Ticket: 80 mm de ancho (226 pt) y alto que alcanza para todo el contenido.
        $pdf = Pdf::loadHTML($html)
            ->setPaper($ticket ? [0, 0, 226, 380] : 'letter')
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download('recibo-' . $this->loanCode($loan) . '-' . $payment->id . '.pdf');
    }

    /** Datos del pago listos para mostrar en la app. */
    protected function decorate(LoanPayment $p): array
    {
        $loan = $p->loan;

        return [
            'id' => $p->id,
            'loan_id' => $p->loan_id,
            'loan_code' => $loan ? $this->loanCode($loan) : null,
            'client_name' => $loan?->client?->name,
            'product_name' => $loan?->product?->name,
            'branch' => $loan?->branch ? [
                'id' => $loan->branch->id,
                'name' => $loan->branch->name,
                'color' => $loan->branch->color,
            ] : null,
            'amount' => (float) $p->amount,
            'capital' => (float) $p->capital,
            'interest' => (float) $p->interest,
            'payment_type' => $p->payment_type,
            'type_label' => LoanPayment::typeLabel($p->payment_type),
            'payment_method' => $p->payment_method,
            'method_label' => LoanPayment::methodLabel($p->payment_method),
            'reference' => $p->reference,
            'payment_date' => $p->payment_date?->toDateString(),
            'registered_at' => $p->created_at?->toIso8601String(),
            'registered_by_name' => $this->personName($p->registeredBy),
            'has_receipt' => !empty($p->receipt_path),
        ];
    }

    protected function loanCode(Loan $loan): string
    {
        return 'PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT);
    }

    protected function personName($user): ?string
    {
        return $user ? ($user->personal?->full_name ?: $user->name) : null;
    }

    /**
     * Registra un pago sobre un préstamo.
     *
     * Soporta tres tipos de pago: interest (solo interés), capital (solo capital)
     * y mixed (ambos). Requiere una caja abierta asignada al usuario. El pago
     * se registra en una transacción atómica: crea el LoanPayment, incrementa
     * total_paid en el préstamo y, si el préstamo queda saldado, lo marca como
     * finalizado y libera el colateral del inventario.
     *
     * @param  Request  $request  Campos: payment_type, amount, capital, interest,
     *                             payment_date, payment_method, reference, notes
     * @param  Loan     $loan
     * @return JsonResponse  201 con payment y estado actualizado del loan,
     *                        o 422 si no hay caja abierta.
     */
    public function store(Request $request, Loan $loan): JsonResponse
    {
        $this->authorizeLoan($request, $loan);

        $validated = $request->validate([
            'payment_type' => 'required|in:interest,capital,mixed',
            'amount' => 'required|numeric|min:0.01',
            'capital' => 'nullable|numeric|min:0',
            'interest' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $amount = (float) $validated['amount'];
        $capital = (float) ($validated['capital'] ?? 0);
        $interest = (float) ($validated['interest'] ?? 0);

        if ($validated['payment_type'] === 'interest') {
            $capital = 0;
            $interest = $amount;
        } elseif ($validated['payment_type'] === 'capital') {
            $capital = $amount;
            $interest = 0;
        } elseif ($capital <= 0 && $interest <= 0) {
            $capital = $amount;
        }

        $registeredBy = (int) $request->user()->id;
        $activeCashSession = $this->resolveOpenCashSession($registeredBy, (int) $loan->company_id);
        if (!$activeCashSession) {
            return response()->json([
                'message' => 'Debes tener una caja abierta para registrar pagos.',
            ], 422);
        }

        DB::transaction(function () use ($loan, $validated, $amount, $capital, $interest, $registeredBy, $activeCashSession) {
            $loan->payments()->create([
                'registered_by' => $registeredBy,
                'cash_register_id' => $activeCashSession->cash_register_id,
                'cash_register_session_id' => $activeCashSession->id,
                'amount' => $amount,
                'payment_type' => $validated['payment_type'],
                'capital' => $capital,
                'interest' => $interest,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $loan->increment('total_paid', $amount);

            if ($loan->fresh()->total_paid >= $loan->total_to_pay) {
                $loan->update(['status' => Loan::STATUS_FINISHED]);
                $this->releaseCollateralFromLoan($loan, $registeredBy);
            }
        });

        $payment = LoanPayment::where('loan_id', $loan->id)
            ->latest('id')
            ->with(['registeredBy', 'cashRegister', 'cashRegisterSession'])
            ->first();

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'payment' => $payment,
            'loan' => [
                'id' => $loan->id,
                'status' => $loan->fresh()->status,
                'total_paid' => (float) $loan->fresh()->total_paid,
                'total_to_pay' => (float) $loan->total_to_pay,
            ],
        ], 201);
    }

    /**
     * Verifica que el usuario tenga acceso al préstamo.
     *
     * Aborta con 403 si el préstamo no pertenece a la empresa del token.
     *
     * @param  Request  $request
     * @param  Loan     $loan
     */
    protected function authorizeLoan(Request $request, Loan $loan): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $loan->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a este préstamo.');
        }

        if ($user->is_super_admin && $company && $loan->company_id !== $company->id) {
            abort(403, 'El préstamo no pertenece a la empresa del token.');
        }
    }

    /**
     * Libera el colateral de inventario cuando un préstamo es pagado completamente.
     *
     * Busca el LoanCollateral en estado 'retained' o 'sellable' y crea un movimiento
     * de salida ('out') en el inventario para reflejar que el artículo fue devuelto
     * al cliente. Actualiza el colateral a estado 'released'.
     *
     * @param  Loan  $loan
     * @param  int   $userId  Usuario que ejecuta la liberación.
     */
    protected function releaseCollateralFromLoan(Loan $loan, int $userId): void
    {
        $collateral = LoanCollateral::where('loan_id', $loan->id)
            ->whereIn('status', ['retained', 'sellable'])
            ->first();

        if (!$collateral) {
            return;
        }

        InventoryMovement::create([
            'company_id' => $loan->company_id,
            'warehouse_id' => $collateral->warehouse_id,
            'branch_id' => $collateral->branch_id,
            'product_id' => $collateral->product_id,
            'user_id' => $userId,
            'type' => 'out',
            'quantity' => 1,
            'unit_cost' => $loan->product?->cost ?? 0,
            'reference' => 'LOAN-REL-' . $loan->id,
            'notes' => 'Salida por liberación de prenda (préstamo pagado)',
            'movement_date' => now(),
        ]);

        $collateral->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    /**
     * Obtiene la sesión de caja abierta del usuario para la empresa indicada.
     *
     * Busca el personal vinculado al usuario, obtiene todas las cajas activas
     * asignadas a ese personal en la empresa, y retorna la sesión abierta más
     * reciente si existe.
     *
     * @param  int  $userId
     * @param  int  $companyId
     * @return \App\Models\CashRegisterSession|null  Sesión abierta o null si no hay ninguna.
     */
    protected function resolveOpenCashSession(int $userId, int $companyId): ?\App\Models\CashRegisterSession
    {
        $personal = Personal::where('user_id', $userId)->first();
        if (!$personal) return null;

        $registerIds = \App\Models\CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) return null;

        return \App\Models\CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }
}
