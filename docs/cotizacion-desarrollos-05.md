# Cotización de desarrollo — Sistema de Préstamos, sistema web (N.º 05)

> Documento editable. Ajusta los datos entre corchetes `[ ]`, las horas o la tarifa según necesites.

**Para:** [Nombre del cliente / Santa Ana]
**De:** [Tu nombre / Eric Pessoa Montaño]
**Fecha:** [dd/mm/aaaa]
**Cotización N°:** [005]
**Tarifa aplicada:** Bs 40,00 / hora

---

## Detalle de los desarrollos

> Los desarrollos de la **app móvil** están en la cotización aparte N.º 06 y el código de productos y los filtros del inventario en la N.º 07.

### 1. Clasificación y filtros de clientes (puntuales, fieles y los que más se prestan)
En la lista de clientes se puede encontrar a los clientes que más se prestan y que pagan a tiempo, para ofrecerles otro tipo de préstamo, y clasificarlos.

Incluye:
- **Categoría automática** de cada cliente según su historial, siempre actualizada: **Cliente fiel**, **Puntual**, **Regular**, **Riesgo** y **Nuevo**.
  - *Puntual*: pagó sus intereses a tiempo (con tolerancia de días configurable), sin multas ni préstamos incumplidos.
  - *Cliente fiel*: puntual y con varios préstamos o un monto prestado alto (umbrales configurables).
  - *Riesgo*: tiene hoy un préstamo en mora o incumplido.
- **Filtros** en la lista: por categoría (con la cantidad de clientes en cada una), **prestado mínimo**, **cantidad mínima de préstamos** y **etiqueta**; ordenar por **más prestado**, **más préstamos** o **mejor puntualidad**.
- Nuevas columnas en la lista: **categoría** y **puntualidad** (% de intereses pagados a tiempo).
- **Etiqueta manual** del cliente (ej. "CLIENTE FIEL", "VIP") que asigna el personal, con sugerencias, visible destacada y filtrable.
- En la **ficha del cliente**: su categoría y un resumen de su **historial de pagos** (puntualidad, préstamos, finalizados, monto prestado, intereses a tiempo y con atraso, préstamos en mora).
- Cálculo eficiente: la lista responde rápido aunque crezca la cantidad de clientes.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 4,0
- **Subtotal:** Bs 160,00

---

### 2. Panel de configuración de las categorías de clientes
Pantalla para que la empresa ajuste por sí misma las reglas de cada categoría, sin tocar el sistema.

Incluye:
- **Cliente fiel**: préstamos mínimos, **monto mínimo prestado** (ej. de Bs 2.000 a Bs 3.000), si debe cumplir **una o ambas** condiciones y un **% mínimo de puntualidad** opcional.
- **Puntual**: **días de tolerancia** de pago, **cantidad de atrasos permitidos** y si una multa le quita la puntualidad.
- **Riesgo**: elegir si un préstamo **en mora** ya cuenta como riesgo (los incumplidos siempre cuentan).
- **Vista previa en vivo**: muestra cuántos clientes quedarían en cada categoría con los nuevos valores (y cuántos suben o bajan) **antes de guardar**.
- Botón para **restablecer los valores por defecto**.
- Configuración **por empresa** (el super admin elige la empresa); la lista y la ficha del cliente muestran las reglas vigentes.
- Acceso con permiso propio (Administrador y Gerente), con botón **"Categorías"** en la lista de clientes.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 3,0
- **Subtotal:** Bs 120,00

---

### 3. Ficha del cliente más compacta
- La tarjeta **"Información del cliente"** en formato compacto (letras más pequeñas), manteniendo el tamaño de la foto.
- El **"Historial de pagos"** se ubica debajo de la información del cliente para aprovechar el espacio vacío; ambas columnas quedan parejas.

- **Tipo:** Mejora visual
- **Horas:** 0,5
- **Subtotal:** Bs 20,00

---

### 4. Etiquetas de clientes administrables y con permiso
Las etiquetas manuales (ej. CLIENTE FIEL, VIP) se administran desde el panel de categorías y se eligen de una lista.

Incluye:
- Sección **"Etiquetas de clientes"** en "Configurar categorías": **crear**, **renombrar**, elegir **color** y **eliminar** etiquetas, con la cantidad de clientes que tiene cada una (enlace a la lista filtrada).
- Al renombrar, los clientes conservan la etiqueta con el nombre nuevo; al eliminar, se pide confirmación y se quita de los clientes.
- En **crear / editar cliente** la etiqueta se elige de una **lista** (ya no se escribe a mano).
- En la **ficha del cliente**, selector rápido para poner o quitar la etiqueta sin entrar a editar.
- Permiso aparte **"Etiquetar clientes"** (Administrador y Gerente); sin el permiso la etiqueta se ve pero no se puede cambiar.
- Las etiquetas se muestran con su color en la lista y en la ficha.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

### 5. Moneda configurable por empresa
Cada empresa tiene su **símbolo de moneda** (en Santa Ana: **Bs**), editable en el formulario de la empresa.

Incluye:
- Campo "Símbolo de la moneda" al crear o editar una empresa.
- Todos los montos del sistema web (préstamos, cobros, ventas, compras, caja, inventario, catálogos, reportes,
  exportaciones y mensajes) usan ese símbolo. Antes algunas pantallas mostraban "Bs" y otras "$".
- La app móvil recibe la moneda al iniciar sesión y la muestra en todas sus pantallas (antes había pantallas con "S/").

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 3,0
- **Subtotal:** Bs 120,00

---

### 6. Web: menú con la sucursal, botón para ocultar el menú y estilo estándar en las listas
- **Menú lateral**: bajo el nombre de la empresa se muestra la sucursal del personal en lugar del RUC (si tiene
  varias, la de su caja abierta).
- **Botón de menú** en la cabecera: oculta o muestra el menú lateral y recuerda la preferencia (en el celular abre el
  menú deslizable).
- **Estilo estándar** en Clientes, Préstamos, Pagos de préstamos, Compras y Ventas: título y cantidad de registros en
  una sola fila, pills de categorías y sucursales más pequeñas, tablas y botones del mismo tamaño. Los estilos quedan
  como clases comunes para ir ajustando las demás pantallas.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 4,0
- **Subtotal:** Bs 160,00

---

### 7. Web: cargos con rol propio y pantalla de permisos renovada
- Al crear un cargo ya no se elige un rol: el sistema le crea su **propio rol** con el mismo nombre, para que cambiar
  los permisos de un cargo no afecte a otros. Al editar se cambian los permisos de ese rol.
- Pantalla de crear y editar rediseñada: datos del cargo en una fila y los **permisos a todo el ancho**, agrupados por
  módulo en español (Clientes, Préstamos, Pagos…), con casilla para marcar todo un módulo, buscador, contador y la
  barra de guardar siempre visible. Avisa si un cargo antiguo comparte su rol con otro.
- Corrección incluida: al desmarcar todos los permisos ahora sí se guardan (antes no se guardaba el cambio).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

### 8. Manual de usuario dentro del sistema (web y app móvil), con descarga en PDF
Manual para el personal, organizado **por tareas del día** (cómo abrir la caja, registrar un préstamo, cobrar un
interés, liquidar, vender, comprar, cerrar la caja…), con una referencia final por menú.

Incluye:
- **16 capítulos**: primeros pasos, caja (apertura, gastos y cierre), clientes (categorías y etiquetas), préstamos,
  cobros (interés, amortización, liquidación, multa, eliminar/restaurar intereses), mora y multa, ventas (contado,
  crédito, cuotas, venta rápida, devoluciones y autorizaciones), compras, inventario y productos, catálogos,
  movimientos y reportes (cierres, descuadres y ajustes, exportaciones), capital, administración del sistema
  (empresa, sucursales, cargos y permisos, personal, cajas, categorías de crédito, plantillas), app móvil,
  preguntas frecuentes y referencia por menú.
- **Vista "Manual de usuario"** en el sistema web (nuevo acceso en el menú lateral, sección *Ayuda*): índice de
  capítulos, **buscador** (por ejemplo "cerrar caja" o "multa" muestra solo las secciones que lo mencionan), avisos y
  consejos destacados; se adapta al celular.
- Botón **Descargar PDF**: el mismo manual en PDF (portada, índice y número de página), listo para imprimir o
  enviar. Siempre sale con el contenido actualizado.
- **App móvil**: opción *Manual de usuario* en el menú lateral, que abre el manual en el navegador del celular.
- El manual se puede abrir sin iniciar sesión (no muestra datos del negocio), para consultarlo desde cualquier
  dispositivo.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 10,0
- **Subtotal:** Bs 400,00

---

## Resumen

| # | Desarrollo | Tipo | Horas | Tarifa (Bs/h) | Subtotal (Bs) |
|---|-----------|------|:-----:|:-------------:|--------------:|
| 1 | Clasificación y filtros de clientes (puntuales, fieles y los que más se prestan) | Mejora | 4,0 | 40,00 | 160,00 |
| 2 | Panel de configuración de las categorías de clientes | Mejora | 3,0 | 40,00 | 120,00 |
| 3 | Ficha del cliente más compacta | Mejora visual | 0,5 | 40,00 | 20,00 |
| 4 | Etiquetas de clientes administrables y con permiso | Mejora | 2,0 | 40,00 | 80,00 |
| 5 | Moneda configurable por empresa (web y app) | Mejora | 3,0 | 40,00 | 120,00 |
| 6 | Web: menú con la sucursal, botón de menú y estilo estándar en las listas | Mejora | 4,0 | 40,00 | 160,00 |
| 7 | Web: cargos con rol propio y pantalla de permisos renovada | Mejora | 2,0 | 40,00 | 80,00 |
| 8 | Manual de usuario dentro del sistema (web y app), con descarga en PDF | Mejora | 10,0 | 40,00 | 400,00 |
| | | | **28,5** | | **1.140,00** |

**TOTAL: Bs 1.140,00**

---

## Notas
- Los montos están expresados en bolivianos (Bs).
- Las horas corresponden a análisis, redacción, desarrollo, pruebas y puesta en funcionamiento.
- Estos trabajos son **funcionalidades nuevas** solicitadas, no correcciones de errores.
- La actualización del manual por cambios futuros del sistema se cotiza junto con cada nuevo desarrollo.
- Validez de la cotización: [15 días] a partir de la fecha.
- Forma de pago: [a convenir].
