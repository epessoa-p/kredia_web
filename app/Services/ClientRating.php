<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Loan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calificación de clientes según su historial de préstamos.
 *
 * Las estadísticas se calculan en UNA consulta agregada por cliente y la categoría
 * se asigna en PHP (una sola fuente de verdad para la lista y la ficha del cliente).
 *
 * Categorías (en este orden de prioridad):
 *   riesgo  — tiene hoy un préstamo incumplido (o en mora, si así se configura).
 *   nuevo   — todavía sin historial (sin intereses pagados ni préstamos finalizados) y sin atrasos.
 *   fiel    — puntual y con al menos N préstamos y/o M Bs prestados (y % mínimo de puntualidad).
 *   puntual — atrasos dentro de lo permitido, sin multas (si así se configura) ni incumplidos.
 *   regular — el resto: tuvo atrasos o multas, pero hoy está al día.
 *
 * Las reglas son de cada empresa (companies.client_rating_settings, panel
 * "Configurar categorías"); config/clients.php tiene los valores por defecto.
 */
class ClientRating
{
    public const CATEGORIES = [
        'fiel'    => ['label' => 'Cliente fiel', 'icon' => 'bi-star-fill',                'color' => '#b45309', 'bg' => '#fef3c7', 'border' => '#fcd34d'],
        'puntual' => ['label' => 'Puntual',      'icon' => 'bi-check-circle-fill',        'color' => '#15803d', 'bg' => '#dcfce7', 'border' => '#86efac'],
        'regular' => ['label' => 'Regular',      'icon' => 'bi-dash-circle-fill',         'color' => '#475569', 'bg' => '#f1f5f9', 'border' => '#cbd5e1'],
        'riesgo'  => ['label' => 'Riesgo',       'icon' => 'bi-exclamation-octagon-fill', 'color' => '#b91c1c', 'bg' => '#fee2e2', 'border' => '#fca5a5'],
        'nuevo'   => ['label' => 'Nuevo',        'icon' => 'bi-stars',                    'color' => '#1d4ed8', 'bg' => '#dbeafe', 'border' => '#93c5fd'],
    ];

    /** Columnas de estadísticas de cada cliente. */
    public const STAT_COLUMNS = [
        'loans_count', 'total_borrowed', 'finished_count', 'defaulted_count', 'risk_open_count',
        'penalty_loans_count', 'paid_interests', 'on_time_interests', 'late_interests',
    ];

    /** Valores por defecto de las reglas (config/clients.php). */
    public static function defaults(): array
    {
        return self::normalize((array) config('clients.rating', []), [
            'tolerance_days' => 3, 'punctual_max_late' => 0, 'penalty_breaks' => true,
            'loyal_min_loans' => 2, 'loyal_min_amount' => 3000, 'loyal_mode' => 'any',
            'loyal_min_punctuality' => 0, 'risk_include_mora' => true,
        ]);
    }

    /** Reglas de una empresa: las que guardó en el panel, o las de por defecto. */
    public static function settingsFor(Company|int|null $company): array
    {
        if (is_int($company)) {
            $company = Company::find($company);
        }

        return self::normalize((array) ($company?->client_rating_settings ?? []), self::defaults());
    }

    /** Limpia y completa un conjunto de reglas (los valores fuera de rango se ajustan). */
    public static function normalize(array $input, array $base): array
    {
        $get = fn ($key) => array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '' ? $input[$key] : $base[$key];
        $bool = fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN);

        return [
            'tolerance_days' => min(60, max(0, (int) $get('tolerance_days'))),
            'punctual_max_late' => min(100, max(0, (int) $get('punctual_max_late'))),
            'penalty_breaks' => $bool($get('penalty_breaks')),
            'loyal_min_loans' => min(100, max(1, (int) $get('loyal_min_loans'))),
            'loyal_min_amount' => min(10000000, max(0, round((float) $get('loyal_min_amount'), 2))),
            'loyal_mode' => $get('loyal_mode') === 'all' ? 'all' : 'any',
            'loyal_min_punctuality' => min(100, max(0, (int) $get('loyal_min_punctuality'))),
            'risk_include_mora' => $bool($get('risk_include_mora')),
        ];
    }

    /**
     * Estadísticas por cliente (una fila por client_id).
     */
    public function statsQuery(int $tolerance = 3): Builder
    {
        $tolerance = max(0, $tolerance);

        // Por préstamo: intereses pagados, pagados a tiempo y atrasados.
        // Atrasado = pagado después de vencimiento + tolerancia, o impago y ya vencido + tolerancia.
        $interests = DB::table('loan_interest_payments')
            ->whereNull('deleted_at')
            ->selectRaw(<<<'SQL'
                loan_id,
                SUM(status = 'paid') AS paid_count,
                SUM(status = 'paid' AND paid_at IS NOT NULL AND DATE(paid_at) <= DATE_ADD(period_end, INTERVAL ? DAY)) AS on_time_count,
                SUM(
                    (status = 'paid' AND paid_at IS NOT NULL AND DATE(paid_at) > DATE_ADD(period_end, INTERVAL ? DAY))
                    OR (status <> 'paid' AND DATE_ADD(period_end, INTERVAL ? DAY) < CURDATE())
                ) AS late_count
                SQL, [$tolerance, $tolerance, $tolerance])
            ->groupBy('loan_id');

        // Préstamos con pago de multa registrado en caja.
        // (No se usa loans.penalty_paid_at: la liquidación lo llena siempre, haya multa o no.)
        $penaltyPayments = DB::table('cash_movements')
            ->where('category', 'penalty_payment')
            ->where('reference_type', Loan::class)
            ->selectRaw('reference_id AS loan_id, COUNT(*) AS penalty_payments')
            ->groupBy('reference_id');

        return DB::table('loans as l')
            ->leftJoinSub($interests, 'ip', 'ip.loan_id', '=', 'l.id')
            ->leftJoinSub($penaltyPayments, 'pp', 'pp.loan_id', '=', 'l.id')
            ->whereNull('l.deleted_at')
            ->selectRaw(<<<'SQL'
                l.client_id,
                COUNT(*) AS loans_count,
                COALESCE(SUM(l.amount), 0) AS total_borrowed,
                SUM(l.status = 'FINALIZADO') AS finished_count,
                SUM(l.status = 'INCUMPLIDO') AS defaulted_count,
                SUM(l.status IN ('EN_MORA', 'INCUMPLIDO')) AS risk_open_count,
                SUM(COALESCE(l.penalty_status, 'normal') <> 'normal' OR COALESCE(pp.penalty_payments, 0) > 0) AS penalty_loans_count,
                COALESCE(SUM(ip.paid_count), 0) AS paid_interests,
                COALESCE(SUM(ip.on_time_count), 0) AS on_time_interests,
                COALESCE(SUM(ip.late_count), 0) AS late_interests
                SQL)
            ->groupBy('l.client_id');
    }

    /**
     * Estadísticas + categoría + puntualidad de los clientes indicados.
     * Los clientes sin préstamos también aparecen (categoría "nuevo").
     *
     * Cada cliente se califica con las reglas de SU empresa, salvo que se pasen
     * $settings (vista previa del panel): entonces se usan para todos.
     *
     * @return Collection<int, object> keyed by client_id
     */
    public function statsFor(iterable $clientIds, ?array $settings = null): Collection
    {
        $ids = collect($clientIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        // Empresa de cada cliente → reglas de esa empresa.
        $companyOf = [];
        if ($settings === null) {
            foreach ($ids->chunk(1000) as $chunk) {
                $companyOf += DB::table('clients')->whereIn('id', $chunk->all())->pluck('company_id', 'id')->all();
            }
        }
        $settingsByCompany = [];
        $settingsOf = function (int $id) use ($settings, $companyOf, &$settingsByCompany) {
            if ($settings !== null) {
                return $settings;
            }
            $companyId = (int) ($companyOf[$id] ?? 0);

            return $settingsByCompany[$companyId] ??= self::settingsFor($companyId ?: null);
        };

        // Una consulta por cada tolerancia distinta (normalmente una sola).
        $rows = collect();
        foreach ($ids->groupBy(fn ($id) => $settingsOf($id)['tolerance_days']) as $tolerance => $group) {
            foreach ($group->chunk(1000) as $chunk) {
                $rows = $rows->merge($this->statsQuery((int) $tolerance)->whereIn('l.client_id', $chunk->all())->get());
            }
        }
        $rows = $rows->keyBy('client_id');

        return $ids->mapWithKeys(function ($id) use ($rows, $settingsOf) {
            $row = $rows->get($id) ?? (object) ['client_id' => $id];
            $stats = (object) ['client_id' => $id];
            foreach (self::STAT_COLUMNS as $column) {
                $stats->$column = $column === 'total_borrowed'
                    ? (float) ($row->$column ?? 0)
                    : (int) ($row->$column ?? 0);
            }
            $stats->punctuality = $stats->paid_interests > 0
                ? (int) round(100 * $stats->on_time_interests / $stats->paid_interests)
                : null;
            $stats->rating = $this->categorize($stats, $settingsOf($id));

            return [$id => $stats];
        });
    }

    /** Estadísticas y categoría de un cliente (para su ficha). */
    public function forClient(int $clientId): object
    {
        return $this->statsFor([$clientId])->get($clientId);
    }

    /** Categoría a partir de las estadísticas y las reglas de la empresa. */
    public function categorize(object $s, ?array $settings = null): string
    {
        $r = $settings ?? self::defaults();

        $moraOpen = $s->risk_open_count - $s->defaulted_count;
        if ($s->defaulted_count > 0 || ($r['risk_include_mora'] && $moraOpen > 0)) {
            return 'riesgo';
        }
        if ($s->paid_interests === 0 && $s->finished_count === 0 && $s->late_interests === 0) {
            return 'nuevo';
        }

        $clean = $s->late_interests <= $r['punctual_max_late']
            && (!$r['penalty_breaks'] || $s->penalty_loans_count === 0);
        if (!$clean) {
            return 'regular';
        }

        $byLoans = $s->loans_count >= $r['loyal_min_loans'];
        $byAmount = $s->total_borrowed >= $r['loyal_min_amount'];
        $loyal = $r['loyal_mode'] === 'all' ? ($byLoans && $byAmount) : ($byLoans || $byAmount);
        if ($loyal && $r['loyal_min_punctuality'] > 0) {
            $loyal = ($s->punctuality ?? 0) >= $r['loyal_min_punctuality'];
        }

        return $loyal ? 'fiel' : 'puntual';
    }

    public static function meta(?string $rating): array
    {
        return self::CATEGORIES[$rating] ?? self::CATEGORIES['nuevo'];
    }
}
