-- =============================================================
-- Plantillas termicas: contrato corto + refuerzo visual en negro
-- Fecha: 2026-04-24
-- Objetivo:
--   1) Crear/actualizar una plantilla pequena para contrato termico.
--   2) Reforzar en negrita las plantillas pequenas de interes,
--      amortizacion y liquidacion para impresoras termicas.
-- =============================================================

START TRANSACTION;

-- 1) CONTRATO TERMICO RESUMIDO (80mm)
INSERT INTO document_templates (
    company_id,
    name,
    type,
    description,
    content,
    paper_size,
    active,
    created_by,
    created_at,
    updated_at
)
SELECT
    c.id,
    'Contrato Resumen - Pequeno',
    'contrato',
    'Comprobante corto para impresion termica de prestamos.',
    '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000;">
<strong style="font-size:13px; font-weight:700;">{{empresa_nombre}}</strong><br>
<strong style="font-size:10px; font-weight:700;">{{sucursal_nombre}}</strong><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<strong style="font-size:11px; font-weight:700;">COMPROBANTE DE PRESTAMO</strong><br>
<strong style="font-size:10px; font-weight:700;">{{fecha_actual}}</strong>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000; border-collapse:collapse;">
<tr><td style="padding:2px 0;"><strong>Prestamo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_id}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Cliente:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Producto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_producto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Monto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>Bs {{prestamo_monto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Interes:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_tasa}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Periodo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_periodo}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Tiempo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_plazo}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Inicio:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{fecha_inicio}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Vence:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{fecha_fin}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px; font-weight:700; color:#000;">
<strong>Resumen rapido del contrato.</strong><br>
<strong>Conserve este comprobante.</strong>
</div>',
    'pequeno',
    1,
    NULL,
    NOW(),
    NOW()
FROM companies c
WHERE c.active = 1
  AND NOT EXISTS (
      SELECT 1
      FROM document_templates dt
      WHERE dt.company_id = c.id
        AND dt.deleted_at IS NULL
        AND dt.type = 'contrato'
        AND dt.name = 'Contrato Resumen - Pequeno'
  );

UPDATE document_templates
SET
    description = 'Comprobante corto para impresion termica de prestamos.',
    content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000;">
<strong style="font-size:13px; font-weight:700;">{{empresa_nombre}}</strong><br>
<strong style="font-size:10px; font-weight:700;">{{sucursal_nombre}}</strong><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<strong style="font-size:11px; font-weight:700;">COMPROBANTE DE PRESTAMO</strong><br>
<strong style="font-size:10px; font-weight:700;">{{fecha_actual}}</strong>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000; border-collapse:collapse;">
<tr><td style="padding:2px 0;"><strong>Prestamo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_id}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Cliente:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Producto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_producto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Monto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>Bs {{prestamo_monto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Interes:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_tasa}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Periodo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_periodo}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Tiempo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{prestamo_plazo}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Inicio:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{fecha_inicio}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Vence:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{fecha_fin}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px; font-weight:700; color:#000;">
<strong>Resumen rapido del contrato.</strong><br>
<strong>Conserve este comprobante.</strong>
</div>',
    paper_size = 'pequeno',
    active = 1,
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND type = 'contrato'
  AND name = 'Contrato Resumen - Pequeno';

-- 2) INTERES TERMICO EN NEGRO / NEGRITA
UPDATE document_templates
SET
    description = 'Boleta termica 80mm con contraste alto para pago de interes.',
    content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000;">
<strong style="font-size:12px; font-weight:700;">{{empresa_nombre}}</strong><br>
<strong style="font-size:10px; font-weight:700;">{{sucursal_nombre}}</strong><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<strong style="font-size:11px; font-weight:700;">BOLETA DE PAGO</strong><br>
<strong style="font-size:10px; font-weight:700;">{{interes_pago_fecha}}</strong>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000; border-collapse:collapse;">
<tr><td><strong>Cliente:</strong></td><td style="text-align:right;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td><strong>CI:</strong></td><td style="text-align:right;"><strong>{{cliente_cedula}}</strong></td></tr>
<tr><td><strong>Prestamo:</strong></td><td style="text-align:right;"><strong>{{prestamo_id}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000; border-collapse:collapse;">
<tr><td><strong>Capital base:</strong></td><td style="text-align:right;"><strong>{{interes_base_capital}}</strong></td></tr>
<tr><td><strong>Tasa:</strong></td><td style="text-align:right;"><strong>{{interes_tasa}}</strong></td></tr>
<tr><td><strong>Interes:</strong></td><td style="text-align:right;"><strong>{{interes_total_periodo}}</strong></td></tr>
<tr style="font-size:11px;">
  <td style="padding-top:4px; border-top:1px solid #000;"><strong>Monto pagado:</strong></td>
  <td style="padding-top:4px; border-top:1px solid #000; text-align:right;"><strong>{{interes_pago_monto}}</strong></td>
</tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000; border-collapse:collapse;">
<tr><td><strong>Metodo:</strong></td><td style="text-align:right;"><strong>{{interes_pago_metodo}}</strong></td></tr>
<tr><td><strong>Ref:</strong></td><td style="text-align:right;"><strong>{{interes_pago_referencia}}</strong></td></tr>
<tr><td><strong>Vence:</strong></td><td style="text-align:right;"><strong>{{interes_fecha_vencimiento}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px; font-weight:700; color:#000;">
<strong>Gracias por su pago</strong><br>
<strong>Conserve este comprobante</strong>
</div>',
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND paper_size = 'pequeno'
  AND (
      name = 'Boleta de Pago - Térmica 80mm'
      OR name = 'Boleta de Pago - Termica 80mm'
      OR (type IN ('recibo', 'boleta') AND (name LIKE '%Interes%' OR description LIKE '%interes%'))
  );

-- 3) AMORTIZACION TERMICA EN NEGRO / NEGRITA
UPDATE document_templates
SET
    description = 'Recibo termico de amortizacion con contraste alto.',
    content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000;">
<strong style="font-size:12px; font-weight:700;">{{empresa_nombre}}</strong><br>
<strong style="font-size:10px; font-weight:700;">{{sucursal_nombre}}</strong><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<strong style="font-size:11px; font-weight:700;">RECIBO AMORTIZACION</strong><br>
<strong style="font-size:10px; font-weight:700;">{{amortizacion_fecha}}</strong>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000; border-collapse:collapse;">
<tr><td><strong>Prestamo:</strong></td><td style="text-align:right;"><strong>{{prestamo_id}}</strong></td></tr>
<tr><td><strong>Cliente:</strong></td><td style="text-align:right;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td><strong>CI:</strong></td><td style="text-align:right;"><strong>{{cliente_cedula}}</strong></td></tr>
<tr><td><strong>Monto:</strong></td><td style="text-align:right;"><strong>{{amortizacion_monto}}</strong></td></tr>
<tr><td><strong>Capital antes:</strong></td><td style="text-align:right;"><strong>{{capital_vigente_antes}}</strong></td></tr>
<tr><td><strong>Capital desp.:</strong></td><td style="text-align:right;"><strong>{{capital_vigente_despues}}</strong></td></tr>
<tr><td><strong>% amort.:</strong></td><td style="text-align:right;"><strong>{{amortizacion_porcentaje}}</strong></td></tr>
<tr><td><strong>Metodo:</strong></td><td style="text-align:right;"><strong>{{amortizacion_metodo}}</strong></td></tr>
<tr><td><strong>Ref:</strong></td><td style="text-align:right;"><strong>{{amortizacion_referencia}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px; font-weight:700; color:#000;">
<strong>Operacion de amortizacion registrada.</strong><br>
<strong>Conserve este recibo.</strong>
</div>',
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND paper_size = 'pequeno'
  AND name = 'Recibo Amortizacion - Pequeno';

-- 4) LIQUIDACION TERMICA EN NEGRO / NEGRITA
UPDATE document_templates
SET
    description = 'Recibo termico de liquidacion con contraste alto.',
    content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000;">
<strong style="font-size:13px; font-weight:700;">{{empresa_nombre}}</strong><br>
<strong style="font-size:10px; font-weight:700;">{{sucursal_nombre}}</strong><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<strong style="font-size:11px; font-weight:700;">RECIBO LIQUIDACION</strong><br>
<strong style="font-size:10px; font-weight:700;">{{liquidacion_fecha}}</strong>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; font-weight:700; color:#000; border-collapse:collapse;">
<tr><td><strong>Prestamo:</strong></td><td style="text-align:right;"><strong>{{prestamo_id}}</strong></td></tr>
<tr><td><strong>Cliente:</strong></td><td style="text-align:right;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td><strong>Cedula:</strong></td><td style="text-align:right;"><strong>{{cliente_cedula}}</strong></td></tr>
<tr><td><strong>Capital:</strong></td><td style="text-align:right;"><strong>{{liquidacion_capital_vigente}}</strong></td></tr>
<tr><td><strong>Interes:</strong></td><td style="text-align:right;"><strong>{{liquidacion_interes_pendiente}}</strong></td></tr>
<tr><td><strong>Multa:</strong></td><td style="text-align:right;"><strong>{{liquidacion_multa}}</strong></td></tr>
<tr><td><strong>Total:</strong></td><td style="text-align:right;"><strong>{{liquidacion_total}}</strong></td></tr>
<tr><td><strong>Pago:</strong></td><td style="text-align:right;"><strong>{{liquidacion_monto}}</strong></td></tr>
<tr><td><strong>Metodo:</strong></td><td style="text-align:right;"><strong>{{liquidacion_metodo}}</strong></td></tr>
<tr><td><strong>Ref:</strong></td><td style="text-align:right;"><strong>{{liquidacion_referencia}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0; opacity:1;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px; font-weight:700; color:#000;">
<strong>Prestamo liquidado en su totalidad.</strong><br>
<strong>Conserve este recibo.</strong>
</div>',
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND paper_size = 'pequeno'
  AND name = 'Recibo Liquidacion - Pequeno';

COMMIT;