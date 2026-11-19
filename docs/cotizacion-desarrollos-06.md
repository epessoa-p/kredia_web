# Cotización de desarrollo — App móvil del Sistema de Préstamos (N.º 06)

> Documento editable. Ajusta los datos entre corchetes `[ ]`, las horas o la tarifa según necesites.

**Para:** [Nombre del cliente / Santa Ana]
**De:** [Tu nombre / Eric Pessoa Montaño]
**Fecha:** [dd/mm/aaaa]
**Cotización N°:** [006]
**Tarifa aplicada:** Bs 40,00 / hora

---

## Detalle de los desarrollos

Actualización de la **app móvil (Android)** para que funcione con las mismas reglas y datos que el sistema web.
Los ajustes del servidor (API) que necesita cada punto están incluidos en sus horas.

---

### 1. Cobros de préstamos iguales a la web
La app registraba los pagos con un formulario antiguo (interés / capital / mixto) que no marcaba los periodos de interés ni cobraba multas. Ahora cobra exactamente igual que la web.

Incluye:
- Botón **"Cobrar"** en el detalle del préstamo con las cuatro operaciones de la web: **pagar interés** (el periodo pendiente más antiguo, total o parcial), **pagar multa**, **amortizar capital** y **liquidar préstamo** (con confirmación; finaliza el préstamo y libera la prenda).
- Cada cobro registra el pago, el **movimiento de caja** y actualiza el préstamo igual que en la web; fecha de hoy y pago en efectivo.
- Mensajes claros cuando no se puede cobrar: sin caja abierta, sin caja asignada, préstamo finalizado o incumplido (solo lectura).
- Las opciones se muestran según los **permisos** del usuario.
- Protección contra **cobros duplicados** (doble toque o reintento).
- La lógica de cobro queda **unificada** para la web y la app (un solo lugar), con transacciones para que un cobro nunca quede a medias.
- Montos en **Bs** en el detalle del préstamo (antes mostraba "S/").
- Documentación de la API actualizada.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 6,0
- **Subtotal:** Bs 240,00

---

### 2. Intereses y multa en el detalle del préstamo
El detalle del préstamo en la app muestra la misma información de intereses y multa que la web.

Incluye:
- Sección **"Intereses"** con cada periodo: fechas, base de capital, tasa, monto, lo pagado y lo que falta, con su estado (**pagado**, **vencido**, **pendiente** o **eliminado**) y un resumen (ej. "3 pagados · 1 vencido").
- Se marca el periodo **próximo a cobrar** (el pendiente más antiguo).
- En los intereses eliminados: **quién** lo eliminó, **cuándo** y el **motivo**; en los restaurados, quién y cuándo.
- Tarjeta de **multa** con su etapa (**en mora**, **periodo de gracia**, **incumplido**), el monto (pendiente o pagada), la fecha en que se alcanzó el plazo tope, los días de gracia y la fecha en que termina la gracia.
- Al abrir el préstamo en la app se actualizan los intereses vencidos y la mora, igual que en la web.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

### 3. Eliminar y restaurar intereses
- En cada periodo de interés del detalle del préstamo, botón **"Eliminar periodo"** (solo intereses no pagados) y **"Restaurar"** en los eliminados, con confirmación.
- Respeta los mismos **permisos** que la web ("Eliminar interés" y "Restaurar interés") y las mismas reglas: no se elimina un interés pagado ni en préstamos finalizados o incumplidos; solo se restaura en préstamos activos o en mora y si no hay otro interés en ese periodo.
- Queda registrado **quién** eliminó o restauró y **cuándo**; al restaurar se vuelve a evaluar la mora.
- En la web, esas reglas ahora también se validan en el servidor (antes solo se ocultaba el botón).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

### 4. Registro de préstamos con las mismas reglas que la web
Se revisó el registro de préstamos desde la app contra la web y se igualaron las reglas.

Incluye:
- La app ya **no permite desembolsar más de lo que hay en la caja** (igual que la web), con un mensaje que indica el saldo disponible.
- El registro se guarda **completo o no se guarda**: préstamo, salida de caja, prenda en almacén, fotos y primer interés (antes, ante un error a mitad de camino podía quedar un préstamo sin su movimiento de caja).
- Protección contra **préstamos duplicados** si la conexión falla y se vuelve a enviar.
- Se guarda el **material** de las joyas (oro, plata…), que antes se perdía.
- Mensajes de error **en español** y más claros en la app (ej. cliente inactivo, saldo insuficiente).
- Documentación de la API actualizada.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

### 5. Categoría de los clientes
La clasificación de clientes de la web (ítem 1) también se ve en la app.

Incluye:
- En la lista de clientes, cada cliente muestra su **categoría** (Cliente fiel, Puntual, Regular, Riesgo, Nuevo) con su color y su **% de puntualidad**.
- **Filtro por categoría** con la cantidad de clientes en cada una (Todos, Puntuales, Cliente fiel, Puntual, Regular, Riesgo, Nuevo), combinable con la búsqueda.
- En la **ficha del cliente**, su categoría y un resumen; al tocarlo se abre el **historial de pagos**: puntualidad, préstamos, finalizados, monto prestado, intereses a tiempo y con atraso, préstamos con multa, en mora e incumplidos.
- Usa las mismas reglas que configura la empresa en la web (panel "Categorías").
- Montos en **Bs** en la ficha del cliente (antes mostraba "S/").

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

### 6. Etiquetas de clientes
Las etiquetas de clientes de la web (ítem 4) también se usan en la app.

Incluye:
- La **etiqueta** de cada cliente (ej. VIP, CLIENTE FIEL) se muestra con su **color** en la lista y en la ficha.
- En la **ficha del cliente**, selector rápido para **poner, cambiar o quitar** la etiqueta, eligiendo de la lista de la empresa.
- Al registrar un **nuevo cliente** desde la app se puede elegir su etiqueta.
- Mismo permiso que la web (**"Etiquetar clientes"**): sin el permiso la etiqueta se ve pero no se puede cambiar.
- Solo se aceptan etiquetas del catálogo de la empresa (se administran en la web, panel "Categorías").

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

### 7. Editar clientes
Hasta ahora la app solo permitía registrar clientes; ahora también se pueden editar, igual que en la web.

Incluye:
- Botón **editar** en la ficha del cliente (solo con el permiso de editar clientes).
- Se modifican los datos (nombre, documento, teléfono, correo, dirección, observaciones), la **ubicación** (mapa o link) y, con permiso, la **etiqueta**.
- **Foto**: cambiarla (galería o cámara) o quitarla.
- **Documentos**: ver los que ya tiene, **eliminarlos** (con confirmación) y **agregar** nuevos.
- Mismas validaciones que la web: no se repiten nombre, documento, teléfono ni correo dentro de la empresa; mensajes de error en español (también al registrar clientes).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,5
- **Subtotal:** Bs 100,00

---

### 8. Abrir y cerrar caja
Hasta ahora la app solo mostraba si había una caja abierta; ahora se puede aperturar y cerrar desde el celular, igual que en la web.

Incluye:
- **Aperturar caja**: monto de apertura y notas; el **gerente elige** cuál de sus cajas abrir (con el color de la sucursal), el resto abre la suya.
- **Cerrar caja**: muestra cuánto **deberías tener** (apertura + ingresos − egresos), se ingresa el **monto contado** (con botón "Usar esperado") o se **cuentan billetes y monedas**, y avisa si **cuadra, sobra o falta**; pide confirmación.
- Mensajes claros cuando no se puede: sin personal asociado, sin caja asignada, sin sucursal, caja ya abierta.
- Respeta el permiso de operar caja y evita aperturas o cierres duplicados.
- La lógica de apertura y cierre queda **unificada** para la web y la app.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 3,0
- **Subtotal:** Bs 120,00

---

### 9. Formulario de nuevo préstamo igual a la web
Revisión del formulario de préstamo de la app comparado con el de la web y corrección de las diferencias.

Incluye:
- La app muestra **todas las categorías activas** de la empresa (antes solo 4 fijas; no se podía prestar con COMPUTADORA, CELULAR, HELADERA, HERRAMIENTA ni ELECTRODOMÉSTICOS). Las categorías nuevas usan el formulario de artículos, como en la web.
- Las categorías **desactivadas** ya no aparecen ni se aceptan.
- El buscador de clientes muestra solo **clientes activos**.
- Muestra el **máximo disponible en caja** y avisa antes de enviar si el monto lo supera.
- **Vista previa**: interés del primer periodo y capital + primer interés.
- Montos en **Bs**.
- Tasa de interés y montos con las **mismas reglas que la web** (se quitaron límites que solo existían en la app).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

### 10. Registrar gastos de caja
El botón "Registrar gasto" de la web, ahora también en la app.

Incluye:
- Desde la caja abierta: **tipo de gasto** (servicio, pago a personal u otro gasto operativo), **monto** y notas.
- **Pago a personal**: se elige el personal activo de la empresa y el gasto queda vinculado a esa persona.
- Muestra el **efectivo disponible** y cómo quedará la caja; no permite gastar más de lo que hay.
- Respeta el permiso **"Registrar gastos de caja"** y evita registrar el mismo gasto dos veces.
- La lógica queda **unificada** para la web y la app.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

### 11. La sesión ya no se cierra por un corte de red
La app cerraba la sesión del usuario cuando fallaba la consulta de su perfil por cualquier motivo, incluso un corte momentáneo de internet al volver a abrir la app, obligándolo a ingresar de nuevo.

Incluye:
- La sesión solo se cierra cuando el servidor rechaza el acceso (sesión vencida, usuario o empresa desactivados).
- Ante un corte de red o un error del servidor, el usuario sigue dentro de la app con sus datos.
- En la caja, si no se pudo actualizar el estado tras abrir o cerrar, la app lo avisa.

- **Tipo:** Corrección (app móvil)
- **Horas:** 0,5
- **Subtotal:** Bs 20,00

---

### 12. Ventas
Hasta ahora la app solo listaba las ventas; ahora se pueden registrar y gestionar como en la web.

Incluye:
- **Lista de ventas** con búsqueda por cliente o producto, estado (completada / devuelta) y tipo de pago.
- **Nueva venta**: se elige un producto disponible en el almacén de la caja (con su origen: préstamo incumplido, compra o inventario), un cliente existente o uno nuevo, y el pago **al contado** o **a crédito** (recargo, cuota inicial y cuotas mensuales que la app calcula y muestra antes de registrar).
- **Detalle de la venta**: datos, plan de cuotas con botón **Cobrar** en cada cuota pendiente (y su estado: pendiente, vencida, pagada, anulada).
- **Liquidar** el saldo de una venta a crédito, mostrando capital, interés de cuotas vencidas y total.
- **Devolver** una venta (motivo y notas): el producto vuelve al almacén y se devuelve en caja lo cobrado. Con el mismo permiso que la web o con la autorización puntual que se da en la web.
- Todo pasa por la caja abierta y se protege contra registros duplicados.
- La lógica de ventas queda **unificada** para la web y la app. De paso: ya no se puede vender dos veces la misma unidad ni cobrar dos veces una cuota con un doble clic, no se cobran cuotas de ventas devueltas y los mensajes de error del formulario web de ventas salen en español.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 6,0
- **Subtotal:** Bs 240,00

---

### 13. Logo de Santa Ana y firma para distribuir la app
- Ícono de la app con el logo de Santa Ana (Android e iOS), y el logo en la pantalla de carga, el inicio de sesión y el menú.
- Configuración de la **firma de release** (keystore de Santa Ana) y guía para generar el APK firmado que apunta a producción.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

### 14. Compras
Hasta ahora la app solo listaba las compras; ahora se registran y gestionan como en la web.

Incluye:
- **Nueva compra**: categoría con los campos del producto que corresponden (vehículo, joya, garrafa o artículo),
  cliente existente o nuevo, monto pagado (con el saldo disponible de la caja), precio de venta y fotos.
  El producto entra al almacén de la sucursal de la caja y el monto sale de la caja.
- **Lista** con búsqueda y **detalle** de la compra (producto, cliente, caja, fotos).
- **Eliminar** una compra con el mismo permiso que la web: el dinero vuelve a la caja y el producto sale del almacén.
- La lógica de compras queda **unificada** para la web y la app. De paso: dos compras simultáneas ya no pueden
  gastar más de lo que hay en la caja, se guardan el modelo y el material del producto (antes se perdían),
  los mensajes de error salen en español y, tras eliminar la última compra o venta de una sucursal, la siguiente
  ya no falla al registrarse.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 4,0
- **Subtotal:** Bs 160,00

---

### 15. Precio de los productos del inventario
En el inventario de la app ahora se ve el precio de venta de cada producto y, con el mismo permiso que en la web
(editar almacenes), se puede cambiar desde el detalle del producto (muestra el costo y la ganancia).
De paso, el detalle del producto ya no falla si el stock tiene decimales y los mensajes de error salen en español.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

---

### 16. Mayúsculas automáticas en los formularios
Igual que en la web, los campos de texto de los formularios de la app (nombres, marcas, placas, detalles, notas…)
se escriben en MAYÚSCULAS mientras se tipea, aunque se cambie el teclado o se pegue texto en minúsculas. No se
convierten el correo, la contraseña, el teléfono, los enlaces, los montos ni los buscadores.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

---

### 17. Elegir empresa (super administrador y usuarios con varias empresas)
El super administrador no tiene una empresa fija y en la app fallaban, por ejemplo, "Nueva venta", "Nueva compra" y
las categorías del nuevo préstamo. Ahora la app le pide elegir la empresa al ingresar, muestra la empresa actual en el
menú y permite cambiarla (también a los usuarios que trabajan en más de una empresa), como el selector de la web.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

### 18. Menú y pestañas según los permisos
El menú lateral y las pestañas de la app mostraban módulos que el usuario no tenía permiso de ver (al entrar daban
error). Ahora, igual que el menú de la web, solo aparecen los módulos permitidos, y el ingreso de stock solo para quien
puede registrarlo. Se revisaron todos los botones de las pantallas nuevas.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

---

### 19. Documentación de la API al día
Se revisaron todas las rutas de la API contra los documentos para el desarrollo de la app y se completaron: rutas que
faltaban, dirección real de producción, cómo evitar operaciones duplicadas, el flujo recomendado (empresa, caja y
permisos) y una tabla con todos los endpoints y el permiso que exige cada uno. Se corrigieron partes que habían quedado
desactualizadas.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

---

### 20. Reportes con descarga en PDF y Excel
Los reportes de la web ahora también están en la app: resumen general, Préstamos, Pagos, Movimientos de caja, Cierres
de caja, Cartera de clientes y Almacenes, con filtros (fechas, estado, sucursal, caja…) y botón para descargar el
reporte en PDF o Excel y abrirlo o compartirlo por WhatsApp o correo desde el celular.

Incluye mejoras y correcciones en la web:
- La pantalla, la exportación y la app usan el mismo cálculo, así que los números siempre coinciden.
- El Excel ahora es un archivo .xlsx real (antes Excel mostraba una advertencia al abrirlo) y los montos se pueden sumar.
- Nueva exportación del reporte de Pagos.
- Corregido: las tablas exportadas mostraban "$" en lugar de Bs; los totales de la cartera de clientes sumaban solo
  los 20 clientes de la página; los préstamos finalizados aparecían con saldo pendiente; al exportar desde la lista de
  Préstamos se perdía el filtro de sucursal; las exportaciones de movimientos y cierres de caja no pedían permiso.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 8,0
- **Subtotal:** Bs 320,00

---

### 21. Catálogos (crear, editar, compartir y PDF con fotos)
Los catálogos ahora se manejan desde el celular: crear y editar (productos disponibles con buscador y categorías,
orden, nombre o precio propio por producto, sucursal, color, logo, contacto y vencimiento), compartir el enlace por
WhatsApp o copiarlo, pausar o activar el enlace, generar uno nuevo y eliminar.

Incluye el **PDF del catálogo con las fotos** de los productos (antes la web abría una página para imprimir y no
había PDF real): se descarga desde la web y desde la app, listo para enviar. Las fotos se reducen para que el archivo
sea liviano.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 6,0
- **Subtotal:** Bs 240,00

---

### 22. Pantalla de pagos renovada, con detalle y recibo en PDF
La lista de pagos se rehízo y quedó en español: cada pago muestra cliente, préstamo, tipo (interés, multa,
amortización o liquidación), la sucursal con su color, quién cobró y la fecha, con el total de lo filtrado.
Se agregan el buscador (cliente, código PR- o referencia), los filtros (fechas, sucursal, tipo y método de pago) y el
**detalle del pago** (desglose, préstamo, cliente, caja y comprobante adjunto) con el **recibo en PDF** en tamaño
ticket (80 mm) o carta, para imprimirlo o compartirlo. Los pagos del historial del préstamo también abren su detalle.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 6,0
- **Subtotal:** Bs 240,00

---

### 23. Color de la sucursal en el inventario
Los productos del inventario (y el stock por almacén del detalle) muestran la sucursal en una etiqueta con su color,
como en la web.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

---

### 24. Ver el detalle desde los reportes
Al tocar una fila de los reportes se abre su detalle: préstamos (ficha del préstamo), pagos (detalle con recibo),
movimientos de caja (datos del movimiento y acceso al préstamo, venta o compra que lo originó), cartera de clientes
(ficha del cliente) y **cierres de caja** (nuevo: resumen de la sesión —apertura, ingresos, egresos, esperado,
contado y descuadre— con todos sus movimientos).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 5,0
- **Subtotal:** Bs 200,00

---

### 25. Fotos del préstamo
El detalle del préstamo muestra las fotos de la prenda (antes no aparecían en la app). Se abren a pantalla completa,
con zoom y deslizando entre fotos.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

### 26. Filtros en movimientos e inventario y accesos rápidos
**Movimientos de caja**: lista rehecha (agrupada por día, con ingresos, egresos y utilidad de lo filtrado, en español)
con filtros por fechas, tipo, categoría, sucursal y caja, "Ver más" y el detalle de cada movimiento.
**Inventario**: filtros por sucursal, almacén, categoría y "solo con stock" (activo por defecto), y "Ver más" (antes
solo se veían los primeros 20 productos).
**Navegación**: la barra inferior pasa a ser Inicio, Préstamos, Clientes e Inventario; Movimientos de caja e
Inventario se agregan al menú lateral.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 4,5
- **Subtotal:** Bs 180,00

---

### 27. Valor del inventario
En la pestaña de inventario, una tarjeta con el valor de lo que se ve (respeta los filtros de sucursal, almacén y
categoría): unidades, valor a costo, valor a precio de venta y ganancia posible. Se separa lo que está **para la
venta** de lo que está **en garantía** (prendas de préstamos vigentes, que el cliente aún puede recuperar), para no
inflar el valor de venta; avisa si hay productos sin precio de venta.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

## Resumen

| # | Desarrollo | Tipo | Horas | Tarifa (Bs/h) | Subtotal (Bs) |
|---|-----------|------|:-----:|:-------------:|--------------:|
| 1 | Cobros de préstamos iguales a la web | Mejora | 6,0 | 40,00 | 240,00 |
| 2 | Intereses y multa en el detalle del préstamo | Mejora | 2,0 | 40,00 | 80,00 |
| 3 | Eliminar y restaurar intereses | Mejora | 1,5 | 40,00 | 60,00 |
| 4 | Registro de préstamos con las mismas reglas que la web | Mejora | 1,5 | 40,00 | 60,00 |
| 5 | Categoría de los clientes | Mejora | 2,0 | 40,00 | 80,00 |
| 6 | Etiquetas de clientes | Mejora | 2,0 | 40,00 | 80,00 |
| 7 | Editar clientes | Mejora | 2,5 | 40,00 | 100,00 |
| 8 | Abrir y cerrar caja | Mejora | 3,0 | 40,00 | 120,00 |
| 9 | Formulario de nuevo préstamo igual a la web | Mejora | 2,0 | 40,00 | 80,00 |
| 10 | Registrar gastos de caja | Mejora | 1,5 | 40,00 | 60,00 |
| 11 | La sesión ya no se cierra por un corte de red | Corrección | 0,5 | 40,00 | 20,00 |
| 12 | Ventas | Mejora | 6,0 | 40,00 | 240,00 |
| 13 | Logo de Santa Ana y firma para distribuir la app | Mejora | 1,5 | 40,00 | 60,00 |
| 14 | Compras | Mejora | 4,0 | 40,00 | 160,00 |
| 15 | Precio de los productos del inventario | Mejora | 1,0 | 40,00 | 40,00 |
| 16 | Mayúsculas automáticas en los formularios | Mejora | 1,0 | 40,00 | 40,00 |
| 17 | Elegir y cambiar de empresa | Mejora | 1,5 | 40,00 | 60,00 |
| 18 | Menú y pestañas según los permisos | Mejora | 1,0 | 40,00 | 40,00 |
| 19 | Documentación de la API al día | Mejora | 1,0 | 40,00 | 40,00 |
| 20 | Reportes con descarga en PDF y Excel | Mejora | 8,0 | 40,00 | 320,00 |
| 21 | Catálogos (crear, editar, compartir y PDF con fotos) | Mejora | 6,0 | 40,00 | 240,00 |
| 22 | Pagos renovados con detalle, filtros y recibo en PDF | Mejora | 6,0 | 40,00 | 240,00 |
| 23 | Color de la sucursal en el inventario | Mejora | 1,0 | 40,00 | 40,00 |
| 24 | Ver el detalle desde los reportes (incluye cierre de caja) | Mejora | 5,0 | 40,00 | 200,00 |
| 25 | Fotos del préstamo | Mejora | 1,5 | 40,00 | 60,00 |
| 26 | Filtros en movimientos e inventario y accesos rápidos | Mejora | 4,5 | 40,00 | 180,00 |
| 27 | Valor del inventario (costo, venta y ganancia) | Mejora | 2,0 | 40,00 | 80,00 |
| | | | **75,5** | | **3.020,00** |

**TOTAL: Bs 3.020,00**

---

## Notas
- Los montos están expresados en bolivianos (Bs).
- Las horas corresponden a análisis, desarrollo, pruebas y puesta en funcionamiento.
- Estos trabajos son **funcionalidades nuevas** solicitadas, no correcciones de errores.
- Validez de la cotización: [15 días] a partir de la fecha.
- Forma de pago: [a convenir].
