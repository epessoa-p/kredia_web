<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;

/**
 * Pone al día la mora de los préstamos sin depender de que alguien abra
 * la pantalla: genera los periodos de interés pendientes y evalúa multa,
 * paso a INCUMPLIDO y liberación de la prenda a la venta.
 */
class CheckOverdueLoans extends Command
{
    protected $signature = 'loans:check-overdue';

    protected $description = 'Genera periodos de interés y actualiza mora/multa/prenda de los préstamos abiertos.';

    public function handle(): int
    {
        $reviewed = 0;
        $defaulted = 0;
        $overdue = 0;

        Loan::whereIn('status', Loan::OPEN_STATUSES)
            ->with(['creditCategory', 'collateral', 'interestPayments'])
            ->chunkById(200, function ($loans) use (&$reviewed, &$defaulted, &$overdue) {
                foreach ($loans as $loan) {
                    $before = $loan->status;

                    $loan->generatePendingInterestPeriods();
                    $loan->checkOverdueStatus();

                    $reviewed++;

                    if ($loan->status !== $before) {
                        if ($loan->status === Loan::STATUS_DEFAULTED) {
                            $defaulted++;
                        } elseif ($loan->status === Loan::STATUS_OVERDUE) {
                            $overdue++;
                        }
                        $this->line("  PR-" . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT)
                            . ": {$before} → {$loan->status}");
                    }
                }
            });

        $this->info("Préstamos revisados: {$reviewed} · nuevos EN_MORA: {$overdue} · nuevos INCUMPLIDO: {$defaulted}");

        return self::SUCCESS;
    }
}
