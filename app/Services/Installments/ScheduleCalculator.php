<?php

namespace App\Services\Installments;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Calcula el plan de pagos de un préstamo en cuotas (lógica pura, sin base de datos).
 *
 * Tasa: `monthlyRate` es el % de interés MENSUAL. La tasa de cada periodo se prorratea
 * por días: diario = 1/30, semanal = 7/30, quincenal = 15/30, mensual = 30/30.
 *
 * Métodos:
 *  - flat   (interés plano): interés total = capital × tasa del periodo × n; cuotas iguales.
 *  - french (cuota fija):    cuota = P·i / (1 − (1+i)^−n); el interés va sobre el saldo.
 *  - german (capital fijo):  capital = P/n en cada cuota; el interés va sobre el saldo (cuotas decrecientes).
 *
 * Los centavos de redondeo se ajustan en la ÚLTIMA cuota para que la suma cuadre exacto.
 */
final class ScheduleCalculator
{
    public const METHODS = ['flat' => 'Interés plano', 'french' => 'Cuota fija (francés)', 'german' => 'Capital fijo (alemán)'];

    public const FREQUENCIES = ['daily' => 'Diario', 'weekly' => 'Semanal', 'biweekly' => 'Quincenal', 'monthly' => 'Mensual'];

    private const PERIOD_DAYS = ['daily' => 1, 'weekly' => 7, 'biweekly' => 15, 'monthly' => 30];

    /**
     * @return array<int, array{number:int, due_date:string, capital:float, interest:float, amount:float, balance:float}>
     */
    public static function build(
        float $principal,
        float $monthlyRate,
        string $method,
        string $frequency,
        int $count,
        CarbonInterface $startDate,
        bool $skipSundays = false,
    ): array {
        if ($principal <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a 0.');
        }
        if ($count < 1) {
            throw new InvalidArgumentException('El número de cuotas debe ser al menos 1.');
        }
        if (!isset(self::PERIOD_DAYS[$frequency])) {
            throw new InvalidArgumentException("Frecuencia no válida: {$frequency}");
        }
        if (!isset(self::METHODS[$method])) {
            throw new InvalidArgumentException("Método no válido: {$method}");
        }

        $i = self::periodRate($monthlyRate, $frequency);
        $dates = self::dueDates($startDate, $frequency, $count, $skipSundays);

        $rows = match ($method) {
            'flat' => self::flat($principal, $i, $count),
            'french' => self::french($principal, $i, $count),
            'german' => self::german($principal, $i, $count),
        };

        foreach ($rows as $k => &$row) {
            $row['number'] = $k + 1;
            $row['due_date'] = $dates[$k];
        }
        unset($row);

        return $rows;
    }

    /** Tasa del periodo (fracción, no %). */
    public static function periodRate(float $monthlyRate, string $frequency): float
    {
        return ($monthlyRate / 100) * (self::PERIOD_DAYS[$frequency] / 30);
    }

    /**
     * Fechas de vencimiento: la primera cuota vence un periodo después del desembolso.
     * Con $skipSundays, una cuota que cae domingo pasa al lunes (y en diario se saltan los domingos).
     *
     * @return string[] Y-m-d
     */
    public static function dueDates(CarbonInterface $start, string $frequency, int $count, bool $skipSundays): array
    {
        $dates = [];
        $cursor = Carbon::parse($start)->startOfDay();

        for ($n = 1; $n <= $count; $n++) {
            if ($frequency === 'daily') {
                $cursor = $cursor->copy()->addDay();
                if ($skipSundays && $cursor->isSunday()) {
                    $cursor = $cursor->addDay();
                }
                $due = $cursor;
            } else {
                $due = match ($frequency) {
                    'weekly' => Carbon::parse($start)->startOfDay()->addWeeks($n),
                    'biweekly' => Carbon::parse($start)->startOfDay()->addDays(15 * $n),
                    'monthly' => Carbon::parse($start)->startOfDay()->addMonthsNoOverflow($n),
                };
                if ($skipSundays && $due->isSunday()) {
                    $due = $due->copy()->addDay();
                }
            }
            $dates[] = $due->toDateString();
        }

        return $dates;
    }

    private static function flat(float $principal, float $i, int $n): array
    {
        $totalInterest = round($principal * $i * $n, 2);
        $capitalEach = round($principal / $n, 2);
        $interestEach = round($totalInterest / $n, 2);

        $rows = [];
        $balance = $principal;
        $capitalSum = 0.0;
        $interestSum = 0.0;
        for ($k = 1; $k <= $n; $k++) {
            $last = $k === $n;
            $capital = $last ? round($principal - $capitalSum, 2) : $capitalEach;
            $interest = $last ? round($totalInterest - $interestSum, 2) : $interestEach;
            $capitalSum += $capital;
            $interestSum += $interest;
            $balance = round($balance - $capital, 2);
            $rows[] = self::row($capital, $interest, $balance);
        }

        return $rows;
    }

    private static function french(float $principal, float $i, int $n): array
    {
        $payment = $i > 0
            ? round($principal * $i / (1 - (1 + $i) ** (-$n)), 2)
            : round($principal / $n, 2);

        $rows = [];
        $balance = $principal;
        for ($k = 1; $k <= $n; $k++) {
            $interest = round($balance * $i, 2);
            $capital = $k === $n ? round($balance, 2) : round($payment - $interest, 2);
            $balance = round($balance - $capital, 2);
            $rows[] = self::row($capital, $interest, $balance);
        }

        return $rows;
    }

    private static function german(float $principal, float $i, int $n): array
    {
        $capitalEach = round($principal / $n, 2);

        $rows = [];
        $balance = $principal;
        for ($k = 1; $k <= $n; $k++) {
            $interest = round($balance * $i, 2);
            $capital = $k === $n ? round($balance, 2) : $capitalEach;
            $balance = round($balance - $capital, 2);
            $rows[] = self::row($capital, $interest, $balance);
        }

        return $rows;
    }

    private static function row(float $capital, float $interest, float $balance): array
    {
        return [
            'capital' => $capital,
            'interest' => $interest,
            'amount' => round($capital + $interest, 2),
            'balance' => max(0.0, $balance),
        ];
    }
}
