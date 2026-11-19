<p class="cap-intro">El inventario muestra qué productos hay en cada almacén: prendas de préstamos, productos comprados y otros ingresos. Se actualiza solo con cada préstamo, liquidación, compra y venta.</p>
<div class="nota"><strong>Un producto por artículo:</strong> cada préstamo, compra o ingreso de stock crea su <strong>propio producto</strong> con cantidad 1, aunque sea igual a otro (por ejemplo dos garrafas o dos celulares del mismo modelo), porque cada artículo usado tiene su propio valor. Por eso en el inventario cada producto aparece con cantidad 1 y su propio costo y código. Si quedaron productos compartidos de antes, el <strong>super administrador</strong> ve en <span class="menu">Inventario</span> y en <span class="menu">Productos</span> el botón <span class="boton">Separar productos</span>, que crea un producto para cada préstamo o compra (también los ya liquidados o vendidos) sin cambiar el stock.</div>

<h3>Consultar el inventario</h3>
<ol class="pasos">
    <li>Menú <span class="menu">Inventario</span>.</li>
    <li>Arriba verás el <strong>valor a costo</strong>, el <strong>valor a precio de venta</strong> y el <strong>margen potencial</strong> (lo que se ganaría vendiendo todo).</li>
    <li>Filtra por sucursal, almacén, categoría, estado, disponibilidad u origen (de préstamo, de compra, otros), o busca por nombre o por <strong>código</strong> (por ejemplo <em>PD-00029</em> o <em>pd29</em>).</li>
    <li>En el filtro <strong>Estado</strong>, cada botón muestra cuántas unidades hay (disponibles, en préstamo y en retención) con los demás filtros aplicados, y el botón <strong>Total</strong> muestra la cantidad total de lo que estás viendo (todas las páginas). Clic en Total para ver todos los estados.</li>
    <li>El <strong>super administrador</strong> tiene además el filtro <strong>Cantidad</strong> (más de 1, 3, 5 o 10 unidades del producto en el almacén), útil para encontrar productos que quedaron agrupados.</li>
    <li>Haz clic en un producto para ver su detalle: información, garantías activas (prendas de préstamos) y el <strong>historial de movimientos</strong> (entradas y salidas, con fotos).</li>
</ol>

<h3>Cambiar el precio de venta de un producto</h3>
<ol class="pasos">
    <li>En <span class="menu">Inventario</span>, en la columna <strong>Precio</strong>, escribe el nuevo precio.</li>
    <li>Al salir del campo se guarda solo: el campo se pone verde si se guardó y rojo si hubo un error (en ese caso vuelve al precio anterior).</li>
</ol>
<div class="nota"><strong>Nota:</strong> el precio solo se puede editar con permiso de edición de almacenes; si no lo tienes, verás el precio sin poder cambiarlo. También se puede cambiar desde la app móvil.</div>

<h3>Ingresar producto (artículos que ya tienes)</h3>
<p>Para registrar un artículo que ya está físicamente en la tienda y no viene de un préstamo ni de una compra. Como cada producto es <strong>único</strong> (cantidad 1), crear un producto es lo mismo que ingresarlo al inventario: el botón está en <span class="menu">Inventario</span> y en <span class="menu">Productos</span> y abre el mismo formulario.</p>
<ol class="pasos">
    <li><span class="menu">Inventario</span> (o <span class="menu">Productos</span>) › <span class="boton">Ingresar producto</span>.</li>
    <li><strong>Sucursal:</strong> elige dónde entra. No hace falta elegir almacén: el producto queda en el almacén de esa sucursal. El personal que no es gerente solo puede ingresar en su sucursal (aparece fija).</li>
    <li><strong>Categoría:</strong> toca la tarjeta (vehículo, joya, garrafa, celular, computadora…). Según la categoría aparecen sus datos: vehículo (marca, año, placa, chasis), joya (tipo, kilataje, material), garrafa (el nombre se completa solo) y el resto como artículo (marca, modelo, número de serie o IMEI).</li>
    <li><strong>Datos del producto:</strong> nombre, <strong>código</strong> (viene sugerido el siguiente PD-; se puede cambiar, pero no repetir en la sucursal) y descripción.</li>
    <li><strong>Valores:</strong> <strong>costo</strong> (lo que se pagó; obligatorio, 0 si no costó nada) y <strong>precio de venta</strong> (opcional). Se muestra la ganancia estimada.</li>
    <li>Opcional: <strong>fotos</strong> (subir archivos o usar la cámara) y, en <em>Más detalles</em>, la fecha, una referencia y notas.</li>
    <li>Revisa el <strong>resumen</strong> de la derecha y presiona <span class="boton">Ingresar producto</span>. Si tienes varios artículos iguales, ingrésalos uno por uno: cada uno tiene su propio costo y código.</li>
</ol>

<h3>Regularizar inventario</h3>
<p>Si un producto muestra <em>Requiere regularización</em> (por ejemplo, prendas de préstamos que fueron eliminados y no salieron del stock), en su detalle presiona <span class="boton">Regularizar</span>: se registra la salida de esas prendas para que el stock sea el real.</p>
<p>Arriba a la derecha del inventario aparece el aviso <strong>"N por regularizar"</strong>: haz clic en él para ver <strong>solo esos productos</strong> (otro clic quita el filtro).</p>

<h3>Productos</h3>
<p>En <span class="menu">Productos</span> está el listado general de productos de la empresa (código, nombre, SKU, categoría, modelo, material, costo y precio). Los productos se crean automáticamente con los préstamos, compras e ingresos; el botón <span class="boton">Ingresar producto</span> abre el mismo formulario del inventario (ver arriba). Aquí se pueden corregir sus datos o desactivarlos.</p>
<div class="nota"><strong>Código del producto</strong> según de dónde viene:
    <ul class="mb-0">
        <li><strong>De un préstamo:</strong> el código del préstamo, por ejemplo <strong>PR-00123</strong>. Lo conserva al pasar a la venta, así se ubica fácilmente de qué préstamo salió.</li>
        <li><strong>De una compra:</strong> el código de la compra, por ejemplo <strong>CO-00007</strong>.</li>
        <li><strong>Producto ingresado a mano</strong> (<span class="boton">Ingresar producto</span>): el formulario sugiere el siguiente correlativo <strong>PD-</strong> (por ejemplo PD-00058); se puede cambiar.</li>
    </ul>
    El código <strong>no se repite dentro de la misma sucursal</strong> (los PR- y CO- son correlativos por sucursal, así que el mismo PR- puede existir en otra sucursal: en las listas se ve junto a la sucursal). Se puede corregir en <span class="menu">Productos</span> › editar. Aparece en el inventario, en el detalle del préstamo, de la compra y de la venta, y al elegir el producto en una venta; se puede buscar escribiéndolo completo o abreviado (<em>pr123</em>, <em>PD-58</em>).</div>

<h3>Almacenes</h3>
<p>En <span class="menu">Almacenes</span> se administran los almacenes (nombre, código, ubicación). Cada sucursal tiene un <strong>almacén principal</strong> donde entran sus prendas, compras y productos. Desde el detalle de un almacén también se pueden registrar movimientos manuales (entradas o salidas) con su cantidad, costo, fecha y referencia.</p>
