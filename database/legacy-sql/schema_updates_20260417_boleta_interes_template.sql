-- =============================================================
-- Actualización de plantilla "Boleta de Pago - Térmica 80mm"
-- Fecha: 2026-04-17
-- Objetivo:
--   - Usar tokens de interés en lugar de tokens de préstamo
--   - Quitar encabezado grande (no hay h5 JS porque se pasa showContextTitle=false)
--   - Mostrar capital base, tasa, monto de interés del periodo
--   - Periodo = fecha de vencimiento del periodo
-- =============================================================

UPDATE document_templates
SET
    content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px;">
<strong style="font-size:12px;">{{empresa_nombre}}</strong><br>
<span>{{sucursal_nombre}}</span><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<span style="font-size:11px; font-weight:700;">BOLETA DE PAGO</span><br>
<span>{{interes_pago_fecha}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; border-collapse:collapse;">
<tr><td>Cliente:</td><td style="text-align:right;">{{cliente_nombre}}</td></tr>
<tr><td>CI:</td><td style="text-align:right;">{{cliente_cedula}}</td></tr>
<tr><td>Prestamo:</td><td style="text-align:right;">{{prestamo_id}}</td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; border-collapse:collapse;">
<tr><td>Capital base:</td><td style="text-align:right;">{{interes_base_capital}}</td></tr>
<tr><td>Tasa de interes:</td><td style="text-align:right;">{{interes_tasa}}</td></tr>
<tr><td>Interes del periodo:</td><td style="text-align:right;">{{interes_total_periodo}}</td></tr>
<tr style="font-size:11px;">
  <td style="padding-top:4px; border-top:1px solid #000;"><strong>Monto pagado:</strong></td>
  <td style="padding-top:4px; border-top:1px solid #000; text-align:right;"><strong>{{interes_pago_monto}}</strong></td>
</tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; border-collapse:collapse;">
<tr><td>Metodo:</td><td style="text-align:right;">{{interes_pago_metodo}}</td></tr>
<tr><td>Ref:</td><td style="text-align:right;">{{interes_pago_referencia}}</td></tr>
<tr><td>Fecha venc.:</td><td style="text-align:right;">{{interes_fecha_vencimiento}}</td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px;">
Gracias por su pago<br>
Conserve este comprobante
</div>',
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND name = 'Boleta de Pago - Térmica 80mm';
