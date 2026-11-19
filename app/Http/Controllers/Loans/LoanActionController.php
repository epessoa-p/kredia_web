<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanInterestPayment;
use App\Services\LoanActionException;
use App\Services\LoanPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoanActionController extends Controller
{
    public function __construct(protected LoanPaymentService $payments)
    {
        $this->middleware('check-permission:loans.view');
    }

    /**
     * Registrar pago de interés de un periodo específico.
     */
    public function storeInterestPayment(Request $request, Loan $loan)
    {
        $this->authorizeCompany($loan);

        $validated = $request->validate([
            'interest_payment_id' => 'required|integer|exists:loan_interest_payments,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        try {
            $this->payments->payInterest($loan, (int) $validated['interest_payment_id'], $validated, (int) auth()->id(), $request->file('receipt'));
        } catch (LoanActionException $exception) {
            return back()->withInput()->with('activeLoanTab', 'interest')->withErrors(['error' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::error('Error al pagar interés', ['loan_id' => $loan->id, 'message' => $exception->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible registrar el pago de interés.']);
        }

        return redirect()
            ->route('loans.show', $this->loanShowRouteParams($loan, $request))
            ->with('success', 'Pago de interés registrado correctamente.');
    }

    /**
     * Pago de la multa (monto total, no parcial).
     * Salda SOLO la multa: el préstamo sigue en mora y el proceso de días de
     * gracia continúa hasta que se paguen los intereses vencidos.
     */
    public function storePenaltyPayment(Request $request, Loan $loan)
    {
        $this->authorizeCompany($loan);

        $validated = $request->validate([
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        try {
            $this->payments->payPenalty($loan, $validated, (int) auth()->id(), $request->file('receipt'));
        } catch (LoanActionException $exception) {
            return back()->withErrors(['error' => $exception->getMessage()])->with('activeLoanTab', 'penalty');
        } catch (\Throwable $exception) {
            Log::error('Error al pagar multa', ['loan_id' => $loan->id, 'message' => $exception->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible registrar el pago de la multa.']);
        }

        return redirect()
            ->route('loans.show', $this->loanShowRouteParams($loan, $request))
            ->with('success', 'Pago de multa registrado correctamente.')
            ->with('activeLoanTab', 'penalty');
    }

    /**
     * Registrar amortización (abono al capital).
     */
    public function storeAmortization(Request $request, Loan $loan)
    {
        $this->authorizeCompany($loan);

        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01|max:' . $loan->getCurrentCapital(),
                'payment_date' => 'required|date',
                'payment_method' => 'required|string|max:50',
                'reference' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);

            $capitalAfter = $this->payments->amortize($loan, $validated, (int) auth()->id(), $request->file('receipt'));

            return redirect()->route('loans.show', $this->loanShowRouteParams($loan, $request))
                ->with('success', 'Amortización registrada. Nuevo capital: ' . \App\Support\Currency::format($capitalAfter, $loan->company))
                ->with('activeLoanTab', 'amortize');
        } catch (ValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors($exception->validator)
                ->with('activeLoanTab', 'amortize');
        } catch (LoanActionException $exception) {
            return back()
                ->withInput()
                ->withErrors(['error' => $exception->getMessage()])
                ->with('activeLoanTab', 'amortize');
        } catch (\Throwable $exception) {
            Log::error('Error al amortizar préstamo', ['loan_id' => $loan->id, 'message' => $exception->getMessage()]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'No fue posible registrar la amortización.'])
                ->with('activeLoanTab', 'amortize');
        }
    }

    /**
     * Liquidar préstamo: pagar capital + intereses + multas pendientes.
     */
    public function storeLiquidation(Request $request, Loan $loan)
    {
        $this->authorizeCompany($loan);

        try {
            $liquidationAmount = $loan->getLiquidationAmount();

            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . ($liquidationAmount > 0 ? $liquidationAmount : 0.01),
                'payment_date' => 'required|date',
                'payment_method' => 'required|string|max:50',
                'reference' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);

            $this->payments->liquidate($loan, $validated, (int) auth()->id(), $request->file('receipt'));

            return redirect()->route('loans.show', $this->loanShowRouteParams($loan, $request))
                ->with('success', 'Préstamo liquidado correctamente.')
                ->with('activeLoanTab', 'liquidate');
        } catch (ValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors($exception->validator)
                ->with('activeLoanTab', 'liquidate');
        } catch (LoanActionException $exception) {
            return back()
                ->withInput()
                ->withErrors(['error' => $exception->getMessage()])
                ->with('activeLoanTab', 'liquidate');
        } catch (\Throwable $exception) {
            Log::error('Error al liquidar préstamo', ['loan_id' => $loan->id, 'message' => $exception->getMessage()]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'No fue posible liquidar el préstamo.'])
                ->with('activeLoanTab', 'liquidate');
        }
    }

    /**
     * Eliminar (soft delete) un periodo de interés no pagado.
     */
    public function destroyInterestPayment(Request $request, Loan $loan, LoanInterestPayment $interestPayment)
    {
        $this->authorizeCompany($loan);

        if ((int) $interestPayment->loan_id !== (int) $loan->id) {
            abort(404);
        }

        try {
            $this->payments->deleteInterest($loan, $interestPayment, (int) auth()->id());
        } catch (LoanActionException $exception) {
            return back()
                ->withErrors(['error' => $exception->getMessage()])
                ->with('activeLoanTab', 'interest');
        }

        return redirect()
            ->route('loans.show', $this->loanShowRouteParams($loan, $request))
            ->with('success', 'Periodo de interés eliminado correctamente.')
            ->with('activeLoanTab', 'interest');
    }

    /**
     * Restaurar un periodo de interés eliminado (requiere permiso loans.interest.restore).
     */
    public function restoreInterestPayment(Request $request, Loan $loan, LoanInterestPayment $interestPayment)
    {
        $this->authorizeCompany($loan);

        if ((int) $interestPayment->loan_id !== (int) $loan->id) {
            abort(404);
        }

        $back = fn (string $type, string $message) => redirect()
            ->route('loans.show', $this->loanShowRouteParams($loan, $request))
            ->with($type, $message)
            ->with('activeLoanTab', 'interest');

        try {
            $this->payments->restoreInterest($loan, $interestPayment, (int) auth()->id());
        } catch (LoanActionException $exception) {
            return $back('error', $exception->getMessage());
        }

        return $back('success', 'Periodo de interés #' . $interestPayment->period_number . ' restaurado correctamente.');
    }

    public function amortize(Loan $loan)
    {
        $this->authorizeCompany($loan);
        $loan->load('client', 'product');
        $activeCashSession = $this->payments->resolveOpenCashSession((int) auth()->id(), (int) $loan->company_id);
        $cashSessionError = $this->payments->getCashSessionError((int) auth()->id(), (int) $loan->company_id);
        return view('loans.actions.amortize', compact('loan', 'activeCashSession', 'cashSessionError'));
    }

    public function liquidate(Loan $loan)
    {
        $this->authorizeCompany($loan);
        $loan->load('client', 'product');
        $pending = $loan->getLiquidationAmount();
        $activeCashSession = $this->payments->resolveOpenCashSession((int) auth()->id(), (int) $loan->company_id);
        $cashSessionError = $this->payments->getCashSessionError((int) auth()->id(), (int) $loan->company_id);
        return view('loans.actions.liquidate', compact('loan', 'pending', 'activeCashSession', 'cashSessionError'));
    }

    protected function authorizeCompany(Loan $loan): void
    {
        if (!auth()->user()->is_super_admin && $loan->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    protected function loanShowRouteParams(Loan $loan, Request $request): array
    {
        $params = ['loan' => $loan];
        $fromContext = (string) $request->input('from_context', '');

        if (in_array($fromContext, ['client', 'loans'], true)) {
            $params['from'] = $fromContext;
        }

        return $params;
    }
}
