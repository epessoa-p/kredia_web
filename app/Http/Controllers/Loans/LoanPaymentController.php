<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Personal;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\LoanPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoanPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-permission:loans.view');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        $branchesQuery = Branch::where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $branchesQuery->where('company_id', $company?->id);
        }
        $branches = $branchesQuery->get();

        $selectedBranchId = $request->integer('sucursal_id');

        if (!$user->is_super_admin) {
            $personal = Personal::where('user_id', $user->id)->first();
            if ($personal && !$personal->isManager() && $personal->branch_id) {
                $branches = $branches->where('id', $personal->branch_id)->values();
                $selectedBranchId = (int) $personal->branch_id;
            }
        }

        if ($selectedBranchId && $branches->isNotEmpty() && !$branches->pluck('id')->contains($selectedBranchId)) {
            $selectedBranchId = 0;
        }

        $query = LoanPayment::with(['loan.client', 'loan.product', 'loan.branch', 'registeredBy', 'cashRegister', 'cashRegisterSession'])->latest();

        if (!$user->is_super_admin) {
            $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('company_id', $company?->id));
        }

        if ($selectedBranchId) {
            $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('branch_id', $selectedBranchId));
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('reference', 'like', "%{$q}%")
                    ->orWhereHas('loan.client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$q}%"));
            });
        }

        $payments = $query->paginate(15)->withQueryString();

        return view('loans.payments.index', compact('payments', 'q', 'branches', 'selectedBranchId'));
    }

    public function create(Loan $loan)
    {
        $this->authorizeCompany($loan);

        $loan->load('client', 'product');

        $user = auth()->user();
        $company = $user->getCurrentCompany();
        $canSetPaymentDate = $user->is_super_admin
            || $user->hasPermissionInCompany('loans.payments.set_date', $company);
        $activeCashSession = $this->resolveOpenCashSession($loan->company_id);
        $cashSessionError = $this->getCashSessionError($loan->company_id);

        return view('loans.payments.create', compact('loan', 'canSetPaymentDate', 'activeCashSession', 'cashSessionError'));
    }

    public function store(Request $request, Loan $loan)
    {
        $this->authorizeCompany($loan);

        try {
            $validated = $request->validate([
                'payment_type' => 'required|in:interest,capital,mixed,penalty',
                'amount' => 'required|numeric|min:0.01',
                'capital' => 'nullable|numeric|min:0',
                'interest' => 'nullable|numeric|min:0',
                'payment_date' => 'nullable|date',
                'payment_method' => 'required|string|max:50',
                'reference' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            $user = auth()->user();
            $company = $user->getCurrentCompany();
            $canSetPaymentDate = $user->is_super_admin
                || $user->hasPermissionInCompany('loans.payments.set_date', $company);

            $paymentDate = ($canSetPaymentDate && !empty($validated['payment_date']))
                ? $validated['payment_date']
                : now()->toDateString();

            $activeCashSession = $this->resolveOpenCashSession($loan->company_id);
            if (!$activeCashSession) {
                return back()->withInput()->withErrors([
                    'error' => $this->getCashSessionError($loan->company_id) ?? 'Debes tener una caja abierta para registrar pagos.',
                ]);
            }

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

            $loan->payments()->create([
                'registered_by' => auth()->id(),
                'cash_register_id' => $activeCashSession->cash_register_id,
                'cash_register_session_id' => $activeCashSession->id,
                'amount' => $amount,
                'payment_type' => $validated['payment_type'],
                'capital' => $capital,
                'interest' => $interest,
                'payment_date' => $paymentDate,
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Registrar ingreso en movimientos de caja
            CashMovement::create([
                'company_id' => $loan->company_id,
                'cash_register_id' => $activeCashSession->cash_register_id,
                'cash_register_session_id' => $activeCashSession->id,
                'user_id' => auth()->id(),
                'type' => 'income',
                'category' => 'loan_payment',
                'amount' => $amount,
                'reference_type' => Loan::class,
                'reference_id' => $loan->id,
                'description' => 'Pago préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) . ' (' . $validated['payment_type'] . ')',
                'movement_date' => now(),
            ]);

            $loan->increment('total_paid', $amount);

            if ($loan->fresh()->total_paid >= $loan->total_to_pay) {
                $loan->update(['status' => Loan::STATUS_FINISHED]);
                $this->releaseCollateralFromLoan($loan);
            }

            return redirect()->route('loans.payments.index')->with('success', 'Pago registrado correctamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al registrar pago de préstamo', [
                'loan_id' => $loan->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible registrar el pago.']);
        }
    }

    protected function authorizeCompany(Loan $loan): void
    {
        if (!auth()->user()->is_super_admin && $loan->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    protected function resolveOpenCashSession(int $companyId): ?\App\Models\CashRegisterSession
    {
        $personal = Personal::where('user_id', auth()->id())->first();
        if (!$personal) return null;

        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) return null;

        return \App\Models\CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    protected function getCashSessionError(int $companyId): ?string
    {
        $personal = Personal::where('user_id', auth()->id())->first();
        if (!$personal) {
            return 'Tu usuario no tiene un personal asociado al sistema. Contacta al administrador.';
        }
        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');
        if ($registerIds->isEmpty()) {
            return 'No tienes una caja asignada. Contacta al administrador para que te asigne una.';
        }
        $hasOpen = \App\Models\CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->exists();
        if (!$hasOpen) {
            return 'No hay ninguna caja abierta. Debes aperturar una caja antes de registrar operaciones.';
        }
        return null;
    }

    protected function releaseCollateralFromLoan(Loan $loan): void
    {
        $collateral = LoanCollateral::where('loan_id', $loan->id)->whereIn('status', ['retained', 'sellable'])->first();

        if (!$collateral) {
            return;
        }

        InventoryMovement::create([
            'company_id' => $loan->company_id,
            'warehouse_id' => $collateral->warehouse_id,
            'branch_id' => $collateral->branch_id,
            'product_id' => $collateral->product_id,
            'user_id' => auth()->id(),
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
}
