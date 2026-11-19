    -- =============================================================
    -- Actualizacion de plantillas de liquidacion ya registradas
    -- Fecha: 2026-04-23
    -- Objetivo:
    --   1) Corregir placeholders antiguos en registros existentes.
    --   2) Actualizar contenido de las 2 plantillas base de liquidacion
    --      (media carta y pequeno) con el formato vigente.
    -- =============================================================

    START TRANSACTION;

    -- 1) Migracion defensiva: plantillas antiguas que aun usan {{prestamo_saldo}}
    UPDATE document_templates
    SET
        content = REPLACE(content, '{{prestamo_saldo}}', '{{liquidacion_total}}'),
        updated_at = NOW()
    WHERE deleted_at IS NULL
    AND content LIKE '%{{prestamo_saldo}}%'
    AND (
        name LIKE '%Liquidacion%'
        OR name LIKE '%LIQUIDACION%'
        OR type = 'liquidacion'
        OR description LIKE '%liquidacion%'
    );

    -- 2) LIQUIDACION - MEDIA CARTA
    UPDATE document_templates
    SET
        description = 'Recibo para tab de liquidacion en formato media carta.',
        content = '<div style="text-align:center; font-family:Arial, sans-serif;">
    <h3 style="margin:0;">{{empresa_nombre}}</h3>
    <p style="margin:2px 0; font-size:11px;">{{sucursal_nombre}}</p>
    <hr>
    <h4 style="margin:8px 0;">RECIBO DE LIQUIDACION</h4>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr><td style="padding:4px; border:1px solid #ddd; width:38%;"><strong>Fecha</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_fecha}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Prestamo</strong></td><td style="padding:4px; border:1px solid #ddd;">{{prestamo_id}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cliente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_nombre}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Cedula</strong></td><td style="padding:4px; border:1px solid #ddd;">{{cliente_cedula}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Capital vigente</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_capital_vigente}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Intereses pendientes</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_interes_pendiente}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Multa</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_multa}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Total liquidacion</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_total}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Monto pagado</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_monto}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Metodo</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_metodo}}</td></tr>
    <tr><td style="padding:4px; border:1px solid #ddd;"><strong>Referencia</strong></td><td style="padding:4px; border:1px solid #ddd;">{{liquidacion_referencia}}</td></tr>
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
        updated_at = NOW()
    WHERE deleted_at IS NULL
    AND name = 'Recibo Liquidacion - Media Carta';

    -- 3) LIQUIDACION - PEQUENO (80mm)
    UPDATE document_templates
    SET
        description = 'Recibo para tab de liquidacion en formato pequeno termico.',
        content = '<div style="text-align:center; font-family:Courier New, monospace; font-size:10px;">
    <strong style="font-size:13px;">{{empresa_nombre}}</strong><br>
    <span>{{sucursal_nombre}}</span><br>
    <hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
    <strong>RECIBO LIQUIDACION</strong><br>
    <span>{{liquidacion_fecha}}</span>
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
    <tr><td><strong>Metodo:</strong></td><td style="text-align:right;">{{liquidacion_metodo}}</td></tr>
    <tr><td><strong>Ref:</strong></td><td style="text-align:right;">{{liquidacion_referencia}}</td></tr>
    </table>

    <hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
    <div style="text-align:center; font-family:Courier New, monospace; font-size:9px;">
    Prestamo liquidado en su totalidad.<br>
    Conserve este recibo.
    </div>',
        updated_at = NOW()
    WHERE deleted_at IS NULL
    AND name = 'Recibo Liquidacion - Pequeno';

    COMMIT;
