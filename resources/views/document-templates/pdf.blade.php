@php
    $size    = $documentTemplate->paper_size ?? 'grande';
    $sizeCSS = \App\Models\DocumentTemplate::PAPER_SIZE_CSS[$size] ?? \App\Models\DocumentTemplate::PAPER_SIZE_CSS['grande'];
    $isSmall = $size === 'pequeno';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTemplate->name }} - PDF</title>
    <style>
        @page {
            {{ $sizeCSS['page'] }};
            margin: {{ $isSmall ? '2mm' : '12mm' }};
        }

        * { box-sizing: border-box; }

        body {
            font-family: {{ $isSmall ? "'Courier New', Courier, monospace" : "Arial, sans-serif" }};
            color: #1f2937;
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Toolbar (hidden on print) ─────────────────── */
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .toolbar .left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toolbar .right {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .toolbar .size-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            background: {{ $isSmall ? '#fef3c7' : '#dbeafe' }};
            color: {{ $isSmall ? '#92400e' : '#1e40af' }};
            font-weight: 600;
        }
        .toolbar button {
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all .15s;
        }
        .toolbar button:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        .toolbar .btn-print {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
            font-weight: 600;
        }
        .toolbar .btn-print:hover {
            background: #1d4ed8;
        }
        .toolbar .btn-direct {
            background: #059669;
            color: #fff;
            border-color: #059669;
            font-weight: 600;
        }
        .toolbar .btn-direct:hover {
            background: #047857;
        }

        /* ── Paper container ─────────────────── */
        .paper {
            width: {{ $sizeCSS['width'] }};
            min-height: {{ $sizeCSS['min-height'] }};
            background: #fff;
            margin: 60px auto 24px;
            padding: {{ $isSmall ? '4mm' : '16mm' }};
            {{ !$isSmall ? 'box-shadow: 0 2px 12px rgba(0,0,0,.12);' : 'box-shadow: 0 1px 6px rgba(0,0,0,.1);' }}
        }

        /* ── Header ─────────────────── */
        .doc-header {
            margin-bottom: {{ $isSmall ? '6px' : '16px' }};
            border-bottom: {{ $isSmall ? '1px dashed #9ca3af' : '1px solid #d1d5db' }};
            padding-bottom: {{ $isSmall ? '6px' : '12px' }};
            {{ $isSmall ? 'text-align: center;' : '' }}
        }
        .doc-title {
            font-size: {{ $isSmall ? '13px' : '20px' }};
            margin: 0 0 {{ $isSmall ? '2px' : '6px' }} 0;
            font-weight: {{ $isSmall ? '700' : '600' }};
        }
        .doc-meta {
            font-size: {{ $isSmall ? '9px' : '11px' }};
            color: #6b7280;
            margin: {{ $isSmall ? '1px 0' : '2px 0' }};
        }

        /* ── Content ─────────────────── */
        .doc-content {
            margin-top: {{ $isSmall ? '6px' : '18px' }};
            line-height: {{ $isSmall ? '1.3' : '1.6' }};
            font-size: {{ $isSmall ? '10px' : '14px' }};
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .doc-content table {
            width: 100%;
            border-collapse: collapse;
            font-size: inherit;
        }
        .doc-content table th,
        .doc-content table td {
            border: 1px solid #d1d5db;
            padding: {{ $isSmall ? '2px 3px' : '6px 10px' }};
            text-align: left;
        }

        /* ── Thermal receipt extras ── */
        @if($isSmall)
        .doc-content hr {
            border: none;
            border-top: 1px dashed #9ca3af;
            margin: 6px 0;
        }
        .doc-footer {
            text-align: center;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #9ca3af;
            font-size: 9px;
            color: #6b7280;
        }
        @endif

        /* ── Print overrides ─────────────────── */
        @media print {
            .toolbar { display: none !important; }
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }
            .paper {
                margin: 0;
                padding: {{ $isSmall ? '2mm' : '0' }};
                box-shadow: none;
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>
<body>

    {{-- ── Toolbar ─────────────────── --}}
    <div class="toolbar">
        <div class="left">
            <span class="size-badge">
                @if($isSmall) 🧾 Recibo 80mm
                @elseif($size === 'mediano') 📄 Media carta
                @else 📄 Carta
                @endif
            </span>
            <span style="font-size: 12px; color: #6b7280;">
                {{ $documentTemplate->name }}
                @if($loan) — Préstamo PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }} @endif
            </span>
        </div>
        <div class="right">
            <button class="btn-print" onclick="window.print()" title="Imprimir o guardar como PDF (Ctrl+P)">
                🖨️ Imprimir / PDF
            </button>
            <button class="btn-direct" onclick="directPrint()" title="Imprimir directamente a impresora térmica (sin diálogo)">
                ⚡ Impresión directa
            </button>
            <button onclick="window.close()" title="Cerrar ventana">✕ Cerrar</button>
        </div>
    </div>

    {{-- ── Paper ─────────────────── --}}
    <div class="paper" id="print-area">
        <div class="doc-header">
            <h1 class="doc-title">{{ $documentTemplate->name }}</h1>
            <p class="doc-meta">Tipo: {{ $documentTemplate->type_label }}</p>
            @if($loan)
                <p class="doc-meta">Préstamo PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }} — {{ $loan->client?->name ?? 'Sin cliente' }}</p>
            @endif
            <p class="doc-meta">Generado: {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <div class="doc-content">
            {!! $renderedContent !!}
        </div>

        @if($isSmall)
            <div class="doc-footer">
                {{ $loan?->company?->name ?? '' }}<br>
                {{ now()->format('d/m/Y H:i') }}
            </div>
        @endif
    </div>

    <script>
        /**
         * Direct print: opens the print dialog and auto-closes the window
         * after printing (works best with thermal printers set as default).
         *
         * For true silent printing (no dialog), you'd need QZ Tray or similar.
         * This approach is the simplest browser-native method.
         */
        function directPrint() {
            // Focus window to ensure it's active
            window.focus();

            // Use matchMedia to detect when print finishes
            const mediaQuery = window.matchMedia('print');
            const handler = (mq) => {
                if (!mq.matches) {
                    // Print dialog closed — auto close window after a short delay
                    mediaQuery.removeEventListener('change', handler);
                    setTimeout(() => window.close(), 300);
                }
            };
            mediaQuery.addEventListener('change', handler);

            window.print();
        }

        // Keyboard shortcut: Ctrl+P triggers our print
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
