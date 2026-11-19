<?php

namespace App\Services\Reports;

use RuntimeException;

/** El reporte tiene demasiadas filas para armarlo en PDF. */
class ReportTooLargeException extends RuntimeException
{
    public function __construct(public readonly int $rows)
    {
        parent::__construct('El reporte tiene ' . number_format($rows, 0, ',', '.') . ' filas: es demasiado grande para PDF (máximo '
            . number_format(ReportExporter::PDF_MAX_ROWS, 0, ',', '.') . '). Filtra por fechas o descárgalo en Excel.');
    }
}
