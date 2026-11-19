<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanInterestPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador del dashboard para la API.
 *
 * Provee un resumen ejecutivo de los préstamos de la empresa:
 * totales, estados y los últimos registros recientes.
 */
class DashboardController extends Controller
{
    /**
     * Retorna el resumen general de préstamos de la empresa.
     *
     * Calcula conteos por estado (activos, finalizados, vencidos),
     * montos totales prestados, cobrados y pendientes, y los últimos
     * 10 préstamos con datos del cliente.
     *
     * @param  Request  $request
     * @return JsonResponse  200 con company, summary y recent_loans.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = Loan::query();

        if (!$user->is_super_admin) {
            $query->where('company_id', $company?->id);
        } elseif ($company) {
            $query->where('company_id', $company->id);
        }

        $totalLoans = (clone $query)->count();
        $activeLoans = (clone $query)->where('status', Loan::STATUS_ACTIVE)->count();
        $completedLoans = (clone $query)->where('status', Loan::STATUS_FINISHED)->count();
        $overdueLoans = (clone $query)->where('status', Loan::STATUS_OVERDUE)->count();
        $totalAmount = (float) (clone $query)->sum('amount');
        $totalPaid = (float) (clone $query)->sum('total_paid');

        // Préstamos vigentes (el cliente aún paga): capital que falta recuperar e intereses vencidos sin pagar.
        // Los incumplidos no entran: su prenda ya pasó a la venta.
        $current = (clone $query)->whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_OVERDUE]);
        $currentCount = (clone $current)->count();
        $currentCapital = (float) (clone $current)->sum(DB::raw('COALESCE(current_capital, amount)'));
        $currentInterest = (float) LoanInterestPayment::whereIn('loan_id', (clone $current)->select('id'))
            ->where('status', '!=', 'paid')
            ->sum(DB::raw('interest_amount - paid_amount'));

        // Mismo "pendiente" que la lista de préstamos (Api\LoanController::index): capital actual + intereses sin pagar.
        $recentLoans = (clone $query)
            ->with(['client', 'branch'])
            ->addSelect([
                'unpaid_interest_total' => LoanInterestPayment::selectRaw('COALESCE(SUM(interest_amount - paid_amount), 0)')
                    ->whereColumn('loan_id', 'loans.id')
                    ->where('status', '!=', 'paid'),
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Loan $loan) => [
                'id'         => $loan->id,
                'numero'     => $loan->numero,
                'code'       => $loan->code,
                'client'     => $loan->client ? [
                    'id'   => $loan->client->id,
                    'name' => $loan->client->name,
                ] : null,
                'branch'     => $loan->branch ? [
                    'id'    => $loan->branch->id,
                    'name'  => $loan->branch->name,
                    'color' => $loan->branch->color,
                ] : null,
                'amount'     => (float) $loan->amount,
                'total_paid' => (float) $loan->total_paid,
                'current_capital' => (float) ($loan->current_capital ?? $loan->amount),
                'unpaid_interest_total' => (float) $loan->unpaid_interest_total,
                'status'     => $loan->status,
                'created_at' => optional($loan->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
            ] : null,
            'summary' => [
                'total_loans' => $totalLoans,
                'active_loans' => $activeLoans,
                'completed_loans' => $completedLoans,
                'overdue_loans' => $overdueLoans,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'total_pending' => round($totalAmount - $totalPaid, 2),
                // Para el inicio de la app (los tres campos de arriba son históricos y se mantienen por compatibilidad).
                'current_loans' => $currentCount,
                'current_capital' => round($currentCapital, 2),
                'current_interest' => round($currentInterest, 2),
                'current_receivable' => round($currentCapital + $currentInterest, 2),
            ],
            'recent_loans' => $recentLoans,
        ]);
    }
}
