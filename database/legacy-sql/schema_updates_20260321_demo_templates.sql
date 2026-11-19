-- =============================================================
-- Demo: Plantilla de boleta para impresora térmica 80mm
-- Fecha: 2026-03-21
-- Ejecutar DESPUÉS de schema_updates_20260321_paper_size.sql
-- =============================================================

INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
VALUES (
    NULL,
    'Boleta de Pago - Térmica 80mm',
    'boleta',
    'Boleta compacta diseñada para impresión en papel térmico 80mm. Ideal para impresoras de recibos como Knup KP-IM609.',
    '<div style="text-align:center; font-family: ''Courier New'', monospace;">
<strong style="font-size:14px;">{{empresa_nombre}}</strong><br>
<span style="font-size:9px;">{{sucursal_nombre}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<strong>BOLETA DE PAGO</strong><br>
<span style="font-size:9px;">{{fecha_actual}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; font-size:10px; border-collapse:collapse;">
<tr>
  <td style="padding:2px 0;"><strong>Cliente:</strong></td>
  <td style="padding:2px 0; text-align:right;">{{cliente_nombre}}</td>
</tr>
<tr>
  <td style="padding:2px 0;"><strong>Cédula:</strong></td>
  <td style="padding:2px 0; text-align:right;">{{cliente_cedula}}</td>
</tr>
<tr>
  <td style="padding:2px 0;"><strong>Préstamo:</strong></td>
  <td style="padding:2px 0; text-align:right;">{{prestamo_id}}</td>
</tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">

<table style="width:100%; font-size:10px; border-collapse:collapse;">
<tr>
  <td style="padding:2px 0;"><strong>Monto:</strong></td>
  <td style="padding:2px 0; text-align:right;">$ {{prestamo_monto}}</td>
</tr>
<tr>
  <td style="padding:2px 0;"><strong>Tasa:</strong></td>
  <td style="padding:2px 0; text-align:right;">{{prestamo_tasa}}</td>
</tr>
<tr>
  <td style="padding:2px 0;"><strong>Plazo:</strong></td>
  <td style="padding:2px 0; text-align:right;">{{prestamo_plazo}}</td>
</tr>
<tr>
  <td style="padding:2px 0;"><strong>Cuota:</strong></td>
  <td style="padding:2px 0; text-align:right;">$ {{prestamo_cuota}}</td>
</tr>
<tr style="font-size:11px;">
  <td style="padding:4px 0; border-top:1px solid #000;"><strong>TOTAL:</strong></td>
  <td style="padding:4px 0; border-top:1px solid #000; text-align:right;"><strong>$ {{prestamo_total}}</strong></td>
</tr>
<tr>
  <td style="padding:2px 0;"><strong>Saldo:</strong></td>
  <td style="padding:2px 0; text-align:right;">$ {{prestamo_saldo}}</td>
</tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">

<div style="text-align:center; font-size:9px;">
<strong>Período</strong><br>
{{fecha_inicio}} — {{fecha_fin}}
<br><br>
<span style="font-size:8px;">Gracias por su pago</span><br>
<span style="font-size:8px;">Conserve este comprobante</span>
</div>',
    'pequeno',
    1,
    NULL
);

-- Demo: Contrato tamaño carta (grande)
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
VALUES (
    NULL,
    'Contrato Estándar - Carta',
    'contrato',
    'Contrato de préstamo formato carta estándar. Plantilla de demostración.',
    '<div style="text-align:center; margin-bottom: 20px;">
<h2 style="margin:0;">{{empresa_nombre}}</h2>
<p style="margin:4px 0; font-size:12px; color:#666;">{{sucursal_nombre}}</p>
<hr>
<h3>CONTRATO DE PRÉSTAMO</h3>
</div>

<p>En la ciudad de _____________, a los <strong>{{fecha_actual}}</strong>, entre las partes:</p>

<p><strong>ACREEDOR:</strong> {{empresa_nombre}}, representada por su sucursal {{sucursal_nombre}}.</p>

<p><strong>DEUDOR:</strong> {{cliente_nombre}}, identificado(a) con cédula <strong>{{cliente_cedula}}</strong>, 
con domicilio en {{cliente_direccion}}, teléfono {{cliente_telefono}}, 
correo electrónico {{cliente_email}}.</p>

<h4>CLÁUSULAS</h4>

<p><strong>PRIMERA — OBJETO:</strong> El Acreedor otorga al Deudor un préstamo por la cantidad de 
<strong>$ {{prestamo_monto}}</strong> (monto en letras), identificado con el número <strong>{{prestamo_id}}</strong>.</p>

<p><strong>SEGUNDA — TASA DE INTERÉS:</strong> El préstamo devengará una tasa de interés de 
<strong>{{prestamo_tasa}}</strong> sobre el saldo del capital.</p>

<p><strong>TERCERA — PLAZO Y CUOTAS:</strong> El plazo del préstamo será de <strong>{{prestamo_plazo}}</strong>, 
con cuotas mensuales de <strong>$ {{prestamo_cuota}}</strong>, para un total a pagar de 
<strong>$ {{prestamo_total}}</strong>.</p>

<p><strong>CUARTA — VIGENCIA:</strong> El presente contrato entrará en vigor a partir del 
<strong>{{fecha_inicio}}</strong> y tendrá como fecha de vencimiento el <strong>{{fecha_fin}}</strong>.</p>

<p><strong>QUINTA — SALDO:</strong> El saldo pendiente a la fecha de generación de este documento es de 
<strong>$ {{prestamo_saldo}}</strong>.</p>

<br><br>

<table style="width:100%; margin-top:40px;">
<tr>
  <td style="width:45%; text-align:center; padding-top:10px;">
    {{firma_prestamista}}
    <div style="border-top:1px solid #000; padding-top:6px; margin-top:6px;">
    <strong>EL ACREEDOR</strong><br>
    {{empresa_nombre}}
    </div>
  </td>
  <td style="width:10%;"></td>
  <td style="width:45%; text-align:center; padding-top:10px;">
    {{firma_cliente}}
    <div style="border-top:1px solid #000; padding-top:6px; margin-top:6px;">
    <strong>EL DEUDOR</strong><br>
    {{cliente_nombre}}<br>
    <small>Cédula: {{cliente_cedula}}</small>
    </div>
  </td>
</tr>
</table>',
    'grande',
    1,
    NULL
);

-- Demo: Recibo media carta (mediano)
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
VALUES (
    NULL,
    'Recibo de Pago - Media Carta',
    'recibo',
    'Recibo compacto en media carta. Ideal para impresión rápida en hojas cortadas.',
    '<div style="text-align:center;">
<h3 style="margin:0;">{{empresa_nombre}}</h3>
<p style="margin:2px 0; font-size:11px; color:#666;">{{sucursal_nombre}}</p>
<hr>
<h4 style="margin:8px 0;">RECIBO DE PAGO</h4>
</div>

<table style="width:100%; font-size:12px; border-collapse:collapse; margin-top:10px;">
<tr>
  <td style="padding:4px 8px; border:1px solid #ddd; background:#f5f5f5; width:35%;"><strong>Fecha:</strong></td>
  <td style="padding:4px 8px; border:1px solid #ddd;">{{fecha_actual}}</td>
</tr>
<tr>
  <td style="padding:4px 8px; border:1px solid #ddd; background:#f5f5f5;"><strong>Cliente:</strong></td>
  <td style="padding:4px 8px; border:1px solid #ddd;">{{cliente_nombre}}</td>
</tr>
<tr>
  <td style="padding:4px 8px; border:1px solid #ddd; background:#f5f5f5;"><strong>Cédula:</strong></td>
  <td style="padding:4px 8px; border:1px solid #ddd;">{{cliente_cedula}}</td>
</tr>
<tr>
  <td style="padding:4px 8px; border:1px solid #ddd; background:#f5f5f5;"><strong>Préstamo:</strong></td>
  <td style="padding:4px 8px; border:1px solid #ddd;">{{prestamo_id}}</td>
</tr>
<tr>
  <td style="padding:4px 8px; border:1px solid #ddd; background:#f5f5f5;"><strong>Monto:</strong></td>
  <td style="padding:4px 8px; border:1px solid #ddd;">$ {{prestamo_monto}}</td>
</tr>
<tr>
  <td style="padding:4px 8px; border:1px solid #ddd; background:#f5f5f5;"><strong>Cuota:</strong></td>
  <td style="padding:4px 8px; border:1px solid #ddd;">$ {{prestamo_cuota}}</td>
</tr>
<tr style="font-weight:bold; font-size:13px;">
  <td style="padding:4px 8px; border:1px solid #ddd; background:#f5f5f5;"><strong>Saldo pendiente:</strong></td>
  <td style="padding:4px 8px; border:1px solid #ddd;">$ {{prestamo_saldo}}</td>
</tr>
</table>

<div style="text-align:center; margin-top:15px; font-size:10px; color:#888;">
Período: {{fecha_inicio}} — {{fecha_fin}}<br>
Conserve este recibo como comprobante de pago.
</div>',
    'mediano',
    1,
    NULL
);
