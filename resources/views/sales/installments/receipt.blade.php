<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo cuota {{ $installment->installment_no }} — VE-{{ str_pad($sale->numero, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #111;
            background: #f5f5f5;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 24px 12px;
        }

        /* ── Paper (80 mm) ── */
        .receipt {
            width: 302px; /* ≈ 80 mm at 96 dpi */
            background: #fff;
            padding: 14px 14px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
        }

        /* ── Header ── */
        .receipt-company {
            text-align: center;
            margin-bottom: 10px;
        }
        .receipt-company .company-name {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .receipt-company .company-sub {
            font-size: 9.5px;
            color: #555;
            line-height: 1.5;
        }

        /* ── Divider ── */
        .dashed { border: none; border-top: 1px dashed #bbb; margin: 8px 0; }
        .solid  { border: none; border-top: 1px solid  #333; margin: 8px 0; }

        /* ── Title ── */
        .receipt-title {
            text-align: center;
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin: 4px 0 2px;
        }
        .receipt-subtitle {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-bottom: 4px;
        }

        /* ── Amount highlight ── */
        .receipt-amount-wrap {
            text-align: center;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 8px 6px;
            margin: 8px 0;
        }
        .receipt-amount-label {
            font-size: 9px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .receipt-amount-value {
            font-size: 22px;
            font-weight: 700;
            color: #16a34a;
            letter-spacing: -.01em;
            line-height: 1.2;
        }

        /* ── Data rows ── */
        .receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 6px;
            padding: 2px 0;
            font-size: 10.5px;
            line-height: 1.45;
        }
        .receipt-row .label {
            color: #666;
            flex-shrink: 0;
        }
        .receipt-row .value {
            font-weight: 600;
            text-align: right;
            word-break: break-word;
        }

        /* ── Progress bar ── */
        .receipt-progress-wrap {
            margin: 6px 0 3px;
        }
        .receipt-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #555;
            margin-bottom: 3px;
        }
        .receipt-progress-track {
            height: 5px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }
        .receipt-progress-bar {
            height: 100%;
            border-radius: 999px;
            background: #16a34a;
        }

        /* ── Notes ── */
        .receipt-notes {
            font-size: 9.5px;
            color: #555;
            font-style: italic;
            margin-top: 4px;
            padding: 5px 6px;
            background: #fafafa;
            border-left: 2px solid #d1d5db;
            border-radius: 0 4px 4px 0;
            line-height: 1.4;
        }

        /* ── Footer ── */
        .receipt-footer {
            text-align: center;
            font-size: 9px;
            color: #888;
            margin-top: 10px;
            line-height: 1.5;
        }

        /* ── Signature space ── */
        .receipt-signature {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        .receipt-signature .sig-box {
            flex: 1;
            border-top: 1px solid #aaa;
            padding-top: 3px;
            text-align: center;
            font-size: 8.5px;
            color: #777;
        }

        /* ── Screen-only buttons ── */
        .screen-actions {
            display: flex;
            gap: 8px;
            margin-top: 18px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            transition: opacity .15s;
        }
        .btn-action:hover { opacity: .85; }
        .btn-print {
            background: #0d6efd;
            color: #fff;
        }
        .btn-back {
            background: #fff;
            color: #374151;
            border: 1.5px solid #d1d5db;
        }

        /* ── Print styles ── */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .receipt {
                width: 80mm;
                box-shadow: none;
                border-radius: 0;
                padding: 6mm 5mm 10mm;
            }
            .screen-actions { display: none !important; }
            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

@php
    $paidCount     = $sale->installments->where('status', \App\Models\SaleInstallment::STATUS_PAGADA)->count();
    $progressPct   = $totalInstallments > 0 ? round($paidCount / $totalInstallments * 100) : 0;
    $saleCode      = 'VE-' . str_pad($sale->numero, 5, '0', STR_PAD_LEFT);
@endphp

<div class="receipt">

    {{-- Company header --}}
    <div class="receipt-company">
        <div class="company-name">{{ $sale->company?->name ?? 'Empresa' }}</div>
        @if($sale->company?->phone)
            <div class="company-sub">Tel: {{ $sale->company->phone }}</div>
        @endif
        @if($sale->branch?->name)
            <div class="company-sub">Sucursal: {{ $sale->branch->name }}</div>
        @endif
    </div>

    <hr class="solid">

    <div class="receipt-title">Recibo de cuota</div>
    <div class="receipt-subtitle">Venta a crédito — {{ $saleCode }}</div>

    {{-- Amount --}}
    <div class="receipt-amount-wrap">
        <div class="receipt-amount-label">Monto pagado</div>
        <div class="receipt-amount-value">{{ $currencySymbol }} {{ number_format((float) $installment->amount, 2) }}</div>
    </div>

    <hr class="dashed">

    {{-- Sale & client --}}
    <div class="receipt-row">
        <span class="label">Venta</span>
        <span class="value">{{ $saleCode }}</span>
    </div>
    @if($sale->client)
    <div class="receipt-row">
        <span class="label">Cliente</span>
        <span class="value">{{ $sale->client->name }}</span>
    </div>
    @if($sale->client->id_number)
    <div class="receipt-row">
        <span class="label">CI / NIT</span>
        <span class="value">{{ $sale->client->id_number }}</span>
    </div>
    @endif
    @endif
    <div class="receipt-row">
        <span class="label">Producto</span>
        <span class="value">{{ $sale->product?->name ?? '-' }}</span>
    </div>

    <hr class="dashed">

    {{-- Installment info --}}
    <div class="receipt-row">
        <span class="label">Cuota</span>
        <span class="value">{{ $installment->installment_no }} / {{ $totalInstallments }}</span>
    </div>
    <div class="receipt-row">
        <span class="label">Vencimiento</span>
        <span class="value">{{ $installment->due_date?->format('d/m/Y') ?? '-' }}</span>
    </div>
    <div class="receipt-row">
        <span class="label">Fecha de pago</span>
        <span class="value">{{ $installment->paid_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</span>
    </div>
    <div class="receipt-row">
        <span class="label">Porcentaje</span>
        <span class="value">{{ number_format((float) $installment->percentage, 1) }}%</span>
    </div>

    <hr class="dashed">

    {{-- Progress --}}
    <div class="receipt-progress-wrap">
        <div class="receipt-progress-label">
            <span>Cuotas pagadas</span>
            <span>{{ $paidCount }}/{{ $totalInstallments }} ({{ $progressPct }}%)</span>
        </div>
        <div class="receipt-progress-track">
            <div class="receipt-progress-bar" style="width:{{ $progressPct }}%"></div>
        </div>
    </div>

    @if($installment->payment_notes)
    <div class="receipt-notes">
        <strong>Nota:</strong> {{ $installment->payment_notes }}
    </div>
    @endif

    <hr class="dashed">

    {{-- Signature --}}
    <div class="receipt-signature">
        <div class="sig-box">Firma cliente</div>
        <div class="sig-box">Firma cobrador</div>
    </div>

    {{-- Footer --}}
    <div class="receipt-footer">
        Impreso: {{ now()->format('d/m/Y H:i') }}<br>
        Este recibo es comprobante de pago.
    </div>

</div>

{{-- Screen-only actions --}}
<div class="screen-actions">
    <button onclick="window.print()" class="btn-action btn-print">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
            <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
            <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
        </svg>
        Imprimir recibo
    </button>
    <a href="{{ route('sales.show', $sale) }}" class="btn-action btn-back">
        ← Volver a la venta
    </a>
</div>

<script>
    // Auto-trigger print dialog on load
    window.addEventListener('load', function () {
        window.print();
    });
</script>

</body>
</html>
