-- =============================================================
-- Plantillas de comprobante de compra
-- Formatos: pequeno termico y media carta
-- Fecha: 2026-04-24
-- =============================================================

-- 1) COMPROBANTE COMPRA - PEQUENO (80mm)
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Comprobante Compra - Pequeno',
    'recibo',
    'Comprobante de compra para impresora termica (80mm).',
    '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px; color:#000;">
<strong style="font-size:13px;">{{empresa_nombre}}</strong><br>
<span>{{compra_sucursal}}</span><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<strong>COMPROBANTE DE COMPRA</strong><br>
<span>{{compra_fecha}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; border-collapse:collapse; font-family:Courier New, monospace; font-size:10px; color:#000;">
<tr><td style="padding:2px 0;"><strong>Codigo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_codigo}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Cliente:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>CI/NIT:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{cliente_cedula}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Producto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_producto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Categoria:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_categoria}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Monto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>Bs {{compra_monto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Caja:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_caja}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Usuario:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_registrado_por}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px; color:#000;">
Gracias por su preferencia.<br>
Conserve este comprobante.
</div>',
    'pequeno',
    1,
    NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM document_templates
    WHERE company_id IS NULL
      AND name = 'Comprobante Compra - Pequeno'
      AND paper_size = 'pequeno'
      AND deleted_at IS NULL
);

-- 2) COMPROBANTE COMPRA - MEDIA CARTA
INSERT INTO document_templates (company_id, name, type, description, content, paper_size, active, created_by)
SELECT
    NULL,
    'Comprobante Compra - Media Carta',
    'recibo',
    'Comprobante de compra para impresion media carta.',
    '<div style="text-align:center; font-family:Arial, sans-serif; color:#000;">
<h3 style="margin:0;"><strong>{{empresa_nombre}}</strong></h3>
<p style="margin:2px 0; font-size:11px;"><strong>{{compra_sucursal}}</strong></p>
<hr>
<h4 style="margin:8px 0;"><strong>COMPROBANTE DE COMPRA</strong></h4>
</div>

<table style="width:100%; border-collapse:collapse; font-size:12px; color:#000;">
<tr><td style="padding:5px; border:1px solid #000; width:38%;"><strong>Codigo</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_codigo}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Fecha</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_fecha}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Cliente</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>CI / NIT</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{cliente_cedula}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Producto</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_producto}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Categoria</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_categoria}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Monto</strong></td><td style="padding:5px; border:1px solid #000;"><strong>Bs {{compra_monto}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Estado</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_estado}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Caja</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_caja}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Registrado por</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_registrado_por}}</strong></td></tr>
</table>

<div style="margin-top:22px; font-size:11px; color:#000;">
<table style="width:100%;">
<tr>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;"><strong>Firma vendedor</strong></td>
<td style="width:10%;"></td>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;"><strong>Firma responsable</strong></td>
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
      AND name = 'Comprobante Compra - Media Carta'
      AND paper_size = 'mediano'
      AND deleted_at IS NULL
);

-- 3) UPDATE si ya existian y el script fue ejecutado previamente
UPDATE document_templates
SET
    type = 'recibo',
    description = 'Comprobante de compra para impresora termica (80mm).',
    content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px; color:#000;">
<strong style="font-size:13px;">{{empresa_nombre}}</strong><br>
<span>{{compra_sucursal}}</span><br>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<strong>COMPROBANTE DE COMPRA</strong><br>
<span>{{compra_fecha}}</span>
<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
</div>

<table style="width:100%; border-collapse:collapse; font-family:Courier New, monospace; font-size:10px; color:#000;">
<tr><td style="padding:2px 0;"><strong>Codigo:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_codigo}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Cliente:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>CI/NIT:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{cliente_cedula}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Producto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_producto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Categoria:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_categoria}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Monto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>Bs {{compra_monto}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Caja:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_caja}}</strong></td></tr>
<tr><td style="padding:2px 0;"><strong>Usuario:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{compra_registrado_por}}</strong></td></tr>
</table>

<hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
<div style="text-align:center; font-family:Courier New, monospace; font-size:9px; color:#000;">
Gracias por su preferencia.<br>
Conserve este comprobante.
</div>',
    active = 1
WHERE name = 'Comprobante Compra - Pequeno'
  AND paper_size = 'pequeno'
  AND deleted_at IS NULL;

UPDATE document_templates
SET
    type = 'recibo',
    description = 'Comprobante de compra para impresion media carta.',
    content = '<div style="text-align:center; font-family:Arial, sans-serif; color:#000;">
<h3 style="margin:0;"><strong>{{empresa_nombre}}</strong></h3>
<p style="margin:2px 0; font-size:11px;"><strong>{{compra_sucursal}}</strong></p>
<hr>
<h4 style="margin:8px 0;"><strong>COMPROBANTE DE COMPRA</strong></h4>
</div>

<table style="width:100%; border-collapse:collapse; font-size:12px; color:#000;">
<tr><td style="padding:5px; border:1px solid #000; width:38%;"><strong>Codigo</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_codigo}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Fecha</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_fecha}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Cliente</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{cliente_nombre}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>CI / NIT</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{cliente_cedula}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Producto</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_producto}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Categoria</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_categoria}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Monto</strong></td><td style="padding:5px; border:1px solid #000;"><strong>Bs {{compra_monto}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Estado</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_estado}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Caja</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_caja}}</strong></td></tr>
<tr><td style="padding:5px; border:1px solid #000;"><strong>Registrado por</strong></td><td style="padding:5px; border:1px solid #000;"><strong>{{compra_registrado_por}}</strong></td></tr>
</table>

<div style="margin-top:22px; font-size:11px; color:#000;">
<table style="width:100%;">
<tr>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;"><strong>Firma vendedor</strong></td>
<td style="width:10%;"></td>
<td style="text-align:center; width:45%; border-top:1px solid #000; padding-top:6px;"><strong>Firma responsable</strong></td>
</tr>
</table>
</div>',
    active = 1
WHERE name = 'Comprobante Compra - Media Carta'
  AND paper_size = 'mediano'
  AND deleted_at IS NULL;
