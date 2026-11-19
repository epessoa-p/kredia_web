<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Company;
use App\Models\Loan;
use App\Support\Currency;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exportación de los reportes: la misma tabla para la página de impresión de la web, el PDF (dompdf) y el Excel
 * (.xlsx con PhpSpreadsheet). Las consultas salen de ReportService, igual que la pantalla y la API.
 *
 * El HTML evita flex/grid (dompdf no los admite) y en modo Excel los montos van como números.
 */
class ReportExporter
{
    /** Reportes exportables => [título, nombre de archivo]. */
    public const REPORTS = [
        'loans' => ['Reporte de Préstamos', 'prestamos'],
        'payments' => ['Reporte de Pagos de Préstamos', 'pagos'],
        'movements' => ['Reporte de Movimientos de Caja', 'movimientos'],
        'closures' => ['Reporte de Cierres de Caja', 'cierres-caja'],
        'clients' => ['Reporte de Cartera de Clientes', 'clientes'],
        'warehouses' => ['Reporte de Almacenes', 'almacenes'],
    ];

    private bool $excel = false;

    /** En el PDF la tabla se parte en bloques: dompdf consume mucha memoria con una tabla única grande. */
    private bool $pdf = false;
    private const PDF_ROWS_PER_TABLE = 40;

    /** Más filas que esto no se arman en PDF (memoria y tiempo): se pide filtrar o usar Excel. */
    public const PDF_MAX_ROWS = 2000;
    private ?Company $company = null;

    public function __construct(private ReportService $reports)
    {
    }

    /* ── Salidas ─────────────────────────────────────────────── */

    /** Página para imprimir / guardar como PDF desde el navegador (exportación "PDF" de la web). */
    public function printPage(string $report, ?Company $company, array $f, string $type): string
    {
        [$title, , $body] = $this->build($report, $company, $f, $type, false);

        return $this->document($title, $body, true);
    }

    /** PDF real (para la app). */
    public function pdf(string $report, ?Company $company, array $f, string $type): array
    {
        // Un PDF de ~700 filas usa unos 200 MB y ~12 s con dompdf.
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);
        [$title, $file, $body] = $this->build($report, $company, $f, $type, false, true);
        $pdf = Pdf::loadHTML($this->document($title, $body, false))
            ->setPaper('letter', $type === 'detailed' ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        return [$file . '-' . now()->format('Y-m-d') . '.pdf', $pdf->output()];
    }

    /** Excel .xlsx real (web y app). */
    public function xlsx(string $report, ?Company $company, array $f, string $type): array
    {
        [$title, $file, $body] = $this->build($report, $company, $f, $type, true);

        return [$file . '-' . now()->format('Y-m-d') . '.xlsx', $this->toXlsx($this->document($title, $body, false), $title)];
    }

    /** Préstamos de un cliente (exportación desde la ficha del cliente). */
    public function clientLoans(Client $client, string $type, string $format): array
    {
        $this->excel = $format === 'excel';
        $this->pdf = false;
        $this->company = $client->company;
        $loans = $client->loans()->with('product', 'creditCategory', 'branch')->latest()->get();
        $title = 'Préstamos de ' . $client->name;
        $subtitle = $client->id_number ? 'Doc: ' . $client->id_number : '';
        $body = $this->header($title, $subtitle, []) . $this->loansTable($loans, $type);
        $file = 'prestamos-' . Str::slug($client->name ?: (string) $client->id);

        return match ($format) {
            'excel' => [$file . '.xlsx', $this->toXlsx($this->document($title, $body, false), $title)],
            default => [$file . '.html', $this->document($title, $body, true)],
        };
    }

    /* ── Armado de cada reporte ──────────────────────────────── */

    /** @return array{0:string,1:string,2:string} [título, nombre de archivo, cuerpo HTML] */
    private function build(string $report, ?Company $company, array $f, string $type, bool $excel, bool $pdf = false): array
    {
        if (!isset(self::REPORTS[$report])) {
            abort(404);
        }
        $this->excel = $excel;
        $this->pdf = $pdf;
        $this->company = $company;
        $type = $type === 'detailed' ? 'detailed' : 'summary';
        [$title, $file] = self::REPORTS[$report];
        $subtitle = $company?->name ?? 'Todas las empresas';
        $html = $this->header($title, $subtitle, $f);

        $html .= match ($report) {
            'loans' => $this->loansReport($company, $f, $type),
            'payments' => $this->paymentsReport($company, $f, $type),
            'movements' => $this->movementsReport($company, $f, $type),
            'closures' => $this->closuresReport($company, $f, $type),
            'clients' => $this->clientsReport($company, $f, $type),
            'warehouses' => $this->warehousesReport($company, $type),
        };

        return [$title, $file, $html];
    }

    private function loansReport(?Company $company, array $f, string $type): string
    {
        $query = $this->reports->loansQuery($company, $f);

        return $this->loansTable($query->get(), $type);
    }

    private function loansTable(Collection $loans, string $type): string
    {
        $amount = (float) $loans->sum('amount');
        $paid = (float) $loans->sum('total_paid');
        $pending = (float) $loans->sum(fn ($l) => ReportService::loanPending($l));

        $html = $this->cards([
            ['Préstamos', $loans->count(), '#0d6efd'],
            ['Monto total', $this->moneyText($amount), '#198754'],
            ['Total pagado', $this->moneyText($paid), '#198754'],
            ['Pendiente', $this->moneyText($pending), '#dc3545'],
        ]);

        $detailed = $type === 'detailed';
        $head = $detailed
            ? ['Código', 'Cliente', 'Producto', 'Categoría', 'Sucursal', ['Monto', 'r'], ['Pagado', 'r'], ['Pendiente', 'r'], 'Tasa', 'Plazo', 'Estado', 'Fecha']
            : ['Código', 'Cliente', 'Producto', ['Monto', 'r'], ['Pagado', 'r'], ['Pendiente', 'r'], 'Estado'];
        $rows = $loans->map(function (Loan $loan) use ($detailed) {
            $code = 'PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT);
            $status = Loan::STATUS_LABELS[$loan->status] ?? ucfirst((string) $loan->status);
            $money = [$this->money($loan->amount), $this->money($loan->total_paid), $this->money(ReportService::loanPending($loan))];

            return $detailed
                ? [$code, $loan->client?->name ?? '-', $loan->product?->name ?? '-', $loan->creditCategory?->name ?? '-',
                    $loan->branch?->name ?? '-', ...$money, $loan->interest_rate . '%', $loan->term_months . ' m', $status,
                    $loan->created_at?->format('d/m/Y')]
                : [$code, $loan->client?->name ?? '-', $loan->product?->name ?? '-', ...$money, $status];
        });
        $foot = $detailed
            ? [['TOTALES', 5], $this->money($amount), $this->money($paid), $this->money($pending), ['', 4]]
            : [['TOTALES', 3], $this->money($amount), $this->money($paid), $this->money($pending), ''];

        return $html . $this->table($head, $rows, $foot);
    }

    private function paymentsReport(?Company $company, array $f, string $type): string
    {
        $query = $this->reports->paymentsQuery($company, $f);
        $totals = $this->reports->paymentsTotals($query);
        $payments = $query->get();
        $types = ['interest' => 'Interés', 'capital' => 'Capital', 'mixed' => 'Mixto', 'penalty' => 'Multa'];

        $html = $this->cards([
            ['Pagos', $totals['count'], '#0d6efd'],
            ['Total cobrado', $this->moneyText($totals['amount']), '#198754'],
            ['Capital', $this->moneyText($totals['capital']), '#0d6efd'],
            ['Intereses', $this->moneyText($totals['interest']), '#6f42c1'],
        ]);

        $detailed = $type === 'detailed';
        $head = $detailed
            ? ['Fecha', 'Préstamo', 'Cliente', 'Tipo', 'Método', 'Referencia', ['Capital', 'r'], ['Interés', 'r'], ['Monto', 'r'], 'Caja', 'Registró']
            : ['Fecha', 'Préstamo', 'Cliente', 'Tipo', ['Monto', 'r']];
        $rows = $payments->map(function ($p) use ($detailed, $types) {
            $code = $p->loan ? 'PR-' . str_pad((string) $p->loan->numero, 5, '0', STR_PAD_LEFT) : '-';
            $base = [$p->payment_date?->format('d/m/Y') ?? '-', $code, $p->loan?->client?->name ?? '-',
                $types[$p->payment_type] ?? ucfirst((string) $p->payment_type)];

            return $detailed
                ? [...$base, ucfirst((string) ($p->payment_method ?? '-')), $p->reference ?: '-', $this->money($p->capital),
                    $this->money($p->interest), $this->money($p->amount), $p->cashRegister?->name ?? '-', $p->registeredBy?->name ?? '-']
                : [...$base, $this->money($p->amount)];
        });
        $foot = $detailed
            ? [['TOTALES', 6], $this->money($totals['capital']), $this->money($totals['interest']), $this->money($totals['amount']), ['', 2]]
            : [['TOTALES', 4], $this->money($totals['amount'])];

        return $html . $this->table($head, $rows, $foot);
    }

    private function movementsReport(?Company $company, array $f, string $type): string
    {
        $query = $this->reports->movementsQuery($company, $f);
        $totals = $this->reports->movementsTotals($query);

        $cards = [
            ['Movimientos', $totals['transacciones'], '#0d6efd'],
            ['Ingresos', $this->moneyText($totals['ingresos']), '#198754'],
            ['Egresos', $this->moneyText($totals['egresos']), '#dc3545'],
            ['Utilidad', $this->moneyText($totals['utilidad']), $totals['utilidad'] >= 0 ? '#198754' : '#dc3545'],
        ];
        if ($totals['aportes'] > 0) {
            $cards[] = ['Aportes a caja (no operativo)', $this->moneyText($totals['aportes']), '#6c757d'];
        }
        $html = $this->cards($cards);

        if ($type === 'detailed') {
            $rows = $query->get()->map(fn ($m) => [
                $m->movement_date?->format('d/m/Y H:i') ?? '-',
                $m->type === 'income' ? 'Ingreso' : 'Egreso',
                $m->category_label,
                $m->description ?? '-',
                $m->cashRegister?->name ?? '-',
                $this->money($m->type === 'expense' ? -1 * (float) $m->amount : $m->amount),
                $m->user?->name ?? '-',
            ]);

            return $html . $this->table(['Fecha', 'Tipo', 'Categoría', 'Descripción', 'Caja', ['Monto', 'r'], 'Usuario'], $rows);
        }

        $rows = $this->reports->movementsByCategory($query)->map(fn ($c) => [
            $c['label'], [$c['count'], 'c'], $this->money($c['income']), $this->money($c['expense']), $this->money($c['net']),
        ]);

        return $html . $this->table(['Categoría', ['Movimientos', 'c'], ['Ingresos', 'r'], ['Egresos', 'r'], ['Neto', 'r']], $rows);
    }

    private function closuresReport(?Company $company, array $f, string $type): string
    {
        $query = $this->reports->closuresQuery($company, $f);
        $totals = $this->reports->closuresTotals($query);
        $sessions = $query->get();

        $html = $this->cards([
            ['Sesiones', $totals['sessions'], '#0d6efd'],
            ['Abiertas', $totals['open_sessions'], '#198754'],
            ['Cerradas', $totals['closed_sessions'], '#6c757d'],
            ['Descuadres', $totals['mismatches'], $totals['mismatches'] > 0 ? '#dc3545' : '#198754'],
        ]);

        $detailed = $type === 'detailed';
        $head = $detailed
            ? ['Caja', 'Apertura', 'Cierre', 'Abrió', 'Cerró', ['M. apertura', 'r'], ['M. cierre', 'r'], ['Ingresos', 'r'],
                ['Egresos', 'r'], ['Descuadre', 'r'], ['Mov.', 'c'], 'Estado']
            : ['Caja', 'Apertura', 'Cierre', ['Ingresos', 'r'], ['Egresos', 'r'], ['Descuadre', 'r'], 'Estado'];
        $rows = $sessions->map(function ($s) use ($detailed) {
            $mismatch = ReportService::closureMismatch($s);
            $mismatchCell = $mismatch === null ? '-' : $this->money($mismatch);
            $closed = $s->closed_at?->format('d/m/Y H:i') ?? 'En curso';
            $status = $s->closed_at ? 'Cerrada' : 'Abierta';

            return $detailed
                ? [$s->cashRegister?->name ?? '-', $s->opened_at?->format('d/m/Y H:i') ?? '-', $closed,
                    $s->openedByUser?->name ?? '-', $s->closedByUser?->name ?? '-', $this->money($s->opening_amount),
                    $s->closing_amount !== null && $s->closed_at ? $this->money($s->closing_amount) : '-',
                    $this->money($s->income_amount), $this->money($s->expense_amount), $mismatchCell,
                    [(int) $s->cash_movements_count, 'c'], $status]
                : [$s->cashRegister?->name ?? '-', $s->opened_at?->format('d/m/Y H:i') ?? '-', $closed,
                    $this->money($s->income_amount), $this->money($s->expense_amount), $mismatchCell, $status];
        });

        return $html . $this->table($head, $rows);
    }

    private function clientsReport(?Company $company, array $f, string $type): string
    {
        $query = $this->reports->clientsQuery($company, $f);
        $totals = $this->reports->clientsTotals($query);
        $clients = $query->get();

        $html = $this->cards([
            ['Clientes', $totals['clients'], '#0d6efd'],
            ['Préstamos', $totals['loans'], '#6f42c1'],
            ['Total prestado', $this->moneyText($totals['borrowed']), '#198754'],
            ['Total pagado', $this->moneyText($totals['paid']), '#0dcaf0'],
        ]);

        $detailed = $type === 'detailed';
        $head = $detailed
            ? ['Cliente', 'Documento', 'Teléfono', 'Email', 'Dirección', ['Préstamos', 'c'], ['Activos', 'c'], ['En mora', 'c'],
                ['Total prestado', 'r'], ['Total pagado', 'r'], ['Pendiente', 'r'], 'Estado']
            : ['Cliente', 'Documento', ['Préstamos', 'c'], ['Total prestado', 'r'], ['Total pagado', 'r'], ['Pendiente', 'r']];
        $rows = $clients->map(function ($c) use ($detailed) {
            $borrowed = (float) ($c->total_borrowed ?? 0);
            $paid = (float) ($c->total_paid ?? 0);
            $money = [$this->money($borrowed), $this->money($paid), $this->money(max(0, $borrowed - $paid))];

            return $detailed
                ? [$c->name, $c->id_number ?: '-', $c->phone ?: '-', $c->email ?: '-', $c->address ?: '-',
                    [(int) $c->loans_count, 'c'], [(int) $c->active_loans_count, 'c'], [(int) $c->overdue_loans_count, 'c'],
                    ...$money, $c->active ? 'Activo' : 'Inactivo']
                : [$c->name, $c->id_number ?: '-', [(int) $c->loans_count, 'c'], ...$money];
        });

        return $html . $this->table($head, $rows);
    }

    private function warehousesReport(?Company $company, string $type): string
    {
        $warehouses = $this->reports->warehouses($company);
        $totals = $this->reports->warehousesTotals($warehouses);

        $html = $this->cards([
            ['Almacenes', $totals['warehouses'], '#0d6efd'],
            ['En custodia', $totals['total_collateral'], '#6f42c1'],
            ['En mora (retenidas)', $totals['total_stored'], '#fd7e14'],
            ['Para la venta', $totals['total_sellable'], '#dc3545'],
        ]);

        $detailed = $type === 'detailed';
        $head = $detailed
            ? ['Almacén', 'Código', 'Sucursal', 'Ubicación', ['Retenidas', 'c'], ['En mora', 'c'], ['Para venta', 'c'], ['Total', 'c'], 'Estado']
            : ['Almacén', 'Sucursal', ['En custodia', 'c'], ['Para venta', 'c'], 'Estado'];
        $rows = $warehouses->map(fn ($w) => $detailed
            ? [$w->name, $w->code ?: '-', $w->primaryBranch?->name ?? '-', $w->location ?: '-', [$w->retained_count, 'c'],
                [$w->stored_count, 'c'], [$w->sellable_count, 'c'], [$w->collateral_count, 'c'], $w->active ? 'Activo' : 'Inactivo']
            : [$w->name, $w->primaryBranch?->name ?? '-', [$w->collateral_count, 'c'], [$w->sellable_count, 'c'],
                $w->active ? 'Activo' : 'Inactivo']);

        return $html . $this->table($head, $rows);
    }

    /* ── Piezas HTML ─────────────────────────────────────────── */

    private function header(string $title, string $subtitle, array $f): string
    {
        $labels = ['date_from' => 'Desde', 'date_to' => 'Hasta', 'status' => 'Estado', 'q' => 'Búsqueda',
            'payment_method' => 'Método', 'type' => 'Tipo', 'category' => 'Categoría'];
        $parts = [];
        foreach ($labels as $key => $label) {
            if (isset($f[$key]) && trim((string) $f[$key]) !== '') {
                $parts[] = $label . ': ' . $f[$key];
            }
        }

        $sub = $subtitle . ($parts ? ' · ' . implode(' · ', $parts) : '');
        if ($this->excel) {
            // En Excel cada dato en su propia fila (el lector une los bloques de una misma celda).
            return '<table><tr><td><b>' . e($title) . '</b></td></tr><tr><td>' . e($sub) . '</td></tr>'
                . '<tr><td>Generado: ' . now()->format('d/m/Y H:i') . '</td></tr></table>';
        }

        return '<table class="hdr"><tr><td><h2>' . e($title) . '</h2><div class="sub">' . e($sub) . '</div></td>'
            . '<td class="gen">Generado: ' . now()->format('d/m/Y H:i') . '</td></tr></table>';
    }

    /** @param array<int, array{0:string,1:mixed,2:string}> $cards */
    private function cards(array $cards): string
    {
        if ($this->excel) {
            $html = '<table><tr>';
            foreach ($cards as [$label]) {
                $html .= '<td><b>' . e($label) . '</b></td>';
            }
            $html .= '</tr><tr>';
            foreach ($cards as [, $value]) {
                $html .= '<td>' . e((string) $value) . '</td>';
            }

            return $html . '</tr></table>';
        }

        $html = '<table class="cards"><tr>';
        foreach ($cards as [$label, $value, $color]) {
            $html .= '<td><div class="v" style="color:' . $color . '">' . e((string) $value) . '</div>'
                . '<div class="l">' . e($label) . '</div></td>';
        }

        return $html . '</tr></table>';
    }

    /**
     * Celdas: texto, o [valor, alineación] con 'r' (derecha) o 'c' (centro); en el pie, [texto, colspan].
     * Los montos llegan ya como celdas de self::money().
     */
    private function table(array $head, Collection $rows, ?array $foot = null): string
    {
        $thead = '<thead><tr>';
        foreach ($head as $h) {
            [$text, $align] = is_array($h) ? $h : [$h, ''];
            $thead .= '<th' . ($align ? ' class="' . $align . '"' : '') . '>' . e($text) . '</th>';
        }
        $thead .= '</tr></thead>';

        if ($this->pdf && $rows->count() > self::PDF_MAX_ROWS) {
            throw new ReportTooLargeException($rows->count());
        }
        $chunks = $this->pdf ? $rows->chunk(self::PDF_ROWS_PER_TABLE) : collect([$rows]);
        if ($chunks->isEmpty()) {
            $chunks = collect([collect()]);
        }

        $html = '';
        foreach ($chunks->values() as $i => $chunk) {
            $html .= '<table class="data">' . $thead . '<tbody>';
            if ($rows->isEmpty()) {
                $html .= '<tr><td colspan="' . count($head) . '" class="c">Sin registros para los filtros elegidos.</td></tr>';
            }
            foreach ($chunk as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    [$value, $align] = is_array($cell) ? $cell : [$cell, ''];
                    $html .= '<td' . ($align ? ' class="' . $align . '"' : '') . '>' . e((string) $value) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            if ($foot && $i === $chunks->count() - 1) {
                $html .= '<tfoot><tr>';
                foreach ($foot as $cell) {
                    if (is_array($cell) && is_int($cell[1])) {
                        $html .= '<td colspan="' . $cell[1] . '">' . e((string) $cell[0]) . '</td>';
                    } else {
                        [$value, $align] = is_array($cell) ? $cell : [$cell, ''];
                        $html .= '<td' . ($align ? ' class="' . $align . '"' : '') . '>' . e((string) $value) . '</td>';
                    }
                }
                $html .= '</tr></tfoot>';
            }
            $html .= '</table>';
        }

        return $html;
    }

    /** Celda de monto: en Excel el número (para poder sumarlo); si no, con la moneda de la empresa. */
    private function money($amount): array
    {
        $amount = round((float) $amount, 2);

        return [$this->excel ? $amount : Currency::format($amount, $this->company), 'r'];
    }

    private function moneyText($amount): string
    {
        return Currency::format((float) $amount, $this->company);
    }

    private function document(string $title, string $body, bool $print): string
    {
        $css = 'body{font-family:"DejaVu Sans",Arial,Helvetica,sans-serif;font-size:11px;color:#212529;margin:' . ($print ? '24px' : '0') . ';}'
            . 'table.hdr{width:100%;border-bottom:2px solid #0d6efd;margin-bottom:14px;}'
            . 'table.hdr h2{margin:0;color:#0d6efd;font-size:18px;} table.hdr .sub{color:#6c757d;font-size:11px;margin-top:3px;}'
            . 'table.hdr .gen{text-align:right;color:#6c757d;font-size:10px;vertical-align:bottom;}'
            . 'table.cards{width:100%;border-collapse:separate;border-spacing:8px 0;margin:0 -8px 14px;}'
            . 'table.cards td{border:1px solid #e3e8ee;border-radius:6px;padding:8px;text-align:center;}'
            . 'table.cards .v{font-size:15px;font-weight:bold;} table.cards .l{font-size:9px;color:#6c757d;margin-top:2px;}'
            . 'table.data{width:100%;border-collapse:separate;border-spacing:0;border-top:1px solid #dee2e6;border-left:1px solid #dee2e6;}'
            . 'table.data th,table.data td{border-right:1px solid #dee2e6;border-bottom:1px solid #dee2e6;padding:4px 6px;vertical-align:top;}'
            . 'table.data th{background:#eef3f8;text-align:left;font-size:10px;} table.data tr:nth-child(even) td{background:#f8f9fa;}'
            . 'table.data tfoot td{font-weight:bold;background:#eef3f8;} .r{text-align:right;white-space:nowrap;} .c{text-align:center;}'
            . 'table.data td:first-child{white-space:nowrap;}'
            . '@media print{body{margin:10px;}}';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . e($title) . '</title><style>' . $css . '</style></head><body>'
            . $body . ($print ? '<script>window.onload=function(){window.print();}</script>' : '') . '</body></html>';
    }

    private function toXlsx(string $html, string $title): string
    {
        $spreadsheet = (new HtmlReader())->loadFromString($html);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title), 0, 31));
        foreach (range(1, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn())) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpt');
        try {
            (new Xlsx($spreadsheet))->save($tmp);

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
            $spreadsheet->disconnectWorksheets();
        }
    }
}
