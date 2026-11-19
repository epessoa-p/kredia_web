<p class="cap-intro">Qué pasa cuando un cliente deja de pagar sus intereses, paso a paso. El sistema lo controla solo; aquí se explica para que puedas informar al cliente.</p>

<h3>La cronología de un préstamo</h3>
<table class="tabla">
    <tr><th>Etapa</th><th>Qué ocurre</th><th>Prenda</th></tr>
    <tr><td><strong>1. Préstamo activo</strong></td><td>Se generan los intereses de cada periodo y el cliente los paga.</td><td>En préstamo (en custodia)</td></tr>
    <tr><td><strong>2. Plazo tope</strong></td><td>El cliente acumula tantos intereses vencidos sin pagar como el <em>plazo tope</em> de la categoría (por ejemplo, 3 meses en una regla mensual = 3 intereses).</td><td>—</td></tr>
    <tr><td><strong>3. En mora</strong></td><td>Se genera la <strong>multa</strong> y empieza a correr el <strong>periodo de gracia</strong> (días de gracia de la categoría).</td><td>En retención</td></tr>
    <tr><td><strong>4. Incumplido</strong></td><td>Terminó la gracia sin que el cliente se ponga al día. El préstamo queda de solo lectura.</td><td>Disponible para la venta</td></tr>
</table>
<p>En el detalle del préstamo, pestaña <span class="boton">Multa</span>, la <strong>Cronología del préstamo</strong> muestra estas fechas para cada préstamo: vencimiento del plazo, entrada en mora y fin del periodo de gracia.</p>

<h3>Cómo se calcula la multa</h3>
<p>Cada categoría define la multa en <span class="menu">Categorías crédito</span>:</p>
<ul>
    <li><strong>Monto fijo de multa:</strong> si tiene un valor, la multa es ese monto.</li>
    <li>Si no, se usa la <strong>tasa de multa %</strong> sobre el capital vigente más los intereses impagos.</li>
</ul>
<p>La pestaña <span class="boton">Multa</span> muestra el <em>Cálculo de multa</em>: la base, la multa aplicada y el total para liquidar con multa.</p>

<h3>Cómo sale el cliente de la mora</h3>
<ul>
    <li><strong>Pagando los intereses vencidos</strong> hasta quedar por debajo del plazo tope: el préstamo vuelve a <em>Activo</em> y la prenda vuelve a custodia normal.</li>
    <li><strong>Liquidando</strong> el préstamo: paga capital, intereses y multa y recupera la prenda.</li>
    <li>Pagar solo la multa <strong>no</strong> lo saca de la mora; solo deja de cobrarse la multa en la liquidación.</li>
</ul>

<h3>Préstamo incumplido</h3>
<p>Cuando el préstamo pasa a <strong>Incumplido</strong>:</p>
<ul>
    <li>Ya no se registran pagos, amortizaciones ni liquidaciones (solo consulta e impresión).</li>
    <li>La prenda queda <strong>disponible para la venta</strong>: aparece al registrar una venta (<span class="menu">Ventas</span> › <span class="boton">Nueva Venta</span>), en <span class="boton">Venta rápida</span> y se puede agregar a los <span class="menu">Catálogos</span>.</li>
    <li>El cliente pasa a la categoría <strong>Riesgo</strong>.</li>
</ul>
<div class="aviso"><strong>Atención:</strong> el cambio de estado es automático según las fechas. Avisa al cliente antes de que termine el periodo de gracia; la fecha exacta está en la cronología del préstamo.</div>

<h3>Ver los préstamos en mora</h3>
<ul>
    <li>En el <span class="menu">Dashboard</span> del personal: <em>Préstamos en mora</em> y <em>cobros vencidos</em>.</li>
    <li>En <span class="menu">Préstamos</span>, filtra por estado <em>En mora</em> o <em>Incumplido</em>, o por cantidad de intereses sin pagar.</li>
    <li>En <span class="menu">Reportes › Almacenes</span>: prendas retenidas, en mora y disponibles para venta.</li>
</ul>
