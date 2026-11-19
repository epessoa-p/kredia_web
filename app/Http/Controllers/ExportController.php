<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

/**
 * Exportaciones de la web (?format=pdf|excel&type=summary|detailed). El contenido lo arma ReportExporter con las
 * mismas consultas que la pantalla (ReportService): "pdf" abre la página para imprimir / guardar como PDF y
 * "excel" descarga un .xlsx.
 */
class ExportController extends Controller
{
    public function __construct(private ReportExporter $exporter)
    {
    }

    public function loans(Request $request)
    {
        return $this->export('loans', $request);
    }

    public function payments(Request $request)
    {
        return $this->export('payments', $request);
    }

    public function clients(Request $request)
    {
        return $this->export('clients', $request);
    }

    public function warehouses(Request $request)
    {
        return $this->export('warehouses', $request);
    }

    public function movements(Request $request)
    {
        return $this->export('movements', $request);
    }

    public function closures(Request $request)
    {
        return $this->export('closures', $request);
    }

    public function clientLoans(Request $request, Client $client)
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $client->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        $format = $request->get('format') === 'excel' ? 'excel' : 'pdf';
        [$filename, $content] = $this->exporter->clientLoans($client, (string) $request->get('type', 'summary'), $format);

        return $format === 'excel' ? $this->xlsxResponse($filename, $content) : response($content);
    }

    private function export(string $report, Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();
        if (!$user->is_super_admin && !$company) {
            abort(403);
        }

        $filters = $request->only(ReportService::FILTERS);
        // La lista de Préstamos filtra la sucursal como sucursal_id (0 = todas).
        if (empty($filters['branch_id']) && $request->integer('sucursal_id') > 0) {
            $filters['branch_id'] = $request->integer('sucursal_id');
        }
        // En la web "type" es el detalle del archivo (summary|detailed), así que el filtro ingreso/egreso de
        // movimientos llega aparte, como movement_type.
        $filters['type'] = $request->get('movement_type');
        $type = $request->get('type') === 'detailed' ? 'detailed' : 'summary';

        if ($request->get('format') === 'excel') {
            [$filename, $content] = $this->exporter->xlsx($report, $company, $filters, $type);

            return $this->xlsxResponse($filename, $content);
        }

        return response($this->exporter->printPage($report, $company, $filters, $type));
    }

    private function xlsxResponse(string $filename, string $content)
    {
        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
