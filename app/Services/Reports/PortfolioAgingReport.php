<?php

namespace App\Services\Reports;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Illuminate\Support\Collection;

/**
 * Cartera por tramos de atraso (morosidad).
 * - Cuotas: días de la cuota impaga más antigua; saldo = lo pendiente (capital + interés + mora).
 * - Empeño: días de atraso del préstamo (getDaysOverdue); saldo = capital vigente + interés impago + multa.
 * Índice de morosidad = saldo con atraso / saldo total.
 */
class PortfolioAgingReport
{
    public const BUCKETS = [
        'current' => 'Al día',
        '1_30' => '1 a 30 días',
        '31_60' => '31 a 60 días',
        '61_90' => '61 a 90 días',
        '90_plus' => 'Más de 90 días',
    ];

    public function build(?int $branchId = null): array
    {
        $loans = Loan::whereIn('status', Loan::OPEN_STATUSES)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['client:id,name,phone', 'branch:id,name,color', 'installments', 'interestPayments', 'creditCategory'])
            ->get();

        $rows = $loans->map(fn (Loan $loan) => $this->row($loan))->filter(fn ($r) => $r['balance'] > 0)->values();

        $buckets = collect(self::BUCKETS)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'count' => $rows->where('bucket', $key)->count(),
            'balance' => round($rows->where('bucket', $key)->sum('balance'), 2),
        ])->values();

        $total = round($rows->sum('balance'), 2);
        $late = round($rows->where('bucket', '!=', 'current')->sum('balance'), 2);

        return [
            'rows' => $rows->sortByDesc('days_late')->values(),
            'buckets' => $buckets,
            'total_balance' => $total,
            'late_balance' => $late,
            'delinquency_rate' => $total > 0 ? round($late / $total * 100, 2) : 0.0,
        ];
    }

    private function row(Loan $loan): array
    {
        if ($loan->isInstallment()) {
            $pending = $loan->installments->where('status', '!=', LoanInstallment::STATUS_PAID);
            $days = (int) ($pending->map->daysLate()->max() ?? 0);
            $balance = round($pending->sum(fn (LoanInstallment $i) => $i->pendingTotal()), 2);
        } else {
            // Empeño: días desde el vencimiento del periodo de interés impago más antiguo.
            $oldest = $loan->interestPayments
                ->filter(fn ($p) => $p->status !== 'paid' && $p->period_end && \Carbon\Carbon::parse($p->period_end)->lt(today()))
                ->sortBy('period_end')
                ->first();
            $days = $oldest ? (int) \Carbon\Carbon::parse($oldest->period_end)->diffInDays(today()) : 0;
            $balance = round($loan->getCurrentCapital() + $loan->getUnpaidInterestTotal() + $loan->getPendingPenalty(), 2);
        }

        return [
            'loan_id' => $loan->id,
            'code' => $loan->code,
            'kind' => $loan->loan_kind ?? Loan::KIND_PAWN,
            'client' => $loan->client?->name,
            'phone' => $loan->client?->phone,
            'branch' => $loan->branch?->name,
            'branch_color' => $loan->branch?->color,
            'status' => $loan->status,
            'days_late' => $days,
            'balance' => $balance,
            'bucket' => $this->bucket($days),
        ];
    }

    private function bucket(int $days): string
    {
        return match (true) {
            $days <= 0 => 'current',
            $days <= 30 => '1_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /** @return Collection<int, array> */
    public static function bucketKeys(): Collection
    {
        return collect(array_keys(self::BUCKETS));
    }
}
