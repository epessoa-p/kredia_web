-- =============================================================
-- Plantillas de recibo para tabs de prestamo
-- Amortizacion, Liquidacion y Multa (media carta y pequeno)
-- Fecha: 2026-04-09
-- Requiere: schema_updates_20260321_paper_size.sql
-- =============================================================

-- 1) AMORTIZACION - MEDIA CARTA
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Recibo Amortizacion - Media Carta',
    'recibo',
    'Recibo para tab de amortizacion en formato media carta.',
    '<div style="text-align:center; font-family:Arial, sans-serif;">
<h3 style="margin:0;">{{empresa_nombre}}</h3>
<p style="margin:2px 0; font-size:11px;">{{sucursal_nombre}}</p>
<hr>
<h4 style="margin:8px 0;">RECIBO DE AMORTIZACION</h4>
</div>

<table style="width:100%; border-collapse:collapse; font-size:12px;">
<tr><td style="padding:4px; border:1px solid #ddd; width:38%;"><strong>Fecha</strong></td><td style="padding:4px; border:1px solid #ddd;">{{fecha_actual}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Prestamo</strong></td><td style="padding:4px; border:1px solid #ddd;">{{prestamo_id}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cliente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_nombre}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cedula</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_cedula}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Capital pendiente</strong></td><td style="padding:4px; border:1px solid #ddd;">Bs {{prestamo_saldo}}</td></tr>
</table>

<p style="margin-top:12px; font-size:11px;">
Este recibo corresponde a un abono de amortizacion al capital del prestamo.
</p>

<div style="margin-top:30px; font-size:11px;">
<table style="width:100%;">
<tr>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma cliente</td>
<td style="width:10%;"></td>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma responsable</td>
</tr>
</table>
</div>',
    'mediano',
    1,
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM document_templates
    WHERE company_id IS NULL
      AND name = 'Recibo Amortizacion - Media Carta'
      AND paper_size = 'mediano'
      AND deleted_at IS NULL
);

-- 2) AMORTIZACION - PEQUENO (80mm)
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Recibo Amortizacion - Pequeno',
    'recibo',
    'Recibo para tab de amortizacion en formato pequeno termico.',
    '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px;">
<strong style="font-size:13px;">{{empresa_nombre}}</strong><br>
<span>{{sucursal_nombre}}</span><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<strong>RECIBO AMORTIZACION</strong><br>
<span>{{fecha_actual}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; border-collapse:collapse;">
<tr><td><strong>Prestamo:</strong></td><td style="text-align:right;">{{prestamo_id}}</td></tr>
<tr><td><strong>Cliente:</strong></td><td style="text-align:right;">{{cliente_nombre}}</td></tr>
<tr><td><strong>Cedula:</strong></td><td style="text-align:right;">{{cliente_cedula}}</td></tr>
<tr><td><strong>Saldo cap.:</strong></td><td style="text-align:right;">Bs {{prestamo_saldo}}</td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px;">
Abono aplicado al capital del prestamo.<br>
Conserve este recibo.
</div>',
    'pequeno',
    1,
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM document_templates
    WHERE company_id IS NULL
      AND name = 'Recibo Amortizacion - Pequeno'
      AND paper_size = 'pequeno'
      AND deleted_at IS NULL
);

-- 3) LIQUIDACION - MEDIA CARTA
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Recibo Liquidacion - Media Carta',
    'recibo',
    'Recibo para tab de liquidacion en formato media carta.',
    '<div style="text-align:center; font-family:Arial, sans-serif;">
<h3 style="margin:0;">{{empresa_nombre}}</h3>
<p style="margin:2px 0; font-size:11px;">{{sucursal_nombre}}</p>
<hr>
<h4 style="margin:8px 0;">RECIBO DE LIQUIDACION</h4>
</div>

<table style="width:100%; border-collapse:collapse; font-size:12px;">
<tr><td style="padding:4px; border:1px solid #ddd; width:38%;"><strong>Fecha</strong></td><td style="padding:4px; border:1px solid #ddd;">{{fecha_actual}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Prestamo</strong></td><td style="padding:4px; border:1px solid #ddd;">{{prestamo_id}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cliente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_nombre}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cedula</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_cedula}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Capital vigente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_capital_vigente}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Intereses pendientes</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_interes_pendiente}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Multa</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_multa}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Total liquidacion</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_total}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Monto pagado</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_monto}}</td></tr>
</table>

<p style="margin-top:12px; font-size:11px;">
Este recibo deja constancia de la liquidacion total del prestamo.
</p>

<div style="margin-top:30px; font-size:11px;">
<table style="width:100%;">
<tr>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma cliente</td>
<td style="width:10%;"></td>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma responsable</td>
</tr>
</table>
</div>',
    'mediano',
    1,
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM document_templates
    WHERE company_id IS NULL
      AND name = 'Recibo Liquidacion - Media Carta'
      AND paper_size = 'mediano'
      AND deleted_at IS NULL
);

-- 4) LIQUIDACION - PEQUENO (80mm)
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Recibo Liquidacion - Pequeno',
    'recibo',
    'Recibo para tab de liquidacion en formato pequeno termico.',
    '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px;">
<strong style="font-size:13px;">{{empresa_nombre}}</strong><br>
<span>{{sucursal_nombre}}</span><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<strong>RECIBO LIQUIDACION</strong><br>
<span>{{fecha_actual}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; border-collapse:collapse;">
<tr><td><strong>Prestamo:</strong></td><td style="text-align:right;">{{prestamo_id}}</td></tr>
<tr><td><strong>Cliente:</strong></td><td style="text-align:right;">{{cliente_nombre}}</td></tr>
<tr><td><strong>Cedula:</strong></td><td style="text-align:right;">{{cliente_cedula}}</td></tr>
<tr><td><strong>Capital:</strong></td><td style="text-align:right;">{{liquidacion_capital_vigente}}</td></tr>
<tr><td><strong>Interes:</strong></td><td style="text-align:right;">{{liquidacion_interes_pendiente}}</td></tr>
<tr><td><strong>Multa:</strong></td><td style="text-align:right;">{{liquidacion_multa}}</td></tr>
<tr><td><strong>Total:</strong></td><td style="text-align:right;">{{liquidacion_total}}</td></tr>
<tr><td><strong>Pago:</strong></td><td style="text-align:right;">{{liquidacion_monto}}</td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px;">
Prestamo liquidado en su totalidad.<br>
Conserve este recibo.
</div>',
    'pequeno',
    1,
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM document_templates
    WHERE company_id IS NULL
      AND name = 'Recibo Liquidacion - Pequeno'
      AND paper_size = 'pequeno'
      AND deleted_at IS NULL
);

-- 5) MULTA - MEDIA CARTA
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Recibo Multa - Media Carta',
    'recibo',
    'Recibo para tab de multa en formato media carta.',
    '<div style="text-align:center; font-family:Arial, sans-serif;">
<h3 style="margin:0;">{{empresa_nombre}}</h3>
<p style="margin:2px 0; font-size:11px;">{{sucursal_nombre}}</p>
<hr>
<h4 style="margin:8px 0;">RECIBO DE MULTA</h4>
</div>

<table style="width:100%; border-collapse:collapse; font-size:12px;">
<tr><td style="padding:4px; border:1px solid #ddd; width:38%;"><strong>Fecha</strong></td><td style="padding:4px; border:1px solid #ddd;">{{fecha_actual}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Prestamo</strong></td><td style="padding:4px; border:1px solid #ddd;">{{prestamo_id}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cliente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_nombre}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cedula</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_cedula}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Referencia</strong></td><td style="padding:4px; border:1px solid #ddd;">Multa por mora</td></tr>
</table>

<p style="margin-top:12px; font-size:11px;">
Recibo emitido por concepto de multa del prestamo.
</p>

<div style="margin-top:30px; font-size:11px;">
<table style="width:100%;">
<tr>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma cliente</td>
<td style="width:10%;"></td>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma responsable</td>
</tr>
</table>
</div>',
    'mediano',
    1,
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM document_templates
    WHERE company_id IS NULL
      AND name = 'Recibo Multa - Media Carta'
      AND paper_size = 'mediano'
      AND deleted_at IS NULL
);

-- 6) MULTA - PEQUENO (80mm)
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Recibo Multa - Pequeno',
    'recibo',
    'Recibo para tab de multa en formato pequeno termico.',
    '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px;">
<strong style="font-size:13px;">{{empresa_nombre}}</strong><br>
<span>{{sucursal_nombre}}</span><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<strong>RECIBO MULTA</strong><br>
<span>{{fecha_actual}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; border-collapse:collapse;">
<tr><td><strong>Prestamo:</strong></td><td style="text-align:right;">{{prestamo_id}}</td></tr>
<tr><td><strong>Cliente:</strong></td><td style="text-align:right;">{{cliente_nombre}}</td></tr>
<tr><td><strong>Cedula:</strong></td><td style="text-align:right;">{{cliente_cedula}}</td></tr>
<tr><td><strong>Concepto:</strong></td><td style="text-align:right;">Multa mora</td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px;">
Pago por concepto de multa del prestamo.<br>
Conserve este recibo.
</div>',
    'pequeno',
    1,
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM document_templates
    WHERE company_id IS NULL
      AND name = 'Recibo Multa - Pequeno'
      AND paper_size = 'pequeno'
      AND deleted_at IS NULL
);
