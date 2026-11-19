<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\SaleInstallment;
use App\Services\SaleException;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleInstallmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-permission:sales.view');
    }

    /**
     * Registrar el pago de una cuota de venta al crédito.
     */
    public function pay(Request $request, SaleInstallment $installment)
    {
        $user    = auth()->user();
        $sale    = $installment->sale()->with('branch')->firstOrFail();
        $company = $user->getCurrentCompany();

        // Authorization
        if (!$user->is_super_admin && $sale->company_id !== $company?->id) {
            abort(403);
        }

        // Already paid
        if ($installment->status === SaleInstallment::STATUS_PAGADA) {
            return back()->withErrors(['error' => 'Esta cuota ya fue pagada.']);
        }

        $validated = $request->validate([
            'payment_notes' => 'nullable|string|max:500',
        ], SaleService::validationMessages());

        // Require open cash session
        $activeCashSession = $this->resolveOpenCashSession((int) $sale->company_id);
        if (!$activeCashSession) {
            return back()->withErrors([
                'error' => $this->getCashSessionError((int) $sale->company_id)
                    ?? 'Debes tener una caja abierta para registrar pagos.',
            ]);
        }

        try {
            app(SaleService::class)->payInstallment($installment, (int) $user->id, $activeCashSession, $validated['payment_notes'] ?? null);
        } catch (SaleException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al pagar cuota de venta', [
                'installment_id' => $installment->id,
                'sale_id'        => $sale->id,
                'message'        => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'No fue posible registrar el pago. Intenta nuevamente.']);
        }

        return redirect()->route('sales.installments.receipt', $installment);
    }

    /**
     * Mostrar recibo térmico del pago de una cuota.
     */
    public function receipt(SaleInstallment $installment)
    {
        $user = auth()->user();
        $sale = $installment->sale()
            ->with(['client', 'product', 'company', 'branch'])
            ->firstOrFail();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $sale->company_id !== $company?->id) {
            abort(403);
        }

        $totalInstallments = $sale->installments()->count();

        return view('sales.installments.receipt', compact('installment', 'sale', 'totalInstallments'));
    }

    /* ── Helpers (identical pattern to SaleController) ─────────── */

    private function resolveOpenCashSession(int $companyId): ?CashRegisterSession
    {
        $personalRecord = Personal::where('user_id', auth()->id())->first();
        if (!$personalRecord) return null;

        $registerIds = CashRegister::where('assigned_personal_id', $personalRecord->id)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) return null;

        return CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    private function getCashSessionError(int $companyId): ?string
    {
        $personalRecord = Personal::where('user_id', auth()->id())->first();
        if (!$personalRecord) {
            return 'No tienes un registro de personal asociado. Contacta al administrador.';
        }

        $registerIds = CashRegister::where('assigned_personal_id', $personalRecord->id)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) {
            return 'No tienes una caja asignada. Contacta al administrador.';
        }

        $hasOpen = CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->exists();

        if (!$hasOpen) {
            return 'No hay ninguna caja abierta. Debes aperturar una caja antes de registrar pagos.';
        }

        return null;
    }
}
