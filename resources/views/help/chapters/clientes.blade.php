<p class="cap-intro">El cliente se registra una sola vez y se reutiliza en préstamos, ventas y compras. El sistema lo clasifica automáticamente según cómo paga.</p>

<h3>Registrar un cliente</h3>
<ol class="pasos">
    <li>Menú <span class="menu">Clientes</span> › <span class="boton">Nuevo cliente</span>.</li>
    <li>Completa el <strong>nombre completo</strong> (obligatorio), documento (CI), teléfono, correo y dirección.</li>
    <li>Opcional: sube la <strong>foto del cliente</strong> y los <strong>documentos adjuntos</strong> (foto del CI, facturas, contratos; JPG, PNG o PDF).</li>
    <li>Opcional: en <strong>Ubicación del cliente</strong> pega el enlace de Google Maps o marca el punto en el mapa.</li>
    <li>Deja marcado <strong>Cliente activo</strong> y presiona <span class="boton">Guardar cliente</span>.</li>
</ol>
<div class="consejo"><strong>Consejo:</strong> también puedes registrar un cliente nuevo directamente al crear un préstamo, una venta o una compra, sin salir del formulario.</div>
<div class="nota"><strong>Clientes inactivos:</strong> un cliente desmarcado como activo no aparece en los buscadores de préstamos, ventas ni compras.</div>

<h3>Buscar y filtrar clientes</h3>
<p>En <span class="menu">Clientes</span> puedes:</p>
<ul>
    <li><strong>Buscar</strong> por nombre, documento o teléfono.</li>
    <li>Filtrar por <strong>categoría</strong> (Fiel, Puntual, Regular, Riesgo, Nuevo) con los botones de arriba, que muestran cuántos clientes hay en cada una.</li>
    <li>Filtrar por <strong>etiqueta</strong>, por <strong>monto mínimo prestado</strong> o <strong>cantidad mínima de préstamos</strong>.</li>
    <li><strong>Ordenar</strong> por más recientes, más prestado, más préstamos o mejor puntualidad.</li>
</ul>

<h3>Ficha del cliente</h3>
<p>Haz clic en un cliente para ver su ficha: datos, ubicación, documentos, su <strong>categoría</strong> con el historial de pagos y pestañas con sus <strong>préstamos</strong>, <strong>compras</strong> y <strong>ventas</strong>, con totales pagados y pendientes.</p>

<h3>Editar un cliente</h3>
<ol class="pasos">
    <li>Abre la ficha del cliente y presiona <span class="boton">Editar</span>.</li>
    <li>Cambia los datos, la foto o los documentos (puedes agregar o eliminar archivos).</li>
    <li>Guarda los cambios.</li>
</ol>

<h3>Categorías automáticas de clientes</h3>
<p>El sistema califica a cada cliente según su historial de pagos de intereses:</p>
<table class="tabla">
    <tr><th>Categoría</th><th>Significa</th></tr>
    <tr><td><strong>Fiel</strong></td><td>Es puntual y además tiene varios préstamos o ha prestado un monto importante.</td></tr>
    <tr><td><strong>Puntual</strong></td><td>Paga sus intereses a tiempo (dentro de los días de tolerancia) y no tuvo multas.</td></tr>
    <tr><td><strong>Regular</strong></td><td>Tuvo atrasos o multas, pero hoy está al día.</td></tr>
    <tr><td><strong>Riesgo</strong></td><td>Tiene un préstamo incumplido o en mora.</td></tr>
    <tr><td><strong>Nuevo</strong></td><td>Todavía no tiene historial de pagos.</td></tr>
</table>
<p>La categoría se actualiza sola; no hay que hacer nada. Sirve para decidir a quién prestar y en qué condiciones.</p>

<h3>Configurar las categorías (administradores)</h3>
<ol class="pasos">
    <li><span class="menu">Clientes</span> › <span class="boton">Configurar categorías</span>.</li>
    <li>Ajusta las reglas: <strong>días de tolerancia</strong> para considerar un pago a tiempo, <strong>atrasos permitidos</strong>, si una <strong>multa</strong> quita la puntualidad, los <strong>préstamos mínimos</strong> y el <strong>monto mínimo prestado</strong> para ser Fiel (y si debe cumplir una o ambas condiciones), y si un préstamo en mora pone al cliente en Riesgo.</li>
    <li>La <strong>vista previa</strong> muestra cuántos clientes quedarían en cada categoría antes de guardar.</li>
    <li>Presiona <span class="boton">Guardar</span>. Con <em>Valores por defecto</em> vuelves a la configuración original.</li>
</ol>

<h3>Etiquetas de clientes</h3>
<p>Además de la categoría automática, puedes poner una <strong>etiqueta manual</strong> a un cliente (por ejemplo <em>CLIENTE FIEL</em> o <em>PREFERENCIAL</em>), con su color.</p>
<ul>
    <li><strong>Crear etiquetas:</strong> en <span class="menu">Clientes › Configurar categorías</span>, sección <em>Etiquetas de clientes</em>: escribe el nombre, elige el color y presiona <span class="boton">Agregar</span>.</li>
    <li><strong>Poner o quitar una etiqueta:</strong> en la ficha del cliente, elige la etiqueta en el selector junto al nombre (o "Sin etiqueta"). También al editar el cliente.</li>
</ul>
<div class="nota"><strong>Nota:</strong> para poner o quitar etiquetas se necesita el permiso <em>Etiquetar clientes</em>.</div>
