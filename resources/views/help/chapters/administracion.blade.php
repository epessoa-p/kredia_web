<p class="cap-intro">Configuración del sistema, para administradores y gerentes. Es lo que se prepara una vez al empezar y se ajusta cuando cambia algo (nueva sucursal, nuevo empleado, nueva tasa).</p>

<h3>Orden recomendado para configurar</h3>
<ol class="pasos">
    <li><strong>Empresa</strong>: datos, logo y moneda.</li>
    <li><strong>Almacenes</strong> y luego <strong>Sucursales</strong> (cada sucursal necesita un almacén principal).</li>
    <li><strong>Cargos</strong> con su rol y permisos.</li>
    <li><strong>Personal</strong>: cada empleado con su cargo y su acceso al sistema.</li>
    <li><strong>Cajas</strong>: una por sucursal o por persona, con su personal asignado.</li>
    <li><strong>Categorías crédito</strong> con sus reglas de interés y multa.</li>
    <li><strong>Plantillas</strong> de documentos (contratos, recibos), si se usan.</li>
</ol>

<h3>Empresa</h3>
<p>En <span class="menu">Empresas</span> (solo el super administrador) se editan nombre, RUC/NIT, email, teléfono, dirección, descripción y <strong>logo</strong>. Opciones importantes:</p>
<ul>
    <li><strong>Símbolo de la moneda</strong>: por ejemplo <em>Bs</em>. Se usa en todas las pantallas, recibos y la app.</li>
    <li><strong>Cobrar el siguiente interés al día siguiente del aniversario</strong>: si un préstamo se crea el 15, el siguiente interés recién se genera el 16; el día 15 el cliente aún debe un solo interés.</li>
</ul>

<h3>Sucursales y almacenes</h3>
<ul>
    <li><span class="menu">Almacenes</span>: nombre, código, ubicación y descripción. Crea primero el almacén.</li>
    <li><span class="menu">Sucursales</span>: nombre, código, <strong>almacén principal</strong>, teléfono, correo, encargado, dirección y un <strong>color</strong> para distinguirla en las listas.</li>
</ul>

<h3>Cargos, roles y permisos</h3>
<p>Los permisos se asignan por <strong>cargo</strong>: cada cargo tiene su propio <strong>rol</strong> (con el mismo nombre), que define qué puede ver y hacer el personal con ese cargo.</p>
<ol class="pasos">
    <li><span class="menu">Cargos</span> › nuevo o editar.</li>
    <li>Escribe el <strong>nombre del cargo</strong> (por ejemplo "Encargado de tienda"). Al crearlo, el sistema le crea su rol automáticamente.</li>
    <li>Marca los <strong>permisos del cargo</strong>, agrupados por módulo (Clientes, Préstamos, Pagos, Ventas…). La casilla del título marca o desmarca todo el módulo y el buscador encuentra un permiso por su nombre.</li>
    <li>Guarda con el botón de la barra inferior, que muestra cuántos permisos están marcados.</li>
</ol>
<div class="aviso"><strong>Atención:</strong> los cambios de permisos aplican a <strong>todas</strong> las personas con ese cargo. Si un cargo antiguo comparte su rol con otro cargo, la pantalla de edición lo avisa. El cargo <strong>Gerente</strong> trabaja con todas las sucursales; el resto queda fijo a su sucursal.</div>

<h3>Personal (empleados y su acceso)</h3>
<ol class="pasos">
    <li><span class="menu">Personal</span> › nuevo.</li>
    <li>Elige el <strong>cargo</strong> y completa nombre, documento, teléfono, fecha de ingreso, dirección y notas.</li>
    <li>En <strong>Email de acceso</strong> y <strong>Contraseña</strong> se crea su usuario para ingresar al sistema y a la app.</li>
    <li>Guarda. Para cambiarle la contraseña, edítalo y escribe una nueva (déjala vacía para no cambiarla).</li>
</ol>
<div class="nota"><strong>Nota:</strong> para dar de baja a un empleado, desmarca <em>Activo</em>; así no puede ingresar, pero su historial se conserva.</div>

<h3>Usuarios</h3>
<p>En <span class="menu">Usuarios</span> se ven todas las cuentas con acceso, sus datos y su rol en cada empresa. Normalmente los usuarios se crean desde <span class="menu">Personal</span>.</p>

<h3>Cajas</h3>
<ol class="pasos">
    <li><span class="menu">Cajas</span> › nueva.</li>
    <li>Elige la <strong>sucursal</strong>, escribe el <strong>nombre de la caja</strong> y elige el <strong>personal asignado</strong> (quién la abre y cierra).</li>
    <li>Deja marcada <em>Caja activa</em> y guarda.</li>
</ol>
<div class="aviso"><strong>Importante:</strong> una persona sin caja asignada no puede abrir caja y, por lo tanto, no puede registrar préstamos, cobros, ventas ni compras.</div>

<h3>Categorías de crédito y reglas</h3>
<p>Cada categoría (Vehículo, Joyas, Garrafas, Celulares…) define cómo se presta y cómo se penaliza:</p>
<ul>
    <li><strong>Datos:</strong> nombre, color, <strong>ícono</strong>, monto probable mínimo y máximo, descripción y si está activa (las inactivas no aparecen al prestar ni comprar).</li>
    <li><strong>Ícono:</strong> se elige de la lista (celular, computadora, joya, garrafa, herramienta…) y se ve en la vista previa con el color de la categoría. Es el que aparece en las tarjetas de <span class="menu">Inventario</span> › <span class="boton">Ingresar producto</span>. Al crear una categoría se sugiere uno según el nombre; puedes cambiarlo.</li>
    <li><strong>Multa:</strong> <em>días de gracia</em> (tiempo en mora antes de pasar a incumplido), <em>tasa de multa %</em> o <em>monto fijo de multa</em>.</li>
    <li><strong>Regla de crédito:</strong> cada categoría tiene <strong>una sola regla</strong>, con <em>interés %</em> y <em>plazo tope</em> en meses (obligatorios; a partir del plazo el préstamo entra en mora) y <em>periodicidad</em> (mensual, trimestral, semestral o anual). Su <strong>nombre se arma solo</strong> con esos datos (por ejemplo <em>10% MENSUAL · 3 MESES</em>) y usa el <strong>monto mínimo y máximo de la categoría</strong> (no se escriben dos veces).</li>
</ul>
<p>Ver el capítulo <em>Mora, multa e incumplimiento</em> para entender cómo se aplican.</p>

<h3>Plantillas de documentos</h3>
<p>En <span class="menu">Plantillas</span> se escriben los textos de contratos, boletas, recibos, amortizaciones y liquidaciones:</p>
<ol class="pasos">
    <li>Nueva plantilla: nombre, <strong>tipo</strong> (contrato, boleta, recibo, amortización, liquidación u otro), tamaño de papel y descripción.</li>
    <li>Escribe el contenido. En <strong>Variables disponibles</strong> haz clic en una variable (nombre del cliente, monto, fecha…) para insertarla; al imprimir se reemplaza con los datos reales.</li>
    <li>Marca <em>Plantilla activa</em> y guarda. Puedes ver la plantilla en PDF para revisarla.</li>
</ol>
