<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanInterestPayment;
use App\Services\LoanActionException;
use App\Services\LoanPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cobros de préstamos desde la app móvil: interés de un periodo, multa,
 * amortización y liquidación. Misma lógica que la web (LoanPaymentService).
 *
 * La fecha de pago es siempre la de hoy y el método por defecto "efectivo",
 * igual que en la web. Para evitar cobros duplicados la app puede enviar
 * `request_id` (único por intento); si se repite se responde 409.
 */
class LoanActionController extends Controller
{
    public function __construct(protected LoanPaymentService $payments)
    {
    }

    /** POST /loans/{loan}/interest-payments */
    public function payInterest(Request $request, Loan $loan): JsonResponse
    {
        $this->authorizeLoan($request, $loan);

        $validated = $request->validate(array_merge([
            'interest_payment_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
        ], $this->commonRules()), $this->messages());

        return $this->run($request, $loan, 'Pago de interés registrado correctamente.', function () use ($request, $loan, $validated) {
            $ip = $this->payments->payInterest($loan, (int) $validated['interest_payment_id'], $this->data($validated), (int) $request->user()->id, $request->file('receipt'));

            return ['interest_payment' => $ip];
        });
    }

    /** POST /loans/{loan}/penalty-payment — cobra el total de la multa pendiente. */
    public function payPenalty(Request $request, Loan $loan): JsonResponse
    {
        $this->authorizeLoan($request, $loan);

        $validated = $request->validate($this->commonRules(), $this->messages());

        return $this->run($request, $loan, 'Pago de multa registrado correctamente.', function () use ($request, $loan, $validated) {
            $amount = $this->payments->payPenalty($loan, $this->data($validated), (int) $request->user()->id, $request->file('receipt'));

            return ['amount' => $amount];
        });
    }

    /** POST /loans/{loan}/amortization */
    public function amortize(Request $request, Loan $loan): JsonResponse
    {
        $this->authorizeLoan($request, $loan);

        $validated = $request->validate(array_merge([
            'amount' => 'required|numeric|min:0.01',
        ], $this->commonRules()), $this->messages());

        return $this->run($request, $loan, null, function () use ($request, $loan, $validated) {
            $capitalAfter = $this->payments->amortize($loan, $this->data($validated), (int) $request->user()->id, $request->file('receipt'));

            return [
                'message' => 'Amortización registrada. Nuevo capital: ' . \App\Support\Currency::format($capitalAfter, $loan->company),
                'capital_after' => $capitalAfter,
            ];
        });
    }

    /** POST /loans/{loan}/liquidation */
    public function liquidate(Request $request, Loan $loan): JsonResponse
    {
        $this->authorizeLoan($request, $loan);

        $validated = $request->validate(array_merge([
            'amount' => 'required|numeric|min:0.01',
        ], $this->commonRules()), $this->messages());

        return $this->run($request, $loan, 'Préstamo liquidado correctamente.', function () use ($request, $loan, $validated) {
            $this->payments->liquidate($loan, $this->data($validated), (int) $request->user()->id, $request->file('receipt'));

            return [];
        });
    }

    /** DELETE /loans/{loan}/interest-payments/{interestPayment} — eliminación manual (auditada). */
    public function deleteInterest(Request $request, Loan $loan, int $interestPayment): JsonResponse
    {
        $this->authorizeLoan($request, $loan);
        $ip = $this->findInterest($loan, $interestPayment);

        return $this->run($request, $loan, 'Periodo de interés #' . $ip->period_number . ' eliminado correctamente.', function () use ($request, $loan, $ip) {
            $this->payments->deleteInterest($loan, $ip, (int) $request->user()->id);

            return [];
        });
    }

    /** POST /loans/{loan}/interest-payments/{interestPayment}/restore */
    public function restoreInterest(Request $request, Loan $loan, int $interestPayment): JsonResponse
    {
        $this->authorizeLoan($request, $loan);
        $ip = $this->findInterest($loan, $interestPayment);

        return $this->run($request, $loan, 'Periodo de interés #' . $ip->period_number . ' restaurado correctamente.', function () use ($request, $loan, $ip) {
            $this->payments->restoreInterest($loan, $ip, (int) $request->user()->id);

            return [];
        });
    }

    /* ── Internos ──────────────────────────────────────────────── */

    protected function findInterest(Loan $loan, int $id): LoanInterestPayment
    {
        $ip = LoanInterestPayment::withTrashed()->where('loan_id', $loan->id)->find($id);
        if (!$ip) {
            abort(response()->json(['message' => 'El periodo de interés no existe en este préstamo.'], 404));
        }

        return $ip;
    }

    protected function commonRules(): array
    {
        return [
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'request_id' => 'nullable|string|max:64',
        ];
    }

    protected function messages(): array
    {
        return [
            'interest_payment_id.required' => 'Selecciona el periodo de interés a pagar.',
            'interest_payment_id.integer' => 'El periodo de interés no es válido.',
            'amount.required' => 'Ingresa el monto.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'payment_method.max' => 'El método de pago es demasiado largo.',
            'reference.max' => 'La referencia no puede superar los 100 caracteres.',
            'notes.max' => 'Las notas no pueden superar los 1000 caracteres.',
            'receipt.file' => 'El comprobante debe ser un archivo.',
            'receipt.mimes' => 'El comprobante debe ser una imagen (JPG/PNG) o un PDF.',
            'receipt.max' => 'El comprobante no puede pesar más de 5 MB.',
        ];
    }

    /** Datos normalizados para el servicio (fecha de hoy, efectivo por defecto). */
    protected function data(array $validated): array
    {
        return [
            'amount' => $validated['amount'] ?? null,
            'payment_date' => now()->toDateString(),
            'payment_method' => strtolower(trim((string) ($validated['payment_method'] ?? ''))) ?: 'efectivo',
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * Ejecuta el cobro con protección contra doble envío y traduce los errores a JSON.
     */
    protected function run(Request $request, Loan $loan, ?string $message, \Closure $action): JsonResponse
    {
        $requestId = trim((string) $request->input('request_id', ''));
        $lockKey = $requestId !== '' ? 'api-loan-action:' . $request->user()->id . ':' . $requestId : null;

        if ($lockKey && !Cache::add($lockKey, true, now()->addMinutes(10))) {
            return response()->json(['message' => 'Esta operación ya fue enviada. Actualiza el préstamo para ver el resultado.'], 409);
        }

        try {
            $result = $action();
        } catch (LoanActionException $e) {
            if ($lockKey) {
                Cache::forget($lockKey);
            }
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            if ($lockKey) {
                Cache::forget($lockKey);
            }
            Log::error('API: error en cobro de préstamo', [
                'loan_id' => $loan->id,
                'route' => $request->path(),
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'No fue posible registrar la operación. Intenta nuevamente.'], 500);
        }

        $loan = Loan::withTrashed()->find($loan->id);

        return response()->json(array_merge([
            'message' => $message ?? ($result['message'] ?? 'Operación registrada.'),
            'loan' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'total_paid' => (float) $loan->total_paid,
                'current_capital' => $loan->getCurrentCapital(),
                'unpaid_interest_total' => $loan->getUnpaidInterestTotal(),
                'liquidation_amount' => $loan->getLiquidationAmount(),
            ],
        ], $result), 201);
    }

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
}
