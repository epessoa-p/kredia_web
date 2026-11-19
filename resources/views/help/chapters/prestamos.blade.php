<p class="cap-intro">Un préstamo entrega dinero al cliente a cambio de una prenda (vehículo, joya, garrafa u otro artículo) que queda en custodia. El cliente paga intereses periódicos y, al final, devuelve el capital para recuperar su prenda.</p>

<h3>Antes de empezar</h3>
<ul>
    <li>Ten la <strong>caja abierta</strong>: el dinero del préstamo sale de la caja y no puede superar su saldo.</li>
    <li>Las <strong>categorías</strong> (Vehículo, Joyas, Garrafas, Celulares, Computadoras, etc.) y sus <strong>reglas de interés</strong> las configura el administrador en <span class="menu">Categorías crédito</span>.</li>
</ul>

<h3>Registrar un préstamo nuevo</h3>
<ol class="pasos">
    <li>Menú <span class="menu">Préstamos</span> › <span class="boton">Nuevo préstamo</span>.</li>
    <li><strong>Cliente:</strong> búscalo por nombre o documento y selecciónalo. Si es nuevo, completa sus datos ahí mismo (nombre, documento, teléfono, foto).</li>
    <li><strong>Datos del préstamo:</strong>
        <ul>
            <li><strong>Sucursal:</strong> la de tu caja (el Gerente puede elegir).</li>
            <li><strong>Categoría:</strong> el tipo de prenda. Al elegirla se aplican su tasa de interés, su periodicidad y su plazo.</li>
        </ul>
    </li>
    <li><strong>Datos del producto (prenda):</strong> el formulario cambia según la categoría:
        <ul>
            <li><strong>Vehículo:</strong> nombre, marca, modelo (año), placa, chasis y detalles.</li>
            <li><strong>Joyas:</strong> tipo de joya (collar, anillo…), kilataje, tipo de material y detalles.</li>
            <li><strong>Garrafas:</strong> estado, capacidad y observaciones.</li>
            <li><strong>Otros artículos:</strong> nombre, marca, modelo, serial y detalles.</li>
        </ul>
        Puedes poner un <strong>precio referencial</strong> y <strong>adjuntar fotos</strong> del producto o documentos.
    </li>
    <li><strong>Monto</strong> a prestar y <strong>tasa de interés</strong> (%). Revisa la vista previa: <em>Interés del primer periodo</em>.</li>
    <li><strong>Fecha de inicio</strong> del préstamo (por defecto hoy).</li>
    <li>Presiona <span class="boton">Registrar préstamo</span>. Se genera el código <strong>PR-00001</strong> (correlativo por sucursal), el dinero sale de la caja y la prenda entra al almacén.</li>
</ol>
<div class="nota"><strong>Cuándo se cobra el interés:</strong> el primer interés vence al cumplirse el periodo (por ejemplo, un mes después de la fecha de inicio si la categoría es mensual). Si la empresa tiene activa la opción <em>"Cobrar el siguiente interés al día siguiente del aniversario"</em>, el siguiente interés se genera recién el día después.</div>

<h3>Imprimir el contrato y el comprobante</h3>
<p>En el detalle del préstamo, sección <strong>Contrato del préstamo</strong>:</p>
<ul>
    <li><span class="boton">PDF</span>: contrato en hoja normal.</li>
    <li><span class="boton">Documento Privado</span>: documento privado con los datos del cliente y la prenda.</li>
    <li><span class="boton">Térmica rápida</span>: comprobante corto para impresora térmica de 80 mm.</li>
</ul>
<p>Puedes ajustar el texto del contrato, <span class="boton">Adjuntar documentos</span> (por ejemplo el contrato firmado escaneado) y presionar <span class="boton">Guardar contrato</span>.</p>

<h3>Buscar préstamos</h3>
<p>En <span class="menu">Préstamos</span> puedes filtrar por <strong>búsqueda rápida</strong> (código, cliente), <strong>estado</strong>, <strong>categoría</strong>, <strong>personal</strong> que lo registró, si el <strong>producto</strong> fue vendido o no, por <strong>cantidad de intereses sin pagar</strong> (por ejemplo, marca 2 y 3 para ver los que deben dos o tres intereses) y por <strong>fechas</strong>. Los Gerentes además eligen la <strong>sucursal</strong>.</p>

<h3>Estados de un préstamo</h3>
<table class="tabla">
    <tr><th>Estado</th><th>Qué significa</th></tr>
    <tr><td><strong>Activo</strong></td><td>Vigente; se cobran intereses normalmente.</td></tr>
    <tr><td><strong>En mora</strong></td><td>Acumuló el máximo de intereses vencidos sin pagar: se generó una multa y la prenda pasó a retención. Todavía se puede cobrar.</td></tr>
    <tr><td><strong>Incumplido</strong></td><td>Venció el periodo de gracia sin regularizar: la prenda queda disponible para la venta. El préstamo queda de solo lectura.</td></tr>
    <tr><td><strong>Finalizado</strong></td><td>El cliente liquidó: se devolvió la prenda. Solo lectura y reimpresión.</td></tr>
</table>
<p>Más detalle en el capítulo <em>Mora, multa e incumplimiento</em>.</p>

<h3>Detalle del préstamo</h3>
<p>Al abrir un préstamo verás arriba el <strong>capital inicial</strong>, el <strong>capital vigente</strong>, el <strong>total pagado</strong> y el monto <strong>para liquidar</strong>. Luego: cliente, condiciones (tasa, periodicidad, plazo, interés por periodo), la prenda y su estado, el historial de pagos y las secciones de intereses, amortización, liquidación y multa (ver <em>Cobros de préstamos</em>).</p>

<h3>Editar un préstamo</h3>
<p>Botón <span class="boton">Editar</span> en el detalle. Solo se puede editar si el préstamo está abierto y <strong>todavía no tiene pagos</strong>, intereses pagados ni amortizaciones. Si el botón aparece con candado, pasa el mouse encima para ver el motivo. Al editar el monto o la tasa, los intereses pendientes se recalculan.</p>

<h3>Eliminar un préstamo</h3>
<ol class="pasos">
    <li>En el detalle presiona <span class="boton">Eliminar</span> (requiere permiso).</li>
    <li>Escribe el <strong>motivo de eliminación</strong> (obligatorio) y confirma.</li>
</ol>
<div class="aviso"><strong>Atención:</strong> no se puede eliminar si ya hay intereses pagados o cobros en caja. Al eliminar, se anulan los intereses pendientes, el dinero vuelve a la caja y la prenda sale del inventario. El préstamo eliminado queda visible como solo lectura, con quién y por qué lo eliminó.</div>
