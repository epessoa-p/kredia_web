<?php

namespace Tests\Unit;

use App\Services\Installments\ScheduleCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ScheduleCalculatorTest extends TestCase
{
    private function sum(array $rows, string $key): float
    {
        return round(array_sum(array_column($rows, $key)), 2);
    }

    public function test_flat_monthly_matches_simple_interest_and_squares_to_the_cent(): void
    {
        // 1000 al 10% mensual, 3 cuotas mensuales → interés total 300, cuota 433.33
        $rows = ScheduleCalculator::build(1000, 10, 'flat', 'monthly', 3, Carbon::parse('2026-01-10'));

        $this->assertCount(3, $rows);
        $this->assertSame(1000.0, $this->sum($rows, 'capital'));
        $this->assertSame(300.0, $this->sum($rows, 'interest'));
        $this->assertSame(1300.0, $this->sum($rows, 'amount'));
        $this->assertSame(333.33, $rows[0]['capital']);
        $this->assertSame(333.34, $rows[2]['capital']);
        $this->assertSame('2026-02-10', $rows[0]['due_date']);
        $this->assertSame('2026-04-10', $rows[2]['due_date']);
        $this->assertSame(0.0, $rows[2]['balance']);
    }

    public function test_french_has_constant_payment_and_decreasing_interest(): void
    {
        // 1000 al 5% mensual en 12 meses → cuota fija ≈ 112.83
        $rows = ScheduleCalculator::build(1000, 5, 'french', 'monthly', 12, Carbon::parse('2026-01-01'));

        $this->assertSame(112.83, $rows[0]['amount']);
        $this->assertSame(50.0, $rows[0]['interest']);
        $this->assertGreaterThan($rows[11]['interest'], $rows[0]['interest']);
        $this->assertSame(1000.0, $this->sum($rows, 'capital'));
        foreach (array_slice($rows, 0, 11) as $row) {
            $this->assertSame(112.83, $row['amount']);
        }
        $this->assertEqualsWithDelta(112.83, $rows[11]['amount'], 0.10); // la última absorbe los centavos
        $this->assertSame(0.0, $rows[11]['balance']);
    }

    public function test_german_has_constant_capital_and_decreasing_payment(): void
    {
        $rows = ScheduleCalculator::build(1200, 10, 'german', 'monthly', 4, Carbon::parse('2026-01-01'));

        $this->assertSame([300.0, 300.0, 300.0, 300.0], array_column($rows, 'capital'));
        $this->assertSame([120.0, 90.0, 60.0, 30.0], array_column($rows, 'interest'));
        $this->assertSame(1500.0, $this->sum($rows, 'amount'));
    }

    public function test_daily_frequency_skips_sundays_and_prorates_the_rate(): void
    {
        // 2026-01-03 es sábado: la 1.ª cuota caería domingo 4 → pasa al lunes 5.
        $rows = ScheduleCalculator::build(300, 30, 'flat', 'daily', 6, Carbon::parse('2026-01-03'), true);

        $dates = array_column($rows, 'due_date');
        $this->assertSame(['2026-01-05', '2026-01-06', '2026-01-07', '2026-01-08', '2026-01-09', '2026-01-10'], $dates);
        foreach ($dates as $date) {
            $this->assertFalse(Carbon::parse($date)->isSunday());
        }
        // 30% mensual → 1% diario → 6 días = 18 de interés
        $this->assertSame(18.0, $this->sum($rows, 'interest'));
    }

    public function test_weekly_and_biweekly_dates(): void
    {
        $weekly = ScheduleCalculator::build(100, 4, 'flat', 'weekly', 2, Carbon::parse('2026-01-01'));
        $biweekly = ScheduleCalculator::build(100, 4, 'flat', 'biweekly', 2, Carbon::parse('2026-01-01'));

        $this->assertSame(['2026-01-08', '2026-01-15'], array_column($weekly, 'due_date'));
        $this->assertSame(['2026-01-16', '2026-01-31'], array_column($biweekly, 'due_date'));
    }

    public function test_zero_rate_french_is_plain_split(): void
    {
        $rows = ScheduleCalculator::build(100, 0, 'french', 'weekly', 3, Carbon::parse('2026-01-01'));

        $this->assertSame(100.0, $this->sum($rows, 'amount'));
        $this->assertSame(0.0, $this->sum($rows, 'interest'));
    }
}
