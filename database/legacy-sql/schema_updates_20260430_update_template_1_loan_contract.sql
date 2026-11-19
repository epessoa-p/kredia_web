-- =============================================================
-- Ajuste plantilla ID=1 para contrato de prestamo
-- Fecha: 2026-04-30
-- =============================================================

UPDATE document_templates
SET
    type = 'contrato',
    name = 'Contrato de Prestamo - Base',
    description = 'Plantilla base de contrato de prestamo con datos del cliente, prestamo y detalle de producto por categoria.',
    paper_size = 'grande',
    content = '<div style="text-align:center; margin-bottom:16px;">
<h2 style="margin:0;">{{empresa_nombre}}</h2>
<p style="margin:4px 0; font-size:12px; color:#666;">{{sucursal_nombre}}</p>
<hr>
<h3 style="margin:6px 0;">CONTRATO DE PRESTAMO CON GARANTIA</h3>
<p style="margin:0; font-size:12px;"><strong>N de prestamo:</strong> {{prestamo_id}}</p>
</div>

<p>En fecha <strong>{{fecha_actual}}</strong>, las partes acuerdan el presente contrato:</p>

<p><strong>ACREEDOR:</strong> {{empresa_nombre}} (Sucursal: {{sucursal_nombre}}).</p>

<p><strong>DEUDOR:</strong> {{cliente_nombre}}, con CI/NIT <strong>{{cliente_cedula}}</strong>, telefono {{cliente_telefono}},
correo {{cliente_email}}, domicilio {{cliente_direccion}}.</p>

<p><strong>PRIMERA — OBJETO:</strong> El ACREEDOR otorga al DEUDOR un prestamo identificado con N <strong>{{prestamo_id}}</strong>, por la suma de <strong>Bs {{prestamo_monto}}</strong>.</p>

<h4 style="margin:14px 0 8px;">SEGUNDA — CONDICIONES DEL PRESTAMO</h4>
<table style="width:100%; border-collapse:collapse; font-size:13px; margin-bottom:10px;">
  <tr>
    <td style="padding:4px 0;"><strong>Monto prestado:</strong></td>
    <td style="padding:4px 0; text-align:right;">Bs {{prestamo_monto}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Interes (%):</strong></td>
    <td style="padding:4px 0; text-align:right;">{{prestamo_interes_porcentaje}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Periodicidad:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{prestamo_periodo}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Plazo:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{prestamo_plazo}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Fecha inicio:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{fecha_inicio}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Fecha vencimiento:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{fecha_fin}}</td>
  </tr>
</table>

<h4 style="margin:14px 0 8px;">TERCERA — GARANTIA / PRODUCTO</h4>
<table style="width:100%; border-collapse:collapse; font-size:13px; margin-bottom:10px;">
  <tr>
    <td style="padding:4px 0;"><strong>Producto:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_nombre}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Categoria:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_categoria}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Marca:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_marca}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Modelo/Ano:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_modelo_anio}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Placa:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_placa}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Chasis:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_chasis}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Serie:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_serie}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Kilataje:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_kilataje}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Tipo:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_tipo}}</td>
  </tr>
  <tr>
    <td style="padding:4px 0;"><strong>Descripcion:</strong></td>
    <td style="padding:4px 0; text-align:right;">{{producto_descripcion}}</td>
  </tr>
</table>

<p><strong>Detalle segun categoria:</strong> {{producto_detalles_categoria}}</p>

<p><strong>CUARTA — INTERES Y PLAZO:</strong> El DEUDOR acepta una tasa de interes de <strong>{{prestamo_interes_porcentaje}}</strong>, bajo periodicidad <strong>{{prestamo_periodo}}</strong>, con plazo total de <strong>{{prestamo_plazo}}</strong>.</p>

<p><strong>QUINTA — DECLARACION DE CONFORMIDAD:</strong> Las partes declaran su plena conformidad con las condiciones del presente prestamo y la garantia detallada en este documento.</p>

<br><br>
<table style="width:100%; border-collapse:collapse; margin-top:24px;">
  <tr>
    <td style="width:50%; text-align:center;">
      <div style="border-top:1px solid #333; width:80%; margin:0 auto 4px;"></div>
      <small>Firma acreedor</small>
    </td>
    <td style="width:50%; text-align:center;">
      <div style="border-top:1px solid #333; width:80%; margin:0 auto 4px;"></div>
      <small>Firma deudor</small>
    </td>
  </tr>
</table>'
WHERE id = 1;
