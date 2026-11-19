@extends('layouts.app')

@section('title', 'Préstamo PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT))

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<style>
    .cs-client-link { border-radius: 10px; padding: 4px; margin: -4px; transition: background-color .15s ease; }
    a:hover .cs-client-link { background-color: rgba(13, 110, 253, .06); }
    .loan-stat { border-left: 3px solid; padding-left: 12px; }
    .loan-stat.primary { border-color: #0d6efd; }
    .loan-stat.success { border-color: #198754; }
    .loan-stat.danger { border-color: #dc3545; }
    .loan-stat.warning { border-color: #ffc107; }
    .loan-stat .stat-label { font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: .03em; color: #6c757d; }
    .loan-stat .stat-value { font-size: var(--fs-lg); font-weight: 700; }

    /* ── Improved tab styling ─────────────────── */
    #loanActionTabs {
        background: var(--mod-color);
        border-bottom: none;
        gap: .25rem;
        padding: .3rem .35rem 0;
        border-radius: .6rem .6rem 0 0;
    }
    #loanActionTabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: rgba(255, 255, 255, .86);
        font-weight: 600;
        font-size: var(--fs-base);
        padding: .65rem 1.1rem;
        border-radius: .45rem .45rem 0 0;
        transition: all .2s ease;
    }
    #loanActionTabs .nav-link:hover {
        color: #fff;
        background: rgba(255, 255, 255, .16);
        border-bottom-color: transparent;
    }
    #loanActionTabs .nav-link.active {
        color: var(--mod-color);
        background: #fff;
        border-bottom-color: transparent;
        box-shadow: 0 -1px 0 rgba(255, 255, 255, .7);
    }
    #loanActionTabs .nav-link i { opacity: .9; }
    #loanActionTabs .nav-link.active i { opacity: 1; }

    /* Gallery thumbnails */
    .loan-gallery-thumb {
        width: 80px; height: 80px; object-fit: cover;
        border-radius: 8px; border: 2px solid #e9ecef;
        cursor: pointer; transition: border-color .2s;
    }
    .loan-gallery-thumb:hover { border-color: #0d6efd; }

    .client-avatar {
        width: 62px;
        height: 62px;
        border-radius: 18px;
        overflow: hidden;
        background: #eef5ff;
        border: 1px solid rgba(13, 110, 253, .12);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .client-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .client-avatar i {
        font-size: var(--fs-lg);
        color: #0d6efd;
    }

    /* Interest period cards */
    .interest-period-card {
        border-radius: 12px;
        border: 1px solid #e9ecef;
        transition: box-shadow .2s;
    }
    .interest-period-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    /* Interés eliminado: se atenúa la tarjeta, pero el dato de quién lo eliminó queda legible */
    .interest-period-card--trashed { background: #fafafa; }
    .interest-period-card--trashed > *:not(.interest-deleted-info) { opacity: .55; }
    .interest-deleted-info {
        font-size: var(--fs-xs); line-height: 1.5;
        background: #fff5f5; border: 1px dashed #f1aeb5; border-radius: 8px;
        padding: 6px 8px; color: #495057;
    }
    .interest-deleted-info i { color: #dc3545; }
    .interest-restored-info {
        font-size: var(--fs-xs); color: #166534;
        background: #f0fdf4; border: 1px dashed #86efac; border-radius: 8px; padding: 4px 8px;
    }
    .interest-period-card .period-badge {
        width: 36px; height: 36px;
        border-radius: 50%; display: flex;
        align-items: center; justify-content: center;
        font-weight: 700; font-size: var(--fs-base);
    }

    /* Signature pads */
    .signature-canvas {
        width: 100%; height: 120px;
        border: 1px dashed #adb5bd;
        border-radius: 8px;
        background: #fff;
        cursor: crosshair;
    }

    /* Penalty alert */
    .penalty-alert { animation: pulse-border 2s infinite; }
    @keyframes pulse-border {
        0%, 100% { border-color: #dc3545; }
        50% { border-color: #ffc107; }
    }

    .print-template-control {
        min-width: 220px;
    }

    .receipt-dropzone {
        border: 1px dashed #adb5bd;
        border-radius: 8px;
        background: #f8f9fa;
        padding: .5rem .75rem;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s ease, background-color .2s ease;
    }

    .receipt-dropzone:hover,
    .receipt-dropzone.is-dragover {
        border-color: #0d6efd;
        background: #eaf2ff;
    }

    .receipt-dropzone .dropzone-file {
        display: block;
        font-size: var(--fs-xs);
        color: #0d6efd;
        margin-top: .2rem;
    }
</style>
@endpush

@section('page')
@php
    $currentCapital = $loan->getCurrentCapital();
    $unpaidInterest = $loan->getUnpaidInterestTotal();
    $liquidationAmount = $loan->getLiquidationAmount();
    $defaultInterestTemplateId = optional(($interestPrintTemplates ?? collect())->firstWhere('paper_size', 'pequeno'))->id
        ?? optional(($interestPrintTemplates ?? collect())->first())->id;
    $loanActionTemplates = ($loanActionPrintTemplates ?? collect())->isNotEmpty()
        ? ($loanActionPrintTemplates ?? collect())
        : (($interestPrintTemplates ?? collect())->isNotEmpty() ? ($interestPrintTemplates ?? collect()) : ($liquidationPrintTemplates ?? collect()));

    $amortizePrintTemplates = $loanActionTemplates->filter(function ($tpl) {
        $name = mb_strtolower((string) ($tpl->name ?? ''));
        $desc = mb_strtolower((string) ($tpl->description ?? ''));
        $type = mb_strtolower((string) ($tpl->type ?? ''));
        return str_contains($name, 'amortiz') || str_contains($desc, 'amortiz') || $type === 'amortizacion';
    });
    if ($amortizePrintTemplates->isEmpty()) {
        $amortizePrintTemplates = $loanActionTemplates;
    }

    $liquidatePrintTemplates = $loanActionTemplates->filter(function ($tpl) {
        $name = mb_strtolower((string) ($tpl->name ?? ''));
        $desc = mb_strtolower((string) ($tpl->description ?? ''));
        $type = mb_strtolower((string) ($tpl->type ?? ''));
        return str_contains($name, 'liquid') || str_contains($desc, 'liquid') || $type === 'liquidacion';
    });
    if ($liquidatePrintTemplates->isEmpty()) {
        $liquidatePrintTemplates = $loanActionTemplates;
    }

    $penaltyPrintTemplates = $loanActionTemplates->filter(function ($tpl) {
        $name = mb_strtolower((string) ($tpl->name ?? ''));
        $desc = mb_strtolower((string) ($tpl->description ?? ''));
        return str_contains($name, 'multa') || str_contains($desc, 'multa') || str_contains($name, 'penal') || str_contains($desc, 'penal');
    });
    if ($penaltyPrintTemplates->isEmpty()) {
        $penaltyPrintTemplates = $loanActionTemplates;
    }

    $defaultAmortizeTemplateId = optional($amortizePrintTemplates->firstWhere('paper_size', 'pequeno'))->id
        ?? optional($amortizePrintTemplates->first())->id;
    // ── Documento Privado de Compra (generado en PHP) ──────────
    $dpProduct  = $loan->product;
    $dpCategory = $loan->creditCategory;
    $dpClient   = $loan->client;
    $dpCompany  = $loan->company;
    $dpBranch   = $loan->branch;
    $dpDocNum   = str_pad((string) $loan->numero, 6, '0', STR_PAD_LEFT);

    $dpCategoryId = (int) ($dpCategory?->id ?? 0);
    // Cualquier categoría que no sea 1 (Vehículo), 2 (Joyas) ni 3 (Garrafas)
    // se comporta como 4 (Artículos): mismos campos en el contrato.
    $dpNonSpecific = !in_array($dpCategoryId, [
        \App\Models\CreditCategory::CATEGORIA_VEHICULO,
        \App\Models\CreditCategory::CATEGORIA_JOYAS,
        \App\Models\CreditCategory::CATEGORIA_GARRAFAS,
    ], true);

    if ($dpNonSpecific) {
        $dpModelo = (string) ($dpProduct?->item_model ?? '');
    } else {
        $dpModelo = (string) ($dpProduct?->model_year ?? '');
        if (!$dpModelo) $dpModelo = (string) ($dpProduct?->item_model ?? '');
    }

    $dpSerial = (string) ($dpProduct?->serial_number ?? '');

    // Muestra el valor o '-' si está vacío
    $dpDash = fn ($v) => ($v !== null && trim((string) $v) !== '') ? (string) $v : '-';

    // Campos relevantes según la categoría (siempre se muestran; vacío => '-')
    $dpFields = [];
    if ($dpCategoryId === \App\Models\CreditCategory::CATEGORIA_VEHICULO) {
        $dpFields['NOMBRE']   = $dpDash($dpProduct?->name);
        $dpFields['MARCA']    = $dpDash($dpProduct?->brand);
        $dpFields['MODELO']   = $dpDash($dpModelo);
        $dpFields['PLACA']    = $dpDash($dpProduct?->plate);
        $dpFields['CHASIS']   = $dpDash($dpProduct?->chassis);
        $dpFields['DETALLES'] = $dpDash($dpProduct?->description);
    } elseif ($dpCategoryId === \App\Models\CreditCategory::CATEGORIA_JOYAS) {
        $dpFields['NOMBRE']   = $dpDash($dpProduct?->name);
        $dpFields['TIPO']     = $dpDash($dpProduct?->item_type);
        $dpFields['KILATAJE'] = $dpDash($dpProduct?->karat);
        $dpFields['MATERIAL'] = $dpDash($dpProduct?->material_type);
        $dpFields['DETALLES'] = $dpDash($dpProduct?->description);
    } elseif ($dpCategoryId === \App\Models\CreditCategory::CATEGORIA_GARRAFAS) {
        $dpFields['NOMBRE']   = $dpDash($dpProduct?->name);
        $dpFields['DETALLES'] = $dpDash($dpProduct?->description);
    } else {
        // Artículos (4) y cualquier categoría nueva: mismos campos.
        $dpFields['NOMBRE']   = $dpDash($dpProduct?->name);
        $dpFields['MARCA']    = $dpDash($dpProduct?->brand);
        $dpFields['MODELO']   = $dpDash($dpModelo);
        $dpFields['SERIAL']   = $dpDash($dpSerial);
        $dpFields['DETALLES'] = $dpDash($dpProduct?->description);
    }

    $dpPairs = [];
    foreach ($dpFields as $lbl => $val) { $dpPairs[] = [$lbl, $val]; }
    while (count($dpPairs) % 3 !== 0)   { $dpPairs[] = ['', '']; }
    $dpProductTableHtml  = '<table style="width:100%;border-collapse:collapse;margin-bottom:4px;">';
    $dpProductTableHtml .= '<tr><th colspan="3" style="border:1px solid #006600;background:#e8f5e9;color:#006600;text-align:center;padding:4px 6px;font-size:8.5pt;">DESCRIPCIÓN DEL ARTÍCULO</th></tr>';
    foreach (array_chunk($dpPairs, 3) as $dpRow) {
        $dpProductTableHtml .= '<tr>';
        foreach ($dpRow as $dpCell) {
            $dpProductTableHtml .= '<th style="border:1px solid #006600;background:#f0f7f0;color:#006600;width:33.33%;padding:3px 6px;font-size:8pt;">' . e($dpCell[0]) . '</th>';
        }
        $dpProductTableHtml .= '</tr><tr>';
        foreach ($dpRow as $dpCell) {
            $fs = mb_strlen((string) $dpCell[1]) > 40 ? '8pt' : '10.5pt';
            $dpProductTableHtml .= '<td style="border:1px solid #006600;font-size:' . $fs . ';font-weight:bold;padding:5px 7px;">' . e((string) $dpCell[1]) . '</td>';
        }
        $dpProductTableHtml .= '</tr>';
    }
    $dpProductTableHtml .= '</table>';
    $dpUl = fn(string $val, string $w = '120px') =>
        '<span style="border-bottom:1px solid #000;display:inline-block;min-width:' . $w . ';padding:0 3px;">' . e($val) . '</span>';

    $dpAmt    = number_format((float) ($loan->amount ?? 0), 0, ',', '.');
    $dpRecojoRaw = (float) ($currentCapital + $unpaidInterest);
    $dpRecojo = number_format($dpRecojoRaw, 0, ',', '.');
    $dpNumToWords = function(int $n) use (&$dpNumToWords): string {
        if ($n === 0) return 'CERO';
        if ($n < 0)  return 'MENOS ' . $dpNumToWords(-$n);
        $res = '';
        if ($n >= 1000000) {
            $m = (int)($n / 1000000);
            $res .= ($m === 1 ? 'UN MILLÓN' : ($dpNumToWords($m) . ' MILLONES'));
            $n %= 1000000; if ($n > 0) $res .= ' ';
        }
        if ($n >= 1000) {
            $t = (int)($n / 1000);
            $res .= ($t === 1 ? 'MIL' : ($dpNumToWords($t) . ' MIL'));
            $n %= 1000; if ($n > 0) $res .= ' ';
        }
        if ($n >= 100) {
            $hs = ['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS',
                   'SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
            if ($n === 100) { $res .= 'CIEN'; $n = 0; }
            else { $res .= $hs[(int)($n / 100)]; $n %= 100; if ($n > 0) $res .= ' '; }
        }
        if ($n >= 21 && $n <= 29) {
            $vt = ['','VEINTIUNO','VEINTIDÓS','VEINTITRÉS','VEINTICUATRO','VEINTICINCO',
                   'VEINTISÉIS','VEINTISIETE','VEINTIOCHO','VEINTINUEVE'];
            $res .= $vt[$n - 20];
        } elseif ($n >= 30) {
            $ts = ['','','','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
            $os = ['','UNO','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE'];
            $res .= $ts[(int)($n / 10)];
            $o = $n % 10; if ($o > 0) $res .= ' Y ' . $os[$o];
        } elseif ($n > 0) {
            $sm = ['','UNO','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE',
                   'DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE',
                   'DIECIOCHO','DIECINUEVE','VEINTE'];
            $res .= $sm[$n];
        }
        return $res;
    };
    $dpRecojoText = $dpNumToWords((int) round($dpRecojoRaw)) . ' 00/100 BOLIVIANOS';
    $dpStartP = $loan->start_date ? explode('/', $loan->start_date->format('d/m/Y')) : [];
    $dpDay = $dpStartP[0] ?? '___'; $dpMon = $dpStartP[1] ?? '___';
    $dpYr2 = isset($dpStartP[2]) ? substr($dpStartP[2], -2) : '__';

    $documentoPrivadoHtml =
'<table style="width:100%;border-collapse:collapse;margin-bottom:4px;"><tr>
  <td style="width:46%;border:1px solid #006600;padding:6px 8px;vertical-align:top;">
    <div style="color:#006600;font-weight:bold;font-size:11pt;">' . e($dpCompany?->name ?? '') . '</div>
    <div style="color:#006600;font-size:8pt;">'  . e($dpCompany?->address ?? '') . '</div>
    <div style="color:#006600;font-size:8pt;">Cel: ' . e($dpBranch?->phone ?? $dpCompany?->phone ?? '') . '</div>
  </td>
  <td style="border:1px solid #006600;padding:6px 8px;text-align:center;vertical-align:middle;">
    <div style="color:#006600;font-weight:bold;font-size:13pt;line-height:1.2;">DOCUMENTO<br>PRIVADO DE COMPRA</div>
    <div style="text-align:right;margin-top:6px;">
      <span style="color:#006600;font-weight:bold;font-size:8.5pt;">N°&nbsp;</span>
      <span style="color:#cc0000;font-weight:bold;font-size:11pt;border-bottom:1.5px solid #cc0000;">&nbsp;' . $dpDocNum . '&nbsp;</span>
    </div>
  </td>
</tr></table>
<div style="border:1px solid #006600;padding:4px 7px;margin-bottom:4px;font-size:8.5pt;">
  Conste por el presente Documento Privado, que al solo reconocimiento de firma y Rubricas podra ser elevado a instrumento Público suscrito al tenor de las siguientes:
</div>
<div style="border:1px solid #006600;padding:5px 7px;margin-bottom:4px;font-size:8.5pt;">
  <strong style="color:#006600;">PRIMERA.-</strong>
  Yo ' . $dpUl($dpClient?->name ?? '', '220px') . ' con C.I. ' . $dpUl($dpClient?->id_number ?? '', '110px') . '<br>
  Domicilio: ' . $dpUl($dpClient?->address ?? '', '200px') . '&nbsp;&nbsp;&nbsp;Telf.: ' . $dpUl($dpClient?->phone ?? '', '110px') . '<br>
  En la fecha, declaro ser único y exclusivo propietario de las joyas y/o artículos que describo a continuación
</div>
' . $dpProductTableHtml . '
<div style="border:1px solid #006600;padding:5px 7px;margin-bottom:4px;font-size:8.5pt;">
  <strong style="color:#006600;">SEGUNDA.-</strong> <strong>Transferencia de derechos.-</strong>
  Al presente por así convenir a mis intereses, al mandato del <strong>Art. 105 del Código Civil</strong>, de mi libre y espontaneo voluntad, sin que medie violencia error ni dolo de ninguna naturaleza, que pueda invalidar mi consentimiento, transfiero bajo la modalidad de compra con Pacto de Rescate y enajenación perpetua de los bienes especificados en la clausula anterior por el precio libremente convenido y pactado de:
  <div style="text-align:center;font-size:15pt;font-weight:bold;margin:8px 0 2px;border-bottom:1.5px solid #000;padding-bottom:4px;">' . $dpRecojo . ' Bs.</div>
  <div style="text-align:center;font-size:9pt;font-weight:bold;margin:0 0 4px;">(' . $dpRecojoText . ')</div>
</div>
<div style="border:1px solid #006600;padding:5px 7px;margin-bottom:4px;font-size:8.5pt;">
  <strong style="color:#006600;">TERCERA.-</strong> Por común acuerdo de partes, se conviene que el presente contrato de venta se sujeta a la modalidad de venta con pacto de rescate, con sujeción a lo determinado en el <strong>Art. 641 del código civil</strong> por lo que <strong>el vendedor puede recuperar el derecho propietario de los bienes en el Término máximo de los 30 Días, plazo a partir de la fecha de suscripción del presente contrato</strong>, Vencido el mismo, el comprador no tendrá ninguna obligación de devolución ya que por el solo vencimiento del término se formaliza y convalida la venta sin ningún reclamo posterior:
</div>
<div style="border:1px solid #006600;padding:5px 7px;margin-bottom:4px;font-size:8.5pt;">
  <strong style="color:#006600;">CUARTA.-</strong> nosotros <strong>' . e($dpCompany?->name ?? '') . '</strong> en calidad de comprador y el(la) Señor(a): ' . $dpUl($dpClient?->name ?? '', '200px') . '<br><br>
  En calidad de comprador(a). damos nuestra conformidad con toda y cada una de las cláusulas que anteceden por el resultado de lo pactado y por convenir a nuestros intereses.
</div>
<table style="width:100%;border-collapse:collapse;margin-bottom:8px;"><tr>
  <td style="width:50%;border:1px solid #006600;padding:5px;text-align:center;font-size:8pt;"><strong>NOTA - RECUERDE</strong><br>SE LE RUEGA TOMAR EN CUENTA<br><strong>LA FECHA DE VENCIMIENTO</strong><br>No se aceptan reclamos posteriores</td>
  <td style="width:50%;border:1px solid #006600;padding:5px;text-align:center;font-size:8pt;font-weight:bold;">SEÑOR: CLIENTE<br>EL RECOJO ES PERSONAL<br>CON CARNET IDENTIDAD</td>
</tr></table>
<div style="font-size:9pt;margin-bottom:18px;">
  Santa Cruz, <span style="border-bottom:1px solid #000;display:inline-block;min-width:22px;text-align:center;">' . $dpDay . '</span> de
  <span style="border-bottom:1px solid #000;display:inline-block;min-width:55px;text-align:center;">' . $dpMon . '</span> del 20
  <span style="border-bottom:1px solid #000;display:inline-block;min-width:22px;text-align:center;">' . $dpYr2 . '</span>
</div>
<table style="width:100%;border-collapse:collapse;"><tr>
  <td style="border:none;width:50%;text-align:center;padding-top:28px;vertical-align:bottom;">
    <div style="border-top:1px solid #000;display:inline-block;width:180px;margin-bottom:3px;"></div><br>
    <strong>PROPIETARIO / PRESTATARIO</strong><br>
    <div style="margin-top:5px;font-size:8.5pt;">C.I. ' . $dpUl($dpClient?->id_number ?? '', '120px') . '</div>
  </td>
  <td style="border:none;width:50%;text-align:center;padding-top:28px;vertical-align:bottom;">
    <div style="border-top:1px solid #000;display:inline-block;width:180px;margin-bottom:3px;"></div><br>
    <strong>PRESTAMOS &ldquo;' . e($dpCompany?->name ?? '') . '&rdquo;</strong><br>
    <div style="margin-top:5px;font-size:8.5pt;">C.I. <span style="border-bottom:1px solid #000;display:inline-block;min-width:120px;padding:0 3px;"></span></div>
  </td>
</tr></table>';

    $defaultContractContent = $loan->contract?->content ?: $documentoPrivadoHtml;
    $defaultLiquidationTemplateId = optional($liquidatePrintTemplates->firstWhere('paper_size', 'pequeno'))->id
        ?? optional($liquidatePrintTemplates->first())->id;
    $defaultPenaltyTemplateId = optional($penaltyPrintTemplates->firstWhere('paper_size', 'pequeno'))->id
        ?? optional($penaltyPrintTemplates->first())->id;
    $statusColors = \App\Models\Loan::STATUS_COLORS;
    $statusLabels = \App\Models\Loan::STATUS_LABELS;
    $penaltyLabels = [
        'normal' => 'Normal', 'overdue' => 'En mora', 'grace' => 'Periodo de gracia', 'defaulted' => 'Incumplido',
    ];
    $fromContext = request('from');
    $backUrl = route('loans.index');
    $backLabel = 'Volver a préstamos';
    if ($fromContext === 'client' && $loan->client_id) {
        $backUrl = route('clients.show', $loan->client_id);
        $backLabel = 'Volver al cliente';
    }
    $isReadOnlyReprintMode = $loan->status === \App\Models\Loan::STATUS_FINISHED;
    $amortizationCount = ($loan->amortizations ?? collect())->count();
    $lastLiquidationPayment = ($loan->payments ?? collect())
        ->filter(function ($payment) {
            return ($payment->payment_type ?? null) === 'mixed';
        })
        ->sortByDesc(function ($payment) {
            return $payment->payment_date ?? $payment->created_at;
        })
        ->first();
    $liquidationRecordCapital = (float) ($lastLiquidationPayment?->capital ?? $currentCapital);
    $liquidationRecordInterest = (float) ($lastLiquidationPayment?->interest ?? $unpaidInterest);
    $liquidationRecordAmount = (float) ($lastLiquidationPayment?->amount ?? $liquidationAmount);
    $oldestPendingInterestId = optional(($loan->interestPayments ?? collect())->filter(fn($ip) => !$ip->trashed())->where('status', '!=', 'paid')->sortBy('period_number')->first())->id;
    $oldestPendingInterestPeriod = optional(($loan->interestPayments ?? collect())->filter(fn($ip) => !$ip->trashed())->where('status', '!=', 'paid')->sortBy('period_number')->first())->period_number;
    $isReadOnly = $loan->trashed();
    // Un préstamo INCUMPLIDO queda de solo lectura: venció la mora y la prenda
    // pasó a estar disponible para la venta; no se registran más pagos.
    $isDefaulted = $loan->status === \App\Models\Loan::STATUS_DEFAULTED;
    $actionsLocked = $isReadOnlyReprintMode || $isReadOnly || $isDefaulted;
@endphp

{{-- Header --}}
<x-page-header title="Préstamo PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}" icon="cash-coin" :subtitle="$loan->client?->name">
    <x-slot:meta>
        <x-status-badge :status="$loan->status" />
        @if($loan->penalty_status !== 'normal')
            <x-status-badge tone="danger">{{ $penaltyLabels[$loan->penalty_status] ?? $loan->penalty_status }}</x-status-badge>
        @endif
    </x-slot:meta>
        @php
            $canEditPermission = auth()->user()->hasPermissionInCompany('loans.edit', auth()->user()->getCurrentCompany());
            // Motivo por el que no se puede editar (para explicarlo en vez de ocultar el botón).
            $editBlockedReason = match (true) {
                $isDefaulted => 'No se puede editar: el préstamo está incumplido.',
                !in_array($loan->status, \App\Models\Loan::OPEN_STATUSES, true) => 'No se puede editar: el préstamo está ' . mb_strtolower($statusLabels[$loan->status] ?? $loan->status) . '.',
                !($canEdit ?? false) => 'No se puede editar: el préstamo ya tiene pagos, intereses pagados o amortizaciones registrados.',
                default => null,
            };
        @endphp
        @if(!$isReadOnly && $canEditPermission)
            @if($editBlockedReason)
                <span class="d-inline-block" tabindex="0" title="{{ $editBlockedReason }}">
                    <x-btn variant="secondary" icon="lock" disabled style="pointer-events:none;">Editar</x-btn>
                </span>
            @else
                <x-btn variant="outline-primary" icon="pencil-square" :href="route('loans.edit', $loan)">Editar</x-btn>
            @endif
        @endif
        @if(!$loan->trashed() && (auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('loans.delete', auth()->user()->getCurrentCompany())))
        <x-btn variant="outline-danger" icon="trash" data-bs-toggle="modal" data-bs-target="#deleteLoanModal">Eliminar</x-btn>
        @endif
        <x-btn variant="secondary" icon="arrow-left" :href="$backUrl">{{ $backLabel }}</x-btn>
</x-page-header>

{{-- Banner de solo lectura (préstamo eliminado) --}}
@if($isReadOnly)
<div class="alert alert-secondary d-flex align-items-start gap-2 mb-4" style="border-left:4px solid #6c757d;">
    <i class="bi bi-trash3-fill fs-4 text-secondary"></i>
    <div>
        <strong>Préstamo eliminado — vista de solo lectura</strong>
        <div class="small text-muted">No se pueden registrar pagos, amortizaciones, liquidaciones ni ediciones. Solo consulta e impresión.</div>
        @php
            $deletionNote = null;
            if ($loan->notes && \Illuminate\Support\Str::contains($loan->notes, '[Eliminado]')) {
                $deletionNote = trim(\Illuminate\Support\Str::after($loan->notes, '[Eliminado]'));
            }
            $deletedByName = $loan->deletedBy?->personal?->full_name ?? $loan->deletedBy?->name;
        @endphp
        <div class="small mt-1">
            <i class="bi bi-person-badge me-1" style="color:#6f42c1;"></i>
            <span class="fw-semibold">Eliminado por:</span>
            {{ $deletedByName ?? 'usuario no registrado' }}
            @if($loan->deleted_at)
                · {{ $loan->deleted_at->format('d/m/Y H:i') }}
            @endif
        </div>
        @if($deletionNote)
            <div class="small mt-1"><span class="fw-semibold">Motivo:</span> {{ $deletionNote }}</div>
        @endif
    </div>
</div>
@endif

{{-- Banner de solo lectura (préstamo incumplido) --}}
@if($isDefaulted && !$isReadOnly)
<div class="alert alert-danger d-flex align-items-start gap-2 mb-4" style="border-left:4px solid #dc3545;">
    <i class="bi bi-lock-fill fs-4 text-danger"></i>
    <div>
        <strong>Préstamo incumplido — vista de solo lectura</strong>
        <div class="small">Venció el periodo de mora. La prenda pasó a estar disponible para la venta y ya no se registran pagos, amortizaciones ni liquidaciones. Solo consulta e impresión.</div>
    </div>
</div>
@endif

{{-- Penalty alert --}}
@if($loan->penalty_status !== 'normal')
<div class="alert alert-danger penalty-alert d-flex align-items-center gap-2 mb-4" style="border-width:2px;">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div>
        <strong>Préstamo en {{ $penaltyLabels[$loan->penalty_status] ?? 'alerta' }}</strong>
        @if((float) $loan->penalty_amount > 0)
            — Multa aplicada: <strong>{{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}</strong>
        @endif
        @if($loan->penalty_status === 'defaulted')
            <br><small class="text-muted">El producto pasa a estado de venta si no se liquida.</small>
        @endif
    </div>
</div>
@endif

{{-- Stats bar --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="loan-stat primary">
            <div class="stat-label">Capital inicial</div>
            <div class="stat-value text-primary">{{ $currencySymbol }} {{ number_format((float) $loan->amount, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="loan-stat warning">
            <div class="stat-label">Capital vigente</div>
            <div class="stat-value text-warning">{{ $currencySymbol }} {{ number_format($currentCapital, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="loan-stat success">
            <div class="stat-label">Total pagado</div>
            <div class="stat-value text-success">{{ $currencySymbol }} {{ number_format((float) $loan->total_paid, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="loan-stat danger">
            <div class="stat-label">Para liquidar</div>
            <div class="stat-value text-danger">{{ $currencySymbol }} {{ number_format($liquidationAmount, 2) }}</div>
        </div>
    </div>
</div>

{{-- Detail cards --}}
<div class="row g-4 mb-4">
    {{-- Client info --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-fill text-mod me-1"></i> Cliente</h6>
                    @if($loan->client)
                        <x-btn variant="outline-primary" icon="box-arrow-up-right" :href="route('clients.show', $loan->client)" title="Ir a la ficha del cliente">Ver cliente</x-btn>
                    @endif
                </div>
                @if($loan->client)
                    <a href="{{ route('clients.show', $loan->client) }}" class="text-decoration-none text-reset">
                    <div class="d-flex align-items-start gap-3 mb-3 cs-client-link">
                        <div class="client-avatar bg-light rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                            @if($loan->client->photo_url)
                                <img src="{{ $loan->client->photo_url }}" alt="{{ $loan->client->name }}" class="client-avatar-img">
                            @else
                                <i class="bi bi-person-fill text-primary"></i>
                            @endif
                        </div>
                        <div>
                            <div class="mb-1"><strong>{{ $loan->client->name }}</strong> <i class="bi bi-chevron-right text-primary fs-xs" ></i></div>
                            <div class="text-muted small">{{ $loan->client->email ?? $loan->client->phone ?? 'Cliente registrado' }}</div>
                        </div>
                    </div>
                    </a>
                    <div class="small text-muted mb-1"><i class="bi bi-card-text me-1"></i> {{ $loan->client->id_number ?? '-' }}</div>
                    <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> {{ $loan->client->phone ?? '-' }}</div>
                    <div class="small text-muted mb-1"><i class="bi bi-envelope me-1"></i> {{ $loan->client->email ?? '-' }}</div>
                    <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i> {{ $loan->client->address ?? '-' }}</div>
                @else
                    <p class="text-muted small mb-0">Sin cliente registrado.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Loan details --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-wallet2 text-success me-1"></i> Condiciones</h6>
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-muted">Producto:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->product?->name ?? '-' }}</div>
                    @if($loan->product?->code)
                        <div class="col-6"><span class="text-muted">Código producto:</span></div>
                        <div class="col-6 fw-semibold">{{ $loan->product->code }}</div>
                    @endif
                    <div class="col-6"><span class="text-muted">Categoría:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->creditCategory?->name ?? '-' }}</div>
                    <div class="col-6"><span class="text-muted">Tasa de interés:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->interest_rate }}%</div>
                    <div class="col-6"><span class="text-muted">Periodicidad:</span></div>
                    <div class="col-6 fw-semibold">{{ ucfirst($loan->interest_period ?? 'Mensual') }}</div>
                    <div class="col-6"><span class="text-muted">Plazo límite:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->term_months }} meses</div>
                    <div class="col-6"><span class="text-muted">Interés por periodo:</span></div>
                    <div class="col-6 fw-semibold text-info">{{ $currencySymbol }} {{ number_format($loan->calculatePeriodInterest(), 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Status / dates --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-calendar3 text-warning me-1"></i> Información</h6>
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-muted">Inicio:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->start_date?->format('d/m/Y') ?? '-' }}</div>
                    <div class="col-6"><span class="text-muted">Vence:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->end_date?->format('d/m/Y') ?? '-' }}</div>
                    <div class="col-6"><span class="text-muted">Creado por:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->createdBy?->name ?? '-' }}</div>
                    <div class="col-6"><span class="text-muted">Fecha creación:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->created_at?->format('d/m/Y H:i') }}</div>
                    @if($loan->cashRegister)
                    <div class="col-6"><span class="text-muted">Desembolsado desde:</span></div>
                    <div class="col-6 fw-semibold">{{ $loan->cashRegister->name }} · Sesión #{{ $loan->cash_register_session_id }}</div>
                    @endif
                    @php $collateralStatus = $loan->collateral->first()?->status; @endphp
                    <div class="col-6"><span class="text-muted">Prenda:</span></div>
                    <div class="col-6">
                        @if($collateralStatus === 'retained')
                            <span class="badge bg-info">En préstamo</span>
                        @elseif($collateralStatus === 'stored')
                            <span class="badge bg-warning text-dark">En retención (mora)</span>
                        @elseif($collateralStatus === 'sellable')
                            <span class="badge bg-success">Disponible</span>
                        @elseif($collateralStatus === 'released')
                            <span class="badge bg-success">Liberada</span>
                        @elseif($collateralStatus === 'sold')
                            <span class="badge bg-dark">Vendida</span>
                        @else
                            <span class="badge bg-secondary">Sin prenda</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Photos gallery --}}
@if($loan->images->count())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-images text-mod me-1"></i> Fotos del préstamo ({{ $loan->images->count() }})</h6>
        <div class="d-flex flex-wrap gap-2">
            @foreach($loan->images as $img)
                <a href="{{ asset('storage/' . $img->path) }}" target="_blank">
                    <img src="{{ asset('storage/' . $img->path) }}" alt="{{ $img->original_name }}" class="loan-gallery-thumb" title="{{ $img->original_name }}">
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Payments history --}}
@if($loan->payments->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-receipt text-info me-1"></i> Historial de pagos ({{ $loan->payments->count() }})</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Capital</th>
                        <th>Interés</th>
                        <th>Registrado por</th>
                        <th>Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loan->payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                            <td>
                                @if($payment->payment_type === 'interest')
                                    <span class="badge bg-info">Interés</span>
                                @elseif($payment->payment_type === 'capital')
                                    <span class="badge bg-primary">Capital</span>
                                @elseif($payment->payment_type === 'penalty')
                                    <span class="badge bg-danger">Multa</span>
                                @else
                                    <span class="badge bg-secondary">Mixto</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $currencySymbol }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $currencySymbol }} {{ number_format((float) ($payment->capital ?? 0), 2) }}</td>
                            <td>{{ $currencySymbol }} {{ number_format((float) ($payment->interest ?? 0), 2) }}</td>
                            <td>
                                @php $paymentBy = $payment->registeredBy?->personal?->full_name ?? $payment->registeredBy?->name; @endphp
                                @if($paymentBy)
                                    <span class="text-muted d-inline-flex align-items-center gap-1 fs-xs"  title="Registrado por">
                                        <i class="bi bi-person-badge" style="color:#6f42c1;"></i>{{ $paymentBy }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $payment->reference ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ── Action Tabs ─────────────────────────────────────────── --}}
@if(in_array($loan->status, array_merge(\App\Models\Loan::OPEN_STATUSES, [\App\Models\Loan::STATUS_FINISHED]), true))
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white pb-0 pt-3 px-3">
        @if($isReadOnlyReprintMode)
            <div class="alert alert-info border-0 shadow-sm py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="small mb-0">
                    <i class="bi bi-printer-fill me-1"></i>
                    <strong>Modo reimpresión activo:</strong> este préstamo está liquidado y los tabs se muestran para reimprimir boletas y recibos.
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Solo lectura</span>
            </div>
        @endif
        <ul class="nav nav-tabs" id="loanActionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-contract" data-bs-toggle="tab" data-bs-target="#pane-contract" type="button" role="tab">
                    <i class="bi bi-file-earmark-text me-1"></i> Contrato
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-interest" data-bs-toggle="tab" data-bs-target="#pane-interest" type="button" role="tab">
                    <i class="bi bi-percent me-1"></i> Interés
                    @php $pendingInterests = $loan->interestPayments->filter(fn($ip) => !$ip->trashed())->where('status', 'pending')->count(); @endphp
                    @if($pendingInterests > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $pendingInterests }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-amortize" data-bs-toggle="tab" data-bs-target="#pane-amortize" type="button" role="tab">
                    <i class="bi bi-graph-down-arrow me-1"></i> Amortizar
                    <span class="badge bg-primary rounded-pill ms-1">{{ $amortizationCount }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-liquidate" data-bs-toggle="tab" data-bs-target="#pane-liquidate" type="button" role="tab">
                    <i class="bi bi-check2-all me-1"></i> Liquidar
                </button>
            </li>
            @if(!$isReadOnlyReprintMode && !$isReadOnly)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-penalty" data-bs-toggle="tab" data-bs-target="#pane-penalty" type="button" role="tab">
                        <i class="bi bi-exclamation-triangle me-1"></i> Multa
                        @if($loan->penalty_status !== 'normal')
                            <span class="badge bg-danger rounded-pill ms-1"><i class="bi bi-exclamation-lg"></i></span>
                        @endif
                    </button>
                </li>
            @endif
        </ul>
    </div>
    <div class="card-body pt-4">
        <div class="tab-content" id="loanActionTabsContent">

            {{-- ═══ Tab: Contrato ═══ --}}
            <div class="tab-pane fade show active" id="pane-contract" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-text text-mod me-1"></i> Contrato del préstamo</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <x-btn variant="success" icon="file-earmark-text-fill" id="btn-doc-privado-prestamo">Documento Privado</x-btn>
                        <x-btn variant="secondary" icon="printer-fill" id="btn-print-thermal-contract" class="contract-quick-print-btn" title="Imprimir comprobante corto para impresora térmica">Térmica rápida</x-btn>
                        <x-btn variant="outline-danger" icon="file-earmark-pdf" id="btn-preview-pdf">PDF</x-btn>
                    </div>
                </div>
                <div class="small text-muted mb-3">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i>
                    <strong>Documento Privado</strong> genera el contrato completo imprimible.
                    <strong>Térmica rápida</strong> imprime un comprobante corto de 80mm.
                </div>

                <form action="{{ route('loans.contract.update', $loan) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <textarea id="contract-content" name="content" class="form-control form-control-sm mb-3" rows="14" {{ $isReadOnly ? 'readonly' : '' }}>{{ old('content', $defaultContractContent) }}</textarea>

                    <div class="row g-3">
                        <div class="col-12">
                            @if(!$isReadOnly)
                                <label class="form-label small fw-semibold">Adjuntar documentos</label>
                                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple accept=".pdf,.jpg,.jpeg,.png">
                            @endif
                            @if($loan->contract?->attachments?->count())
                                <div class="mt-2">
                                    @foreach($loan->contract->attachments as $file)
                                        <span class="badge bg-light text-dark border me-1">
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank">{{ $file->file_name }}</a>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    @if(!$isReadOnly)
                        <x-btn type="submit" icon="save" class="mt-3">Guardar contrato</x-btn>
                    @endif
                </form>

                <form id="contract-export-form" method="POST" target="_blank" class="d-none">
                    @csrf
                    <input type="hidden" name="content" id="contract-export-content">
                </form>
            </div>

            {{-- ═══ Tab: Interés ═══ --}}
            <div class="tab-pane fade" id="pane-interest" role="tabpanel">
                {{-- Resumen --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-percent text-info me-1"></i> Interés del préstamo</h6>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 bg-light text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Capital vigente</small>
                            <div class="fw-bold fs-5 text-primary">{{ $currencySymbol }} {{ number_format($currentCapital, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Tasa interés</small>
                            <div class="fw-bold fs-5">{{ $loan->interest_rate }}%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Interés actual</small>
                            <div class="fw-bold fs-5 text-info">{{ $currencySymbol }} {{ number_format($loan->calculatePeriodInterest(), 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Interés pendiente</small>
                            <div class="fw-bold fs-5 text-danger">{{ $currencySymbol }} {{ number_format($unpaidInterest, 2) }}</div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3"><i class="bi bi-calendar2-check me-1"></i> Periodos de interés</h6>

                {{-- Banner estado de caja --}}
                @if(isset($activeCashSession) && $activeCashSession)
                    <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 mb-3 small">
                        <div><i class="bi bi-safe2-fill me-1"></i> Caja activa: <strong>{{ $activeCashSession->cashRegister?->name ?? '' }}</strong> · Aperturada: {{ $activeCashSession->opened_at?->format('d/m/Y H:i') }}</div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Lista para cobrar</span>
                    </div>
                @elseif(isset($cashSessionError))
                    <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 small">
                        <i class="bi bi-exclamation-octagon me-2 flex-shrink-0"></i>
                        {{ $cashSessionError }}
                    </div>
                @endif

                @if($loan->interestPayments->isEmpty())
                    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i> No hay periodos de interés generados aún.</div>
                @else
                    @php
                        // Periodos con interés activo: los eliminados "al editar" de esos periodos
                        // ya fueron reemplazados y no se muestran (evita duplicados confusos).
                        $activePeriodNumbers = $loan->interestPayments->filter(fn ($i) => !$i->trashed())->pluck('period_number')->all();
                        // Restaurar: con permiso y solo en préstamos activos o en mora.
                        $canRestoreInterest = !$isReadOnly && !$isDefaulted
                            && in_array($loan->status, \App\Models\Loan::OPEN_STATUSES, true)
                            && auth()->user()->hasPermissionInCompany('loans.interest.restore', auth()->user()->getCurrentCompany());
                    @endphp
                    <div class="row g-3">
                        @foreach($loan->interestPayments->sortBy('period_number') as $ip)
                            @continue($ip->trashed() && $ip->deletion_reason === \App\Models\LoanInterestPayment::REASON_LOAN_EDITED && in_array($ip->period_number, $activePeriodNumbers))
                            @php
                                $isPaid = $ip->status === 'paid';
                                $isTrashed = $ip->trashed();
                                $isOverdue = !$isPaid && !$isTrashed && $ip->period_end->lt(now());
                                $remaining = $ip->getRemainingAmount();
                                $isBlockedByOlderPending = !$isPaid && !$isTrashed && $oldestPendingInterestId && (int) $oldestPendingInterestId !== (int) $ip->id;
                            @endphp
                            <div class="col-md-6 col-lg-4">
                                <div
                                    id="interest-card-{{ $ip->id }}"
                                    class="interest-period-card p-3 {{ $isTrashed ? 'border-secondary interest-period-card--trashed' : ($isPaid ? 'border-success' : ($isOverdue ? 'border-danger' : 'border-warning')) }}"
                                    data-period-number="{{ $ip->period_number }}"
                                    data-period-start="{{ $ip->period_start->format('d/m/Y') }}"
                                    data-period-end="{{ $ip->period_end->format('d/m/Y') }}"
                                    data-base-amount="{{ number_format((float) $ip->base_amount, 2, '.', '') }}"
                                    data-interest-rate="{{ number_format((float) $ip->interest_rate, 2, '.', '') }}"
                                    data-interest-amount="{{ number_format((float) $ip->interest_amount, 2, '.', '') }}"
                                    data-paid-amount="{{ number_format((float) $ip->paid_amount, 2, '.', '') }}"
                                    data-remaining="{{ number_format((float) $remaining, 2, '.', '') }}"
                                    data-status="{{ $isTrashed ? 'Eliminado' : ($isPaid ? 'Pagado' : ($isOverdue ? 'Vencido' : 'Pendiente')) }}"
                                    data-payment-method="{{ $ip->payment_method ?? '' }}"
                                    data-reference="{{ $ip->reference ?? '' }}"
                                    data-paid-at="{{ $ip->paid_at ? $ip->paid_at->format('d/m/Y H:i') : '' }}"
                                >
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <div class="period-badge {{ $isTrashed ? 'bg-secondary text-white' : ($isPaid ? 'bg-success text-white' : ($isOverdue ? 'bg-danger text-white' : 'bg-warning text-dark')) }}">
                                            #{{ $ip->period_number }}
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">{{ $ip->period_start->format('d/m/Y') }} — {{ $ip->period_end->format('d/m/Y') }}</div>
                                            <div class="fw-bold">
                                                @if($isTrashed)
                                                    <span class="text-secondary"><i class="bi bi-trash me-1"></i> Eliminado</span>
                                                @elseif($isPaid)
                                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Pagado</span>
                                                @elseif($isOverdue)
                                                    <span class="text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i> Vencido</span>
                                                @else
                                                    <span class="text-warning"><i class="bi bi-clock me-1"></i> Pendiente</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-1 small mb-2">
                                        <div class="col-6 text-muted">Base capital:</div>
                                        <div class="col-6 fw-semibold">{{ $currencySymbol }} {{ number_format((float) $ip->base_amount, 2) }}</div>
                                        <div class="col-6 text-muted">Interés ({{ $ip->interest_rate }}%):</div>
                                        <div class="col-6 fw-semibold">{{ $currencySymbol }} {{ number_format((float) $ip->interest_amount, 2) }}</div>
                                        @if((float) $ip->paid_amount > 0)
                                            <div class="col-6 text-muted">Pagado:</div>
                                            <div class="col-6 fw-semibold text-success">{{ $currencySymbol }} {{ number_format((float) $ip->paid_amount, 2) }}</div>
                                        @endif
                                        @if($isPaid && $ip->paid_at)
                                            <div class="col-6 text-muted">Fecha de pago:</div>
                                            <div class="col-6 fw-semibold text-success">{{ $ip->paid_at->format('d/m/Y H:i') }}</div>
                                        @endif
                                    </div>

                                    @if($isTrashed)
                                        @php
                                            $deleterName = $ip->deletedBy?->personal?->full_name ?? $ip->deletedBy?->name;
                                        @endphp
                                        <div class="interest-deleted-info mb-2">
                                            <div><i class="bi bi-person-x me-1"></i><span class="text-muted">Eliminado por:</span>
                                                <strong>{{ $deleterName ?? 'No registrado' }}</strong>
                                            </div>
                                            <div><i class="bi bi-calendar-x me-1"></i><span class="text-muted">Fecha:</span>
                                                {{ $ip->deleted_at?->format('d/m/Y H:i') }}
                                            </div>
                                            @if($ip->deletion_reason_label)
                                                <div><i class="bi bi-info-circle me-1"></i><span class="text-muted">Motivo:</span>
                                                    {{ $ip->deletion_reason_label }}
                                                </div>
                                            @endif
                                            @if($canRestoreInterest && !in_array($ip->period_number, $activePeriodNumbers))
                                                <form action="{{ route('loans.interest.restore', [$loan, $ip]) }}" method="POST" class="mt-1 interest-restore-form"
                                                      data-period="{{ $ip->period_number }}">
                                                    @csrf
                                                    <input type="hidden" name="from_context" value="{{ $fromContext }}">
                                                    <x-btn variant="outline-success" type="submit" icon="arrow-counterclockwise">Restaurar</x-btn>
                                                </form>
                                            @endif
                                        </div>
                                    @elseif($ip->restored_at)
                                        @php $restorerName = $ip->restoredBy?->personal?->full_name ?? $ip->restoredBy?->name; @endphp
                                        <div class="interest-restored-info mb-2">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                                            Restaurado por <strong>{{ $restorerName ?? '—' }}</strong> · {{ $ip->restored_at->format('d/m/Y H:i') }}
                                        </div>
                                    @endif

                                    <input type="hidden" id="interest-template-{{ $ip->id }}" value="{{ $defaultInterestTemplateId ?? '' }}">
                                    <div class="mb-2">
                                        <x-btn variant="secondary" icon="printer-fill" class="interest-print-btn" data-card-id="interest-card-{{ $ip->id }}" data-template-select="interest-template-{{ $ip->id }}" title="80 mm · térmico">Imprimir ticket</x-btn>
                                    </div>

                                    @if(!$isPaid && !$isTrashed)
                                        @if($isBlockedByOlderPending)
                                            <div class="alert alert-warning small py-2 px-2 mb-2">
                                                <i class="bi bi-lock-fill me-1"></i>
                                                Debes pagar primero el periodo #{{ $oldestPendingInterestPeriod }}.
                                            </div>
                                        @endif
                                        @if(!$actionsLocked)
                                        <form action="{{ route('loans.interest.store', $loan) }}" method="POST" enctype="multipart/form-data" class="mt-2 interest-payment-form" data-period-number="{{ $ip->period_number }}" data-template-select="interest-template-{{ $ip->id }}" data-card-id="interest-card-{{ $ip->id }}">
                                            @csrf
                                            <input type="hidden" name="from_context" value="{{ $fromContext }}">
                                            <input type="hidden" name="interest_payment_id" value="{{ $ip->id }}">
                                            <input type="hidden" name="payment_method" value="efectivo">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">{{ $currencySymbol }}</span>
                                                <input type="number" step="0.01" name="amount" class="form-control form-control-sm" value="{{ number_format($remaining, 2, '.', '') }}" max="{{ $remaining }}" min="0.01" required {{ $isBlockedByOlderPending ? 'disabled' : '' }}>
                                                <x-btn variant="success" type="submit" icon="check-lg" title="Pagar interés" :disabled="!(isset($activeCashSession) && $activeCashSession) || $isBlockedByOlderPending">Pagar</x-btn>
                                            </div>
                                            <input type="text" name="reference" class="form-control form-control-sm mt-1" placeholder="Referencia (opcional)" {{ $isBlockedByOlderPending ? 'disabled' : '' }}>
                                        </form>
                                        @if(auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('loans.interest.delete', auth()->user()->getCurrentCompany()))
                                        <x-btn variant="outline-danger" :block="true" icon="trash"
                                                class="mt-1 ip-delete-btn"
                                                data-period="{{ $ip->period_number }}"
                                                data-action="{{ route('loans.interest.destroy', [$loan, $ip]) }}"
                                                data-from-context="{{ $fromContext }}">Eliminar periodo</x-btn>
                                        @endif
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ═══ Tab: Amortizar ═══ --}}
            <div class="tab-pane fade" id="pane-amortize" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-arrow-down-circle text-mod me-1"></i> Amortización de capital</h6>
                    <div class="d-flex align-items-center gap-2">
                        <input type="hidden" id="print-template-amortize" value="{{ $defaultAmortizeTemplateId ?? '' }}">
                        <x-btn variant="secondary" icon="printer-fill" class="print-action-btn" data-action="amortize" data-template-select="print-template-amortize" title="80 mm · térmico">Imprimir ticket</x-btn>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 bg-light text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Capital inicial</small>
                            <div class="fw-bold fs-5">{{ $currencySymbol }} {{ number_format((float) $loan->amount, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-primary bg-opacity-10 text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Capital vigente</small>
                            <div class="fw-bold fs-5 text-primary">{{ $currencySymbol }} {{ number_format($currentCapital, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-light text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Total amortizado</small>
                            <div class="fw-bold fs-5 text-success">{{ $currencySymbol }} {{ number_format((float) $loan->amount - $currentCapital, 2) }}</div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Al amortizar, el capital vigente se reduce. El próximo periodo de interés se calculará sobre el nuevo capital.
                    <br>Ejemplo: Capital {{ $currencySymbol }} {{ number_format($currentCapital, 2) }} - Amortización {{ $currencySymbol }} 50 = Nuevo capital {{ $currencySymbol }} {{ number_format(max(0, $currentCapital - 50), 2) }} → Interés {{ $loan->interest_rate }}% = {{ $currencySymbol }} {{ number_format(max(0, $currentCapital - 50) * ((float)$loan->interest_rate / 100), 2) }}
                </div>

                @if(!$actionsLocked)
                <form action="{{ route('loans.amortize.store', $loan) }}" method="POST" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <input type="hidden" name="source_action" value="amortize">
                    <input type="hidden" name="from_context" value="{{ $fromContext }}">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Monto a amortizar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">{{ $currencySymbol }}</span>
                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $currentCapital }}" class="form-control form-control-sm" placeholder="0.00" value="{{ old('source_action') === 'amortize' ? old('amount') : '' }}" required>
                        </div>
                        <small class="text-muted">Máx: {{ $currencySymbol }} {{ number_format($currentCapital, 2) }}</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Fecha</label>
                        <input type="date" name="payment_date" class="form-control form-control-sm bg-light" value="{{ old('source_action') === 'amortize' ? old('payment_date', now()->toDateString()) : now()->toDateString() }}" required readonly>
                    </div>
                    <input type="hidden" name="payment_method" value="efectivo">
                    <div class="col-md-3 d-flex align-items-end">
                        <x-btn variant="success" :block="true" type="submit" icon="arrow-down-circle">Amortizar</x-btn>
                    </div>
                    <input type="hidden" name="reference" value="{{ old('source_action') === 'amortize' ? old('reference') : '' }}">
                    <div class="col-md-6">
                        <label class="form-label small">Notas</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Observaciones" value="{{ old('source_action') === 'amortize' ? old('notes') : '' }}">
                    </div>
                </form>
                @else
                    <div class="alert alert-light border small py-2 mb-3">
                        <i class="bi bi-lock-fill me-1 text-secondary"></i> Préstamo liquidado: la amortización está bloqueada. Puedes usar "Imprimir" para reemitir comprobantes.
                    </div>
                @endif

                {{-- Historial de amortizaciones --}}
                @if($loan->amortizations->count() > 0)
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i> Historial de amortizaciones</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Capital antes</th>
                                    <th>Capital después</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loan->amortizations->sortByDesc('created_at') as $amort)
                                    <tr>
                                        <td>{{ $amort->payment_date->format('d/m/Y') }}</td>
                                        <td class="fw-semibold text-success">{{ $currencySymbol }} {{ number_format((float) $amort->amount, 2) }}</td>
                                        <td>{{ $currencySymbol }} {{ number_format((float) $amort->capital_before, 2) }}</td>
                                        <td class="fw-semibold">{{ $currencySymbol }} {{ number_format((float) $amort->capital_after, 2) }}</td>
                                        <td class="text-muted">{{ $amort->reference ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ═══ Tab: Liquidar ═══ --}}
            <div class="tab-pane fade" id="pane-liquidate" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-lock-fill text-danger me-1"></i> Liquidación</h6>
                    <div class="d-flex align-items-center gap-2">
                        <input type="hidden" id="print-template-liquidate" value="{{ $defaultLiquidationTemplateId ?? '' }}">
                        <x-btn variant="secondary" icon="printer-fill" class="print-action-btn" data-action="liquidate" data-template-select="print-template-liquidate" title="80 mm · térmico">Imprimir ticket</x-btn>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 bg-primary bg-opacity-10 text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Capital vigente</small>
                            <div class="fw-bold fs-5 text-primary">{{ $currencySymbol }} {{ number_format($isReadOnlyReprintMode ? $liquidationRecordCapital : $currentCapital, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-warning bg-opacity-10 text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Interés pendiente</small>
                            <div class="fw-bold fs-5 text-warning">{{ $currencySymbol }} {{ number_format($isReadOnlyReprintMode ? $liquidationRecordInterest : $unpaidInterest, 2) }}</div>
                        </div>
                    </div>
                    @if((float) $loan->penalty_amount > 0)
                    <div class="col-md-4">
                        <div class="card border-0 bg-danger bg-opacity-10 text-center p-3">
                            <small class="text-muted text-uppercase fs-xs" >Multa</small>
                            <div class="fw-bold fs-5 text-danger">{{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}</div>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="alert alert-warning py-2 mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <i class="bi bi-calculator me-1"></i>
                            <strong>Total para liquidar:</strong>
                            <span class="fs-5 fw-bold">{{ $currencySymbol }} {{ number_format($liquidationAmount, 2) }}</span>
                        </div>
                        <div class="small">
                            Capital {{ $currencySymbol }} {{ number_format($currentCapital, 2) }}
                            + Interés {{ $currencySymbol }} {{ number_format($unpaidInterest, 2) }}
                            @if((float) $loan->penalty_amount > 0)
                                + Multa {{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}
                            @endif
                        </div>
                    </div>
                </div>

                <form id="form-liquidate" action="{{ route('loans.liquidate.store', $loan) }}" method="POST" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <input type="hidden" name="source_action" value="liquidate">
                    <input type="hidden" name="from_context" value="{{ $fromContext }}">
                    @if(!$actionsLocked)
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Monto</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">{{ $currencySymbol }}</span>
                            <input type="number" name="amount" step="0.01" min="{{ $liquidationAmount }}" class="form-control form-control-sm" value="{{ old('source_action') === 'liquidate' ? old('amount', number_format($liquidationAmount, 2, '.', '')) : number_format($liquidationAmount, 2, '.', '') }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Fecha</label>
                        <input type="date" name="payment_date" class="form-control form-control-sm bg-light" value="{{ old('source_action') === 'liquidate' ? old('payment_date', now()->toDateString()) : now()->toDateString() }}" required readonly>
                    </div>
                    <input type="hidden" name="payment_method" value="efectivo">
                    <input type="hidden" name="reference" value="">
                    <div class="col-md-6">
                        <label class="form-label small">Notas</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Observaciones" value="{{ old('source_action') === 'liquidate' ? old('notes') : '' }}">
                    </div>
                    @else
                    <div class="col-12">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="border rounded p-2 bg-light h-100">
                                    <div class="small text-muted">Monto de referencia</div>
                                    <div class="fw-semibold">{{ $currencySymbol }} {{ number_format($liquidationRecordAmount, 2) }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 bg-light h-100">
                                    <div class="small text-muted">Estado</div>
                                    <div class="fw-semibold text-success">Liquidado</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 bg-light h-100">
                                    <div class="small text-muted">Reimpresión</div>
                                    <div class="fw-semibold">Disponible desde este tab</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="col-12">
                        @if(!$actionsLocked)
                        <x-btn variant="success" type="submit" icon="lock-fill">Liquidar préstamo</x-btn>
                        @else
                        <div class="alert alert-light border small py-2 mb-0">
                            <i class="bi bi-check2-circle me-1 text-success"></i> Este préstamo ya fue liquidado. Usa "Imprimir" para reimprimir la boleta de liquidación.
                        </div>
                        @endif
                    </div>
                </form>

            </div>

            {{-- ═══ Tab: Multa ═══ --}}
            @if(!$isReadOnlyReprintMode && !$isReadOnly)
            <div class="tab-pane fade" id="pane-penalty" role="tabpanel">
                @php
                    $category = $loan->creditCategory;
                    $graceDays = (int) ($category?->penalty_grace_days ?? 0);
                    $penaltyRate = (float) ($category?->penalty_rate ?? 0);
                    $penaltyFixed = (float) ($category?->penalty_fixed_amount ?? 0);
                    $baseLiquidation = $currentCapital + $unpaidInterest;
                    $calculatedPenalty = $penaltyFixed > 0 ? $penaltyFixed : round($baseLiquidation * ($penaltyRate / 100), 2);

                    $termEndDate = $loan->start_date ? $loan->start_date->copy()->addMonths((int) $loan->term_months) : null;
                    // Fechas reales de la lógica de mora (derivadas de los periodos vencidos),
                    // no del plazo nominal: es lo que realmente evalúa checkOverdueStatus().
                    $penaltyReachedAt = $loan->getPenaltyReachedAt();
                    $graceEndDate = $loan->getGraceEndsAt();
                    $pendingPenalty = $loan->getPendingPenalty();
                    $canPayPenalty = auth()->user()->is_super_admin
                        || auth()->user()->hasPermissionInCompany('payments.create', auth()->user()->getCurrentCompany());

                    $penaltyStages = [
                        'normal' => ['label' => 'Normal', 'icon' => 'bi-check-circle-fill', 'color' => 'success', 'desc' => 'Préstamo al día, sin multas.'],
                        'overdue' => ['label' => 'En mora', 'icon' => 'bi-clock-fill', 'color' => 'warning', 'desc' => 'El plazo venció. Período de gracia activo.'],
                        'grace' => ['label' => 'Periodo de gracia', 'icon' => 'bi-hourglass-split', 'color' => 'warning', 'desc' => 'Tiempo extra antes de aplicar multa.'],
                        'defaulted' => ['label' => 'Incumplido', 'icon' => 'bi-exclamation-triangle-fill', 'color' => 'danger', 'desc' => 'Multa aplicada. El producto queda disponible en el almacén.'],
                    ];
                    $currentStage = $penaltyStages[$loan->penalty_status] ?? $penaltyStages['normal'];
                @endphp

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Multa</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                        <input type="hidden" id="print-template-penalty" value="{{ $defaultPenaltyTemplateId ?? '' }}">
                        <x-btn variant="secondary" icon="printer" class="print-action-btn" data-action="penalty" data-template-select="print-template-penalty">Imprimir</x-btn>
                    </div>
                </div>

                {{-- Estado actual --}}
                <div class="alert alert-{{ $currentStage['color'] }} d-flex align-items-start gap-3 mb-4" style="border-radius: .75rem;">
                    <i class="bi {{ $currentStage['icon'] }} fs-3 mt-1"></i>
                    <div>
                        <h6 class="mb-1 fw-bold">Estado actual: {{ $currentStage['label'] }}</h6>
                        <p class="mb-0 small">{{ $currentStage['desc'] }}</p>
                        @if($graceEndDate)
                            <p class="mb-0 small mt-1">
                                <i class="bi bi-hourglass-split me-1"></i>
                                La gracia vence el <strong>{{ $graceEndDate->format('d/m/Y') }}</strong>
                                @if(now()->gt($graceEndDate))
                                    <span class="badge bg-danger ms-1">Vencida</span>
                                @else
                                    <span class="text-muted">({{ (int) now()->diffInDays($graceEndDate, false) }} día(s) restantes)</span>
                                @endif
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Pago de la multa --}}
                @if((float) $loan->penalty_amount > 0)
                    @if($loan->isPenaltyPaid())
                        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" style="border-radius:.75rem;">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                            <div>
                                <strong>Multa pagada</strong> — {{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}
                                el {{ $loan->penalty_paid_at->format('d/m/Y H:i') }}.
                                <div class="small text-muted">Ya no se cobra en la liquidación.</div>
                            </div>
                        </div>
                    @elseif($isDefaulted)
                        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4" style="border-radius:.75rem;">
                            <i class="bi bi-lock-fill fs-5"></i>
                            <div>
                                <strong>Multa pendiente:</strong> {{ $currencySymbol }} {{ number_format($pendingPenalty, 2) }}
                                <div class="small text-muted">Préstamo incumplido: solo lectura, no se registran pagos.</div>
                            </div>
                        </div>
                    @elseif($canPayPenalty)
                        <div class="card border-0 shadow-sm mb-4" style="border-radius:.75rem;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <div>
                                        <h6 class="fw-bold mb-1"><i class="bi bi-cash-coin text-danger me-1"></i> Multa pendiente</h6>
                                        <div class="fs-4 fw-bold text-danger">{{ $currencySymbol }} {{ number_format($pendingPenalty, 2) }}</div>
                                        <div class="small text-muted">
                                            Pagarla salda solo la multa: el préstamo sigue en mora hasta ponerse al día con los intereses.
                                        </div>
                                    </div>
                                    <form action="{{ route('loans.penalty.store', $loan) }}" method="POST" class="d-flex flex-column gap-2" style="min-width:260px;">
                                        @csrf
                                        <input type="hidden" name="from_context" value="{{ $fromContext }}">
                                        <input type="hidden" name="payment_method" value="efectivo">
                                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notas (opcional)">
                                        <x-btn variant="success" type="submit" icon="check-lg" :disabled="!(isset($activeCashSession) && $activeCashSession)">Pagar multa</x-btn>
                                        @if(!(isset($activeCashSession) && $activeCashSession))
                                            <small class="text-danger">Necesitas una caja abierta para cobrar.</small>
                                        @endif
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Condiciones de multa --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 bg-light text-center p-3" style="border-radius:.75rem;">
                            <small class="text-muted text-uppercase fs-xs" >Días de gracia</small>
                            <div class="fw-bold fs-4 text-primary">{{ $graceDays }}</div>
                            <small class="text-muted">días después del vencimiento</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-light text-center p-3" style="border-radius:.75rem;">
                            <small class="text-muted text-uppercase fs-xs" >Tasa de multa</small>
                            <div class="fw-bold fs-4 text-danger">{{ $penaltyRate }}%</div>
                            <small class="text-muted">sobre liquidación total</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-light text-center p-3" style="border-radius:.75rem;">
                            <small class="text-muted text-uppercase fs-xs" >Monto fijo alternativo</small>
                            <div class="fw-bold fs-4 text-dark">{{ $penaltyFixed > 0 ? $currencySymbol . ' ' . number_format($penaltyFixed, 2) : '—' }}</div>
                            <small class="text-muted">{{ $penaltyFixed > 0 ? 'se usa en lugar del %' : 'no configurado' }}</small>
                        </div>
                    </div>
                </div>

                {{-- Cronología de estados --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-signpost-split me-1 text-mod"></i> Cronología del préstamo</h6>
                        <div class="d-flex flex-column gap-0">
                            {{-- Paso 1: Activo --}}
                            <div class="d-flex align-items-start gap-3 pb-3 position-relative" style="padding-left:20px;">
                                <div class="position-absolute" style="left:8px; top:8px; bottom:0; width:2px; background: {{ in_array($loan->penalty_status, ['normal']) && $loan->status === \App\Models\Loan::STATUS_ACTIVE ? '#0d6efd' : '#dee2e6' }};"></div>
                                <div class="rounded-circle flex-shrink-0 {{ in_array($loan->status, array_merge(\App\Models\Loan::OPEN_STATUSES, [\App\Models\Loan::STATUS_FINISHED]), true) ? 'bg-success' : 'bg-secondary' }}" style="width:18px; height:18px; margin-top:4px; position:relative; z-index:1;"></div>
                                <div>
                                    <strong class="small">Préstamo activo</strong>
                                    <div class="small text-muted">Inicio: {{ $loan->start_date?->format('d/m/Y') ?? '-' }}</div>
                                </div>
                            </div>
                            {{-- Paso 2: Vencimiento --}}
                            <div class="d-flex align-items-start gap-3 pb-3 position-relative" style="padding-left:20px;">
                                <div class="position-absolute" style="left:8px; top:8px; bottom:0; width:2px; background: {{ in_array($loan->penalty_status, ['overdue', 'grace', 'defaulted']) ? '#ffc107' : '#dee2e6' }};"></div>
                                <div class="rounded-circle flex-shrink-0 {{ in_array($loan->penalty_status, ['overdue', 'grace', 'defaulted']) ? 'bg-warning' : 'bg-light border' }}" style="width:18px; height:18px; margin-top:4px; position:relative; z-index:1;"></div>
                                <div>
                                    <strong class="small">Vencimiento del plazo</strong>
                                    <div class="small text-muted">{{ $termEndDate ? $termEndDate->format('d/m/Y') : '-' }} ({{ $loan->term_months }} meses)</div>
                                    @if($loan->penalty_status !== 'normal')
                                        <span class="badge bg-warning text-dark small mt-1">En mora — Producto pasa a retención</span>
                                    @endif
                                </div>
                            </div>
                            {{-- Paso 3: Fin de gracia --}}
                            <div class="d-flex align-items-start gap-3 pb-3 position-relative" style="padding-left:20px;">
                                <div class="position-absolute" style="left:8px; top:8px; bottom:0; width:2px; background: {{ $loan->penalty_status === 'defaulted' ? '#dc3545' : '#dee2e6' }};"></div>
                                <div class="rounded-circle flex-shrink-0 {{ $loan->penalty_status === 'defaulted' ? 'bg-danger' : 'bg-light border' }}" style="width:18px; height:18px; margin-top:4px; position:relative; z-index:1;"></div>
                                <div>
                                    <strong class="small">Fin del periodo de gracia</strong>
                                    <div class="small text-muted">{{ $graceEndDate ? $graceEndDate->format('d/m/Y') : '-' }} (+{{ $graceDays }} días)</div>
                                    @if($loan->penalty_status === 'defaulted')
                                        <span class="badge bg-danger small mt-1">Multa aplicada — Producto disponible</span>
                                    @endif
                                </div>
                            </div>
                            {{-- Paso 4: Resolución --}}
                            <div class="d-flex align-items-start gap-3" style="padding-left:20px;">
                                <div class="rounded-circle flex-shrink-0 {{ $loan->status === \App\Models\Loan::STATUS_FINISHED ? 'bg-success' : 'bg-light border' }}" style="width:18px; height:18px; margin-top:4px; position:relative; z-index:1;"></div>
                                <div>
                                    <strong class="small">{{ $loan->status === \App\Models\Loan::STATUS_FINISHED ? 'Préstamo liquidado' : 'Pendiente de resolución' }}</strong>
                                    <div class="small text-muted">{{ $loan->status === \App\Models\Loan::STATUS_FINISHED ? 'El producto fue devuelto al cliente.' : 'Liquidar o vender producto para cerrar.' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Cálculo de multa --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-calculator me-1 text-danger"></i> Cálculo de multa</h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted">Capital vigente</td>
                                        <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format($currentCapital, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Interés pendiente</td>
                                        <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format($unpaidInterest, 2) }}</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td class="fw-bold">Base para multa (liquidación)</td>
                                        <td class="text-end fw-bold">{{ $currencySymbol }} {{ number_format($baseLiquidation, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">
                                            @if($penaltyFixed > 0)
                                                Multa fija configurada
                                            @else
                                                Multa ({{ $penaltyRate }}% de {{ $currencySymbol }} {{ number_format($baseLiquidation, 2) }})
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold text-danger">{{ $currencySymbol }} {{ number_format($calculatedPenalty, 2) }}</td>
                                    </tr>
                                    @if((float) $loan->penalty_amount > 0)
                                    <tr class="table-danger">
                                        <td class="fw-bold">Multa actualmente aplicada</td>
                                        <td class="text-end fw-bold text-danger fs-5">{{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}</td>
                                    </tr>
                                    @endif
                                    <tr class="table-dark">
                                        <td class="fw-bold">Total para liquidar (con multa)</td>
                                        <td class="text-end fw-bold fs-5">{{ $currencySymbol }} {{ number_format($liquidationAmount, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if($loan->penalty_status === 'normal')
                            <div class="alert alert-success small mt-3 mb-0 py-2">
                                <i class="bi bi-shield-check me-1"></i>
                                No hay multas aplicadas. El préstamo está al día.
                            </div>
                        @elseif($loan->penalty_status === 'overdue')
                            <div class="alert alert-warning small mt-3 mb-0 py-2">
                                <i class="bi bi-clock me-1"></i>
                                Periodo de gracia activo. Quedan <strong>{{ $graceEndDate ? max(0, (int) now()->diffInDays($graceEndDate, false)) : '?' }} días</strong> antes de aplicar la multa.
                            </div>
                        @elseif($loan->penalty_status === 'defaulted')
                            <div class="alert alert-danger small mt-3 mb-0 py-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Multa aplicada de <strong>{{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}</strong>. El producto queda disponible en el almacén.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endif

{{-- ── Modal: Eliminar Préstamo ───────────────────────────── --}}
@if(!$loan->trashed())
<div class="modal fade" id="deleteLoanModal" tabindex="-1" aria-labelledby="deleteLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius:20px">
            <div class="modal-header border-0 pb-0 px-4 pt-4" style="background:linear-gradient(135deg,#fff5f5 0%,#fee2e2 100%)">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div class="fs-lg" style="width:46px;height:46px;border-radius:14px;background:#fecaca;color:#991b1b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-trash-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-md" >Eliminar Préstamo</div>
                        <div class="small text-danger">Esta acción es irreversible</div>
                    </div>
                    <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal"></button>
                </div>
                <div class="w-100 mt-3 mb-1 p-3 rounded-3" style="background:rgba(255,255,255,.7);border:1px solid #fecaca;">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div class="fs-xs" style="color:#991b1b;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Código</div>
                            <div class="fw-bold">PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="fs-xs" style="color:#991b1b;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Monto</div>
                            <div class="fw-bold text-danger">{{ $currencySymbol }} {{ number_format($loan->amount, 2) }}</div>
                        </div>
                        <div class="col-12">
                            <div class="fs-xs" style="color:#991b1b;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Cliente</div>
                            <div class="fw-semibold">{{ $loan->client?->name ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body px-4 pt-3 pb-0">
                <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3 fs-sm" style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                    <div>Se eliminarán también todos los períodos de interés pendientes asociados. <strong>No se puede eliminar si hay intereses pagados o cobros en caja.</strong></div>
                </div>
                <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3 fs-sm" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;">
                    <i class="bi bi-cash-coin flex-shrink-0 mt-1"></i>
                    <div>
                        Al eliminar, <strong>{{ $currencySymbol }} {{ number_format($loan->amount, 2) }} vuelven a tu caja aperturada</strong> como un ingreso de reversión,
                        y <strong>la prenda sale del inventario</strong>. Necesitas tener una caja abierta.
                    </div>
                </div>
                <form id="deleteLoanForm" method="POST" action="{{ route('loans.destroy', $loan) }}">
                    @csrf
                    @method('DELETE')
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Motivo de eliminación <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" required
                                  placeholder="Describe el motivo por el que se elimina este préstamo..."></textarea>
                    </div>
                </form>
            </div>
            <div class="px-4 pb-4">
                <div class="d-grid gap-2">
                    <x-btn variant="danger" size="md" :block="true" type="submit" form="deleteLoanForm" icon="trash">Confirmar eliminación</x-btn>
                    <x-btn variant="secondary" size="md" :block="true" data-bs-dismiss="modal">Cancelar</x-btn>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
@php
    $contractTemplateCatalog = ($contractTemplates ?? collect())->map(function ($tpl) use ($loan) {
        return [
            'id' => (string) $tpl->id,
            'name' => $tpl->name,
            'paper_size' => $tpl->paper_size,
            'content' => (string) $tpl->applyToLoan($loan),
        ];
    })->values();
    $interestTemplateCatalog = ($interestPrintTemplates ?? collect())->map(function ($tpl) use ($loan) {
        return [
            'id' => (string) $tpl->id,
            'name' => $tpl->name,
            'paper_size' => $tpl->paper_size,
            'content' => (string) $tpl->content,
        ];
    })->values();
    $actionTemplateCatalog = ($loanActionTemplates ?? collect())->map(function ($tpl) use ($loan) {
        return [
            'id' => (string) $tpl->id,
            'name' => $tpl->name,
            'paper_size' => $tpl->paper_size,
            'content' => (string) $tpl->content,
        ];
    })->values();
@endphp
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
$(function () {
    const contractTemplateCatalog = @json($contractTemplateCatalog);
    const interestTemplateCatalog = @json($interestTemplateCatalog);
    const actionTemplateCatalog = @json($actionTemplateCatalog);
    const activeLoanTabFromServer = @json(session('activeLoanTab') ?: old('source_action'));
    const isReadOnlyReprintMode = @json($isReadOnlyReprintMode);
    const liquidationRecord = {
        amount: @json($lastLiquidationPayment?->amount),
        capital: @json($lastLiquidationPayment?->capital),
        interest: @json($lastLiquidationPayment?->interest),
        paymentDate: @json(optional($lastLiquidationPayment?->payment_date)->format('Y-m-d')),
        paymentMethod: @json($lastLiquidationPayment?->payment_method),
        reference: @json($lastLiquidationPayment?->reference),
        notes: @json($lastLiquidationPayment?->notes),
    };

    function activateLoanActionTab(tabName) {
        const tabMap = {
            amortize: 'tab-amortize',
            liquidate: 'tab-liquidate',
            penalty: 'tab-penalty',
            interest: 'tab-interest',
            contract: 'tab-contract',
        };

        const tabId = tabMap[tabName];
        if (!tabId) return;

        const trigger = document.getElementById(tabId);
        if (!trigger || typeof bootstrap === 'undefined' || !bootstrap.Tab) return;

        bootstrap.Tab.getOrCreateInstance(trigger).show();
    }

    if (activeLoanTabFromServer) {
        activateLoanActionTab(activeLoanTabFromServer);
    }

    // ── Summernote ──
    const $editor = $('#contract-content');
    if ($editor.length && typeof $editor.summernote === 'function') {
        $editor.summernote({
            placeholder: 'Redacta aquí el contrato...',
            tabsize: 2,
            height: 350,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'hr']],
                ['view', ['codeview', 'help', 'fullscreen']],
                ['history', ['undo', 'redo']]
            ]
        });
    }

    function getContractContent() {
        if ($editor.length && typeof $editor.summernote === 'function') {
            return $editor.summernote('code') || '';
        }
        return $editor.val() || '';
    }

    function submitContractExport(actionUrl) {
        const form = document.getElementById('contract-export-form');
        const contentInput = document.getElementById('contract-export-content');
        if (!form || !contentInput) return;
        contentInput.value = getContractContent();
        form.action = actionUrl;
        form.submit();
    }

    function getContractTemplateById(templateId) {
        return contractTemplateCatalog.find(t => String(t.id) === String(templateId));
    }

    function buildThermalContractFallback() {
        return `
            <div class="fs-xs" style="text-align:center; font-family:Courier New, monospace; font-weight:700; color:#000;">
                <strong class="fs-sm" style="font-weight:700;">{{ $loan->company?->name ?? '' }}</strong><br>
                <strong class="fs-xs" style="font-weight:700;">{{ $loan->branch?->name ?? '' }}</strong><br>
                @if($loan->branch?->phone)<strong class="fs-xs" style="font-weight:400;">{{ $loan->branch->phone }}</strong><br>@endif
                <hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
                <strong class="fs-xs" style="font-weight:700;">COMPROBANTE DE PRESTAMO</strong><br>
                <strong class="fs-xs" style="font-weight:700;">{{ now()->format('d/m/Y H:i') }}</strong>
                <hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
            </div>
            <table class="fs-xs" style="width:100%; font-family:Courier New, monospace; font-weight:700; color:#000; border-collapse:collapse;">
                <tr><td style="padding:2px 0;"><strong>Prestamo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}</strong></td></tr>
                <tr><td style="padding:2px 0;"><strong>Cliente:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ $loan->client?->name ?? '' }}</strong></td></tr>
                <tr><td style="padding:2px 0;"><strong>Producto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ $loan->product?->name ?? '' }}</strong></td></tr>
                <tr><td style="padding:2px 0;"><strong>Monto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ $currencySymbol }} {{ number_format((float) $loan->amount, 2) }}</strong></td></tr>
                <tr><td style="padding:2px 0;"><strong>Periodo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ match ((string) ($loan->interest_period ?? 'monthly')) { 'monthly' => 'Mensual', 'quarterly' => 'Trimestral', 'semiannual' => 'Semestral', 'annual' => 'Anual', default => ucfirst((string) ($loan->interest_period ?? 'Mensual')) } }}</strong></td></tr>
                <tr><td style="padding:2px 0;"><strong>Inicio:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ $loan->start_date?->format('d/m/Y') ?? '' }}</strong></td></tr>
                <tr><td style="border-top:1px dashed #000; padding:4px 0 2px;"><strong>Recojo:</strong></td><td style="border-top:1px dashed #000; padding:4px 0 2px; text-align:right;"><strong>{{ $currencySymbol }} {{ number_format($currentCapital + $unpaidInterest, 2) }}</strong></td></tr>
            </table>
            <hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
            <div class="fs-xs" style="text-align:center; font-family:Courier New, monospace; font-weight:700; color:#000;">
                <strong>TODO RECOJO ES PERSONAL</strong><br>
                <strong>CON EL CARNET DE IDENTIDAD.</strong>
            </div>`;
    }

    function printThermalContract() {
        openPrintWindowFromHtml(buildThermalContractFallback(), 'pequeno', 'Comprobante térmico', false);
    }

    $('#btn-preview-pdf').on('click', function () {
        submitContractExport("{{ route('loans.contract.download.pdf', $loan) }}");
    });

    $('#btn-doc-privado-prestamo').on('click', function () {
        openPrintWindowFromHtml(getContractContent(), 'grande', 'Documento Privado', false);
    });

    $('#btn-print-thermal-contract').on('click', function () {
        printThermalContract();
    });


    function getPrintSizeStyles(templateType) {
        if (templateType === 'pequeno') {
            return {
                page: '@page{size:80mm auto; margin:8px;}',
                wrapper: 'max-width:80mm; margin:0 auto;'
            };
        }
        if (templateType === 'mediano') {
            return {
                page: '@page{size:215.9mm 139.7mm; margin:10mm;}',
                wrapper: 'max-width:215.9mm; min-height:139.7mm; margin:0 auto;'
            };
        }
        return {
            page: '@page{size:letter portrait; margin:0;}',
            wrapper: 'max-width:215.9mm; padding:12mm; margin:0 auto;'
        };
    }

    function formatMoney(value) {
        const n = Number(value || 0);
        return n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function applyTemplatePlaceholders(templateHtml, placeholders) {
        let rendered = String(templateHtml || '');
        Object.entries(placeholders).forEach(([key, val]) => {
            rendered = rendered.split(key).join(escapeHtml(val));
        });
        return rendered;
    }

    function token(name) {
        return '{' + '{' + name + '}' + '}';
    }

    function buildCommonLoanPayload() {
        return {
            [token('cliente_nombre')]: '{{ $loan->client?->name ?? '' }}',
            [token('cliente_cedula')]: '{{ $loan->client?->id_number ?? '' }}',
            [token('cliente_telefono')]: '{{ $loan->client?->phone ?? '' }}',
            [token('cliente_email')]: '{{ $loan->client?->email ?? '' }}',
            [token('cliente_direccion')]: '{{ $loan->client?->address ?? '' }}',
            [token('prestamo_id')]: 'PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}',
            [token('prestamo_monto')]: '{{ number_format((float) $loan->amount, 2) }}',
            [token('prestamo_tasa')]: '{{ $loan->interest_rate }}%',
            [token('prestamo_plazo')]: '{{ $loan->term_months }} meses',
            [token('prestamo_cuota')]: '{{ number_format((float) $loan->monthly_payment, 2) }}',
            [token('prestamo_total')]: '{{ number_format((float) $loan->total_to_pay, 2) }}',
            [token('prestamo_saldo')]: '{{ number_format(max(0, (float) $loan->total_to_pay - (float) $loan->total_paid), 2) }}',
            [token('empresa_nombre')]: '{{ $loan->company?->name ?? '' }}',
            [token('sucursal_nombre')]: '{{ $loan->branch?->name ?? '' }}',
            [token('sucursal_telefono')]: '{{ $loan->branch?->phone ?? '' }}',
            [token('fecha_actual')]: new Date().toLocaleString('es-BO'),
            [token('fecha_inicio')]: '{{ $loan->start_date?->format('d/m/Y') ?? '' }}',
            [token('fecha_fin')]: '{{ $loan->end_date?->format('d/m/Y') ?? '' }}',
        };
    }

    function openPrintWindowFromHtml(rawHtml, templateType, titleSuffix, showContextTitle = true) {
        const printConfig = getPrintSizeStyles(templateType || 'grande');
        const isThermal = (templateType || 'grande') === 'pequeno';
        const printWin = window.open('', '_blank', 'width=900,height=700');
        if (!printWin) return;

        const contextTitle = showContextTitle
            ? `<h5 class="mb-3">Préstamo PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }} — {{ $loan->client->name ?? '' }}${titleSuffix ? ' - ' + titleSuffix : ''}</h5>`
            : '';

        printWin.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
            <title></title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                body{padding:20px;font-size:${isThermal ? '12px' : '13px'};background:#fff;color:#000;-webkit-font-smoothing:none;-moz-osx-font-smoothing:auto;}
                .print-wrapper{${printConfig.wrapper}}
                ${printConfig.page}
                ${isThermal ? `
                .print-wrapper,*{color:#000!important;-webkit-text-fill-color:#000!important;text-shadow:none!important;box-shadow:none!important;}
                .print-wrapper{font-family:"Courier New",monospace;line-height:1.28;}
                .print-wrapper hr{border-top-color:#000!important;opacity:1!important;}
                ` : ''}
                @media print{
                    body{padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
                    .no-print{display:none!important;}
                }
            </style>
        </head><body>
            <div class="print-wrapper">
                ${contextTitle}
                ${rawHtml}
            </div>
            <script>window.onload=function(){window.print();}<\/script>
        </body></html>`);
        printWin.document.close();
    }

    function buildInterestPayload(cardEl, sourceEl) {
        if (!cardEl) return null;

        const amountInput = sourceEl ? sourceEl.querySelector('input[name="amount"]') : null;
        const methodInput = sourceEl ? sourceEl.querySelector('select[name="payment_method"]') : null;
        const referenceInput = sourceEl ? sourceEl.querySelector('input[name="reference"]') : null;

        // If form exists use its value; otherwise fall back to remaining; if remaining = 0 (paid) use paidAmount
        const remainingNum = Number(cardEl.dataset.remaining || 0);
        const paidNum = Number(cardEl.dataset.paidAmount || 0);
        const inputAmount = (amountInput && amountInput.value)
            ? Number(amountInput.value)
            : (remainingNum > 0 ? remainingNum : paidNum);

        const paymentMethodRaw = methodInput && methodInput.value ? methodInput.value : (cardEl.dataset.paymentMethod || '');
        const paymentMethod = paymentMethodRaw
            ? paymentMethodRaw.charAt(0).toUpperCase() + paymentMethodRaw.slice(1)
            : 'No especificado';

        const paymentReference = referenceInput && referenceInput.value ? referenceInput.value : (cardEl.dataset.reference || '-');

        return {
            ...buildCommonLoanPayload(),
            [token('interes_periodo')]: '#' + (cardEl.dataset.periodNumber || ''),
            [token('interes_numero_periodo')]: cardEl.dataset.periodNumber || '',
            [token('interes_fecha_inicio')]: cardEl.dataset.periodStart || '',
            [token('interes_fecha_fin')]: cardEl.dataset.periodEnd || '',
            [token('interes_fecha_vencimiento')]: cardEl.dataset.periodEnd || '',
            [token('interes_estado')]: cardEl.dataset.status || '',
            [token('interes_base_capital')]: '{{ $currencySymbol }} ' + formatMoney(cardEl.dataset.baseAmount || 0),
            [token('interes_tasa')]: (cardEl.dataset.interestRate || '') + '%',
            [token('interes_total_periodo')]: '{{ $currencySymbol }} ' + formatMoney(cardEl.dataset.interestAmount || 0),
            [token('interes_pagado')]: '{{ $currencySymbol }} ' + formatMoney(cardEl.dataset.paidAmount || 0),
            [token('interes_saldo')]: '{{ $currencySymbol }} ' + formatMoney(cardEl.dataset.remaining || 0),
            [token('interes_pago_monto')]: '{{ $currencySymbol }} ' + formatMoney(inputAmount || 0),
            [token('interes_pago_metodo')]: paymentMethod,
            [token('interes_pago_referencia')]: paymentReference || '-',
            [token('interes_pago_fecha')]: new Date().toLocaleString('es-BO'),
        };
    }

    function getInterestTemplateById(templateId) {
        return interestTemplateCatalog.find(t => String(t.id) === String(templateId));
    }

    function printInterestFromCard(cardEl, templateSelectEl, sourceEl) {
        const payload = buildInterestPayload(cardEl, sourceEl);
        if (!payload) return;

        let templateHtml = `
            <div class="border rounded p-3">
                <h6 class="mb-3">Recibo de pago de interés</h6>
                <div><strong>Préstamo:</strong> PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}</div>
                <div><strong>Cliente:</strong> {{ $loan->client?->name ?? '' }}</div>
                <hr>
                <div><strong>Periodo:</strong> ${token('interes_periodo')} (${token('interes_fecha_inicio')} - ${token('interes_fecha_fin')})</div>
                <div><strong>Monto pagado:</strong> {{ $currencySymbol }} ${token('interes_pago_monto')}</div>
                <div><strong>Método:</strong> ${token('interes_pago_metodo')}</div>
                <div><strong>Referencia:</strong> ${token('interes_pago_referencia')}</div>
                <div><strong>Fecha:</strong> ${token('interes_pago_fecha')}</div>
            </div>`;
        let templateType = 'pequeno';

        if (templateSelectEl && templateSelectEl.value) {
            const selected = getInterestTemplateById(templateSelectEl.value);
            if (selected) {
                templateHtml = selected.content || templateHtml;
                templateType = selected.paper_size || 'pequeno';
            }
        }

        const renderedHtml = applyTemplatePlaceholders(templateHtml, payload);
        openPrintWindowFromHtml(renderedHtml, templateType, 'Pago de interés ' + (payload[token('interes_periodo')] || ''), false);
    }

    function ensureAmountForAction(action) {
        if (action !== 'amortize' && action !== 'liquidate') return true;
        if (action === 'liquidate' && isReadOnlyReprintMode) return true;

        const form = document.querySelector(action === 'amortize' ? '#pane-amortize form' : '#pane-liquidate form');
        const amountInput = form ? form.querySelector('input[name="amount"]') : null;
        const amountValue = amountInput ? Number(amountInput.value || 0) : 0;

        if (!amountInput || !Number.isFinite(amountValue) || amountValue <= 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Monto requerido',
                    text: 'Debe ingresar un monto válido antes de imprimir.',
                    timer: 2800,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                });
            } else {
                alert('Debe ingresar un monto válido antes de imprimir.');
            }
            if (amountInput) amountInput.focus();
            return false;
        }

        return true;
    }

    // ── Build payloads for amortize / liquidate / penalty tabs ──
    function buildAmortizePayload() {
        const form = document.querySelector('#pane-amortize form');
        const amountInput = form ? form.querySelector('input[name="amount"]') : null;
        const methodSelect = form ? form.querySelector('select[name="payment_method"]') : null;
        const referenceInput = form ? form.querySelector('input[name="reference"]') : null;
        const dateInput = form ? form.querySelector('input[name="payment_date"]') : null;
        const notesInput = form ? form.querySelector('input[name="notes"]') : null;
        const amountNumber = amountInput && amountInput.value ? Number(amountInput.value) : 0;
        const capitalBefore = Number('{{ number_format($currentCapital, 2, '.', '') }}');
        const capitalAfter = Math.max(0, capitalBefore - amountNumber);

        const paymentMethod = methodSelect && methodSelect.value
            ? methodSelect.value.charAt(0).toUpperCase() + methodSelect.value.slice(1)
            : 'No especificado';

        return {
            ...buildCommonLoanPayload(),
            [token('amortizacion_monto')]: formatMoney(amountNumber),
            [token('amortizacion_fecha')]: dateInput ? dateInput.value : new Date().toISOString().slice(0, 10),
            [token('amortizacion_metodo')]: paymentMethod,
            [token('amortizacion_referencia')]: referenceInput ? (referenceInput.value || '-') : '-',
            [token('amortizacion_notas')]: notesInput ? (notesInput.value || '-') : '-',
            [token('capital_vigente_antes')]: '{{ $currencySymbol }} ' + formatMoney(capitalBefore),
            [token('capital_vigente_despues')]: '{{ $currencySymbol }} ' + formatMoney(capitalAfter),
            [token('amortizacion_porcentaje')]: capitalBefore > 0 ? ((amountNumber / capitalBefore) * 100).toFixed(2) + '%' : '0.00%',
            [token('prestamo_saldo')]: formatMoney(capitalAfter),
            [token('capital_inicial')]: '{{ $currencySymbol }} {{ number_format((float) $loan->amount, 2) }}',
            [token('capital_vigente')]: '{{ $currencySymbol }} {{ number_format($currentCapital, 2) }}',
            [token('total_amortizado')]: '{{ $currencySymbol }} {{ number_format((float) $loan->amount - $currentCapital, 2) }}',
        };
    }

    function buildLiquidatePayload() {
        const form = document.querySelector('#pane-liquidate form');
        const amountInput = form ? form.querySelector('input[name="amount"]') : null;
        const methodSelect = form ? form.querySelector('select[name="payment_method"]') : null;
        const referenceInput = form ? form.querySelector('input[name="reference"]') : null;
        const dateInput = form ? form.querySelector('input[name="payment_date"]') : null;
        const notesInput = form ? form.querySelector('input[name="notes"]') : null;

        const recordAmount = Number(liquidationRecord.amount || 0);
        const recordCapital = Number(liquidationRecord.capital || 0);
        const recordInterest = Number(liquidationRecord.interest || 0);
        const paymentMethod = methodSelect && methodSelect.value
            ? methodSelect.value.charAt(0).toUpperCase() + methodSelect.value.slice(1)
            : (liquidationRecord.paymentMethod
                ? liquidationRecord.paymentMethod.charAt(0).toUpperCase() + liquidationRecord.paymentMethod.slice(1)
                : 'No especificado');

        const amountNumber = amountInput && amountInput.value
            ? Number(amountInput.value)
            : (recordAmount > 0 ? recordAmount : Number('{{ number_format($liquidationAmount, 2, '.', '') }}'));
        const paymentDate = dateInput && dateInput.value
            ? dateInput.value
            : (liquidationRecord.paymentDate || new Date().toISOString().slice(0, 10));
        const paymentReference = referenceInput && referenceInput.value
            ? referenceInput.value
            : (liquidationRecord.reference || '-');
        const paymentNotes = notesInput && notesInput.value
            ? notesInput.value
            : (liquidationRecord.notes || '-');
        const capitalValue = isReadOnlyReprintMode && recordCapital > 0
            ? recordCapital
            : Number('{{ number_format($currentCapital, 2, '.', '') }}');
        const interestValue = isReadOnlyReprintMode && recordInterest > 0
            ? recordInterest
            : Number('{{ number_format($unpaidInterest, 2, '.', '') }}');
        const liquidationTotalValue = recordAmount > 0
            ? recordAmount
            : Number('{{ number_format($liquidationAmount, 2, '.', '') }}');

        return {
            ...buildCommonLoanPayload(),
            [token('liquidacion_monto')]: '{{ $currencySymbol }} ' + formatMoney(amountNumber),
            [token('liquidacion_total')]: '{{ $currencySymbol }} ' + formatMoney(liquidationTotalValue),
            [token('liquidacion_fecha')]: paymentDate,
            [token('liquidacion_metodo')]: paymentMethod,
            [token('liquidacion_referencia')]: paymentReference,
            [token('liquidacion_notas')]: paymentNotes,
            [token('liquidacion_capital_vigente')]: '{{ $currencySymbol }} ' + formatMoney(capitalValue),
            [token('liquidacion_interes_pendiente')]: '{{ $currencySymbol }} ' + formatMoney(interestValue),
            [token('liquidacion_multa')]: '{{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}',
            [token('prestamo_total')]: formatMoney(liquidationTotalValue),
            [token('prestamo_saldo')]: formatMoney(liquidationTotalValue),
            [token('capital_vigente')]: '{{ $currencySymbol }} ' + formatMoney(capitalValue),
            [token('interes_pendiente')]: '{{ $currencySymbol }} ' + formatMoney(interestValue),
            [token('multa_monto')]: '{{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}',
        };
    }

    function buildPenaltyPayload() {
        return {
            ...buildCommonLoanPayload(),
            [token('multa_monto')]: '{{ $currencySymbol }} {{ number_format((float) $loan->penalty_amount, 2) }}',
            [token('multa_estado')]: '{{ $loan->penalty_status }}',
            [token('multa_tasa')]: '{{ $category?->penalty_rate ?? 0 }}%',
            [token('multa_monto_fijo')]: '{{ $currencySymbol }} {{ number_format((float) ($category?->penalty_fixed_amount ?? 0), 2) }}',
            [token('multa_dias_gracia')]: '{{ (int) ($category?->penalty_grace_days ?? 0) }}',
            [token('prestamo_saldo')]: '{{ number_format($liquidationAmount, 2) }}',
            [token('capital_vigente')]: '{{ $currencySymbol }} {{ number_format($currentCapital, 2) }}',
            [token('interes_pendiente')]: '{{ $currencySymbol }} {{ number_format($unpaidInterest, 2) }}',
            [token('liquidacion_total')]: '{{ $currencySymbol }} {{ number_format($liquidationAmount, 2) }}',
        };
    }

    const actionPayloadBuilders = {
        amortize:  { build: buildAmortizePayload,  title: 'Amortización' },
        liquidate: { build: buildLiquidatePayload, title: 'Liquidación' },
        penalty:   { build: buildPenaltyPayload,   title: 'Multa' },
    };

    const defaultFallbackTemplates = {
        amortize: `<div class="border rounded p-3">
            <h6 class="mb-3">Recibo de amortización</h6>
            <div><strong>Préstamo:</strong> ${token('prestamo_id')}</div>
            <div><strong>Cliente:</strong> ${token('cliente_nombre')}</div>
            <hr>
            <div><strong>Monto amortizado:</strong> {{ $currencySymbol }} ${token('amortizacion_monto')}</div>
            <div><strong>Capital vigente:</strong> ${token('capital_vigente')}</div>
            <div><strong>Método:</strong> ${token('amortizacion_metodo')}</div>
            <div><strong>Referencia:</strong> ${token('amortizacion_referencia')}</div>
            <div><strong>Fecha:</strong> ${token('fecha_actual')}</div>
        </div>`,
        liquidate: `<div class="border rounded p-3">
            <h6 class="mb-3">Recibo de liquidación</h6>
            <div><strong>Préstamo:</strong> ${token('prestamo_id')}</div>
            <div><strong>Cliente:</strong> ${token('cliente_nombre')}</div>
            <hr>
            <div><strong>Capital vigente:</strong> ${token('liquidacion_capital_vigente')}</div>
            <div><strong>Interés pendiente:</strong> ${token('liquidacion_interes_pendiente')}</div>
            <div><strong>Multa:</strong> ${token('liquidacion_multa')}</div>
            <div><strong>Total liquidación:</strong> ${token('liquidacion_total')}</div>
            <div><strong>Monto pagado:</strong> ${token('liquidacion_monto')}</div>
            <div><strong>Método:</strong> ${token('liquidacion_metodo')}</div>
            <div><strong>Referencia:</strong> ${token('liquidacion_referencia')}</div>
            <div><strong>Fecha:</strong> ${token('fecha_actual')}</div>
        </div>`,
        penalty: `<div class="border rounded p-3">
            <h6 class="mb-3">Detalle de multa</h6>
            <div><strong>Préstamo:</strong> ${token('prestamo_id')}</div>
            <div><strong>Cliente:</strong> ${token('cliente_nombre')}</div>
            <hr>
            <div><strong>Estado:</strong> ${token('multa_estado')}</div>
            <div><strong>Multa aplicada:</strong> ${token('multa_monto')}</div>
            <div><strong>Capital vigente:</strong> ${token('capital_vigente')}</div>
            <div><strong>Total liquidación:</strong> ${token('liquidacion_total')}</div>
            <div><strong>Fecha:</strong> ${token('fecha_actual')}</div>
        </div>`,
    };

    function printActionTab(action, templateSelectEl) {
        const config = actionPayloadBuilders[action];
        if (!config) return;

        const payload = config.build();
        let templateHtml = defaultFallbackTemplates[action] || '';
        let templateType = 'pequeno';

        if (templateSelectEl && templateSelectEl.value) {
            const selected = actionTemplateCatalog.find(t => String(t.id) === String(templateSelectEl.value));
            if (selected) {
                templateHtml = selected.content || templateHtml;
                templateType = selected.paper_size || 'pequeno';
            }
        }

        const renderedHtml = applyTemplatePlaceholders(templateHtml, payload);
        openPrintWindowFromHtml(renderedHtml, templateType, config.title, false);
    }

    // ── Print action buttons (amortize / liquidate / penalty) ──
    document.querySelectorAll('.print-action-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const action = this.dataset.action;
            if (!ensureAmountForAction(action)) return;
            const templateSelectId = this.dataset.templateSelect;
            const templateSelect = templateSelectId ? document.getElementById(templateSelectId) : null;
            printActionTab(action, templateSelect);
        });
    });

    const amortizeForm = document.querySelector('#pane-amortize form[action*="/amortize"]');
    if (amortizeForm) {
        amortizeForm.addEventListener('submit', function (e) {
            if (!ensureAmountForAction('amortize')) {
                e.preventDefault();
                return;
            }
            const templateSelect = document.getElementById('print-template-amortize');
            printActionTab('amortize', templateSelect);
        });
    }

    const liquidateForm = document.querySelector('#pane-liquidate form[action*="/liquidate"]');
    if (liquidateForm) {
        liquidateForm.addEventListener('submit', function (e) {
            if (!ensureAmountForAction('liquidate')) {
                e.preventDefault();
                return;
            }
            const templateSelect = document.getElementById('print-template-liquidate');
            printActionTab('liquidate', templateSelect);
        });
    }

    document.querySelectorAll('.interest-payment-form').forEach(form => {
        const fileInput = form.querySelector('.interest-receipt-input');
        const dropzone = form.querySelector('.receipt-dropzone');
        const dropzoneFile = dropzone ? dropzone.querySelector('.dropzone-file') : null;

        function showSelectedFile(file) {
            if (!dropzoneFile) return;
            dropzoneFile.textContent = file ? file.name : '';
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                showSelectedFile(this.files && this.files[0] ? this.files[0] : null);
            });
        }

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', function () {
                fileInput.click();
            });

            ['dragenter', 'dragover'].forEach(evt => {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'drop'].forEach(evt => {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('is-dragover');
                });
            });

            dropzone.addEventListener('drop', function (e) {
                const files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
                if (!files || !files.length) return;
                fileInput.files = files;
                showSelectedFile(files[0]);
            });
        }

        form.addEventListener('submit', function () {
            const templateSelectId = form.dataset.templateSelect;
            const templateSelect = templateSelectId ? document.getElementById(templateSelectId) : null;
            const cardId = form.dataset.cardId;
            const cardEl = cardId ? document.getElementById(cardId) : form.closest('.interest-period-card');
            printInterestFromCard(cardEl, templateSelect, form);
        });
    });

    /* ── Receipt visibility by payment method ── */
    const RECEIPT_METHODS = ['transferencia', 'pago_qr'];

    function wireReceiptToggle(methodSelect, wrapEl, dropzoneEl, fileInputEl, labelEl) {
        if (!methodSelect || !wrapEl) return;

        function toggle() {
            const show = RECEIPT_METHODS.includes(methodSelect.value);
            wrapEl.style.display = show ? '' : 'none';
            if (!show && fileInputEl) fileInputEl.value = '';
        }

        methodSelect.addEventListener('change', toggle);
        toggle();

        if (!dropzoneEl || !fileInputEl) return;

        function setLabel(file) {
            if (!labelEl) return;
            labelEl.textContent = file ? file.name : 'Arrastra o haz clic para adjuntar';
            labelEl.style.color = file ? '#0d6efd' : '';
        }

        dropzoneEl.addEventListener('click', () => fileInputEl.click());
        fileInputEl.addEventListener('change', function () { setLabel(this.files[0] || null); });
        ['dragenter', 'dragover'].forEach(e => dropzoneEl.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); dropzoneEl.classList.add('is-dragover'); }));
        ['dragleave', 'drop'].forEach(e => dropzoneEl.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); dropzoneEl.classList.remove('is-dragover'); }));
        dropzoneEl.addEventListener('drop', function (e) {
            const files = e.dataTransfer?.files;
            if (!files?.length) return;
            fileInputEl.files = files;
            setLabel(files[0]);
        });
    }

    /* Amortize */
    wireReceiptToggle(
        document.querySelector('#pane-amortize select[name="payment_method"]'),
        document.querySelector('.amortize-receipt-wrap'),
        document.getElementById('amortize-dropzone'),
        document.getElementById('amortize-receipt-input'),
        document.getElementById('amortize-dropzone-label')
    );

    /* Liquidate */
    wireReceiptToggle(
        document.querySelector('#pane-liquidate select[name="payment_method"]'),
        document.querySelector('.liq-receipt-wrap'),
        document.getElementById('liquidate-dropzone'),
        document.getElementById('liquidate-receipt-input'),
        document.getElementById('liquidate-dropzone-label')
    );

    /* Interest — one per period card */
    document.querySelectorAll('.interest-payment-form').forEach(function (form) {
        wireReceiptToggle(
            form.querySelector('select[name="payment_method"]'),
            form.querySelector('.interest-receipt-wrap'),
            null, null, null
        );
    });

    document.querySelectorAll('.interest-print-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const cardId = this.dataset.cardId;
            const templateSelectId = this.dataset.templateSelect;
            const cardEl = cardId ? document.getElementById(cardId) : null;
            const templateSelect = templateSelectId ? document.getElementById(templateSelectId) : null;
            const sourceForm = cardEl ? cardEl.querySelector('.interest-payment-form') : null;
            printInterestFromCard(cardEl, templateSelect, sourceForm);
        });
    });

});


</script>
@endpush

{{-- Modal: Eliminar periodo de interés --}}
<div class="modal fade" id="deleteInterestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius:18px">
            <div class="modal-body pt-4 pb-2 px-4 text-center">
                <div class="fs-lg" style="width:52px;height:52px;border-radius:14px;background:#fee2e2;color:#dc2626;
                            display:flex;align-items:center;justify-content:center;margin:0 auto .85rem;">
                    <i class="bi bi-trash3-fill"></i>
                </div>
                <h6 class="fw-bold mb-1 fs-md" >Eliminar periodo de interés</h6>
                <p class="text-muted mb-0 fs-sm" >
                    ¿Seguro que deseas eliminar el <strong id="dim-period-label">periodo #?</strong><br>
                    <span class="fs-xs" style="color:#9ca3af;">Esta acción no se puede deshacer.</span>
                </p>
            </div>
            <form id="deleteInterestForm" method="POST" class="px-4 pb-4 pt-3">
                @csrf
                @method('DELETE')
                <input type="hidden" id="dim-from-context" name="from_context" value="">
                <div class="d-flex gap-2">
                    <x-btn variant="secondary" size="md" class="flex-fill" data-bs-dismiss="modal">Cancelar</x-btn>
                    <x-btn variant="danger" size="md" class="flex-fill" type="submit" icon="trash">Eliminar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.ip-delete-btn');
    if (!btn) return;
    document.getElementById('dim-period-label').textContent = 'periodo #' + btn.dataset.period;
    document.getElementById('deleteInterestForm').action = btn.dataset.action;
    document.getElementById('dim-from-context').value = btn.dataset.fromContext || '';
    new bootstrap.Modal(document.getElementById('deleteInterestModal')).show();
});

// Restaurar un interés eliminado: confirmar y bloquear el botón para no enviarlo dos veces.
document.addEventListener('submit', function (e) {
    const form = e.target.closest('.interest-restore-form');
    if (!form) return;
    if (form.dataset.confirmed === '1') {
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Restaurando…';
        return;
    }
    e.preventDefault();
    const text = '¿Restaurar el periodo de interés #' + form.dataset.period + '? Volverá a contar como interés pendiente del préstamo.';
    const go = () => { form.dataset.confirmed = '1'; form.requestSubmit(); };
    if (window.Swal) {
        Swal.fire({ icon: 'question', text: text, showCancelButton: true, confirmButtonText: 'Restaurar', cancelButtonText: 'Cancelar', confirmButtonColor: '#198754' })
            .then((r) => { if (r.isConfirmed) go(); });
    } else if (confirm(text)) {
        go();
    }
});
</script>
@endpush

@endsection
