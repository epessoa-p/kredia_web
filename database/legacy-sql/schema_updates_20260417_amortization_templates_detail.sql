-- =============================================================
-- Refresh de plantillas de amortizacion (detalle de montos)
-- Fecha: 2026-04-17
-- Objetivo:
--   - Reducir encabezados grandes
--   - Aumentar detalle del monto amortizado
--   - Mantener compatibilidad con placeholders de show.blade.php
-- =============================================================

-- AMORTIZACION - PEQUENO (80mm)
UPDATE document_templates
SET
    content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px;">
<strong style="font-size:12px;">{{empresa_nombre}}</strong><br>
<span>{{sucursal_nombre}}</span><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<span style="font-size:11px; font-weight:700;">RECIBO</span><br>
<span>{{amortizacion_fecha}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; font-family:Courier New, monospace; font-size:10px; border-collapse:collapse;">
<tr><td>Prestamo:</td><td style="text-align:right;">{{prestamo_id}}</td></tr>
<tr><td>Cliente:</td><td style="text-align:right;">{{cliente_nombre}}</td></tr>
<tr><td>CI:</td><td style="text-align:right;">{{cliente_cedula}}</td></tr>
<tr><td>Monto amort.:</td><td style="text-align:right;">Bs {{amortizacion_monto}}</td></tr>
<tr><td>Capital antes:</td><td style="text-align:right;">{{capital_vigente_antes}}</td></tr>
<tr><td>Capital despues:</td><td style="text-align:right;">{{capital_vigente_despues}}</td></tr>
<tr><td>% amortizado:</td><td style="text-align:right;">{{amortizacion_porcentaje}}</td></tr>
<tr><td>Metodo:</td><td style="text-align:right;">{{amortizacion_metodo}}</td></tr>
<tr><td>Ref:</td><td style="text-align:right;">{{amortizacion_referencia}}</td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px;">
Operacion de amortizacion registrada.<br>
Conserve este recibo.
</div>',
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND name = 'Recibo Amortizacion - Pequeno';

-- AMORTIZACION - MEDIA CARTA
UPDATE document_templates
SET
    content = '<div style="text-align:center; font-family:Arial, sans-serif;">
<p style="margin:0; font-size:18px; font-weight:700;">{{empresa_nombre}}</p>
<p style="margin:2px 0; font-size:11px;">{{sucursal_nombre}}</p>
<hr>
<p style="margin:6px 0; font-size:14px; font-weight:700;">RECIBO DE AMORTIZACION</p>
</div>

<table style="width:100%; border-collapse:collapse; font-size:12px;">
<tr><td style="padding:4px; border:1px solid #ddd; width:38%;"><strong>Fecha</strong></td><td style="padding:4px; border:1px solid #ddd;">{{amortizacion_fecha}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Prestamo</strong></td><td style="padding:4px; border:1px solid #ddd;">{{prestamo_id}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cliente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_nombre}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cliente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_nombre}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cedula</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_cedula}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Monto amortizado</strong></td><td style="padding:4px; border:1px solid #ddd;">Bs {{amortizacion_monto}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Capital antes</strong></td><td style="padding:4px; border:1px solid #ddd;">{{capital_vigente_antes}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Capital despues</strong></td><td style="padding:4px; border:1px solid #ddd;">{{capital_vigente_despues}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>% amortizado</strong></td><td style="padding:4px; border:1px solid #ddd;">{{amortizacion_porcentaje}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Metodo</strong></td><td style="padding:4px; border:1px solid #ddd;">{{amortizacion_metodo}}</td></tr>
<tr><td style="padding:4px; border:1px solid #ddd;"><strong>Referencia</strong></td><td style="padding:4px; border:1px solid #ddd;">{{amortizacion_referencia}}</td></tr>
</table>

<p style="margin-top:10px; font-size:11px;">{{amortizacion_notas}}</p>

<div style="margin-top:24px; font-size:11px;">
<table style="width:100%;">
<tr>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma cliente</td>
<td style="width:10%;"></td>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;">Firma responsable</td>
</tr>
</table>
</div>',
    updated_at = NOW()
WHERE deleted_at IS NULL
  AND name = 'Recibo Amortizacion - Media Carta';
