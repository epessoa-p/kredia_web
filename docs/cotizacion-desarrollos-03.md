# Cotización de desarrollo — Sistema de Préstamos (N.º 03)

> Documento editable. Ajusta los datos entre corchetes `[ ]`, las horas o la tarifa según necesites.

**Para:** [ Santa Ana]
**De:** [ Eric Pessoa Montaño]
**Fecha:** [22/10/2026]
**Cotización N°:** [003]
**Tarifa aplicada:** Bs 40,00 / hora

---

## Detalle de los desarrollos

### 1. Regla de interés "al día siguiente del aniversario" (configurable por empresa)
Ajuste en la generación de intereses de los préstamos para evitar el reclamo de que, en la fecha exacta del aniversario, el cliente ya aparece con dos intereses.

Incluye:
- **Interruptor habilitar/deshabilitar** a nivel de empresa (en la configuración de la empresa).
- Con la regla activa, el primer interés se genera al crear el préstamo, pero el **siguiente interés recién aparece al día siguiente** del aniversario (si se crea el 15, el 2.º interés aparece el 16); en la fecha exacta el cliente debe un solo interés.
- Sin corrimiento de fechas ni impacto en los cálculos de multa/mora; aplica también a préstamos activos.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

### 2. Botón para registrar gastos (egresos) de la caja abierta
Nuevo botón **"Registrar gasto"** en la barra superior, junto a "Cerrar caja", para registrar egresos manuales de la caja abierta.

Incluye:
- Ventana para registrar el gasto con **tipo de gasto** (Servicio básico/externo, Pago a personal, Otro gasto operativo), **monto** y **notas**.
- En **Pago a personal** se selecciona obligatoriamente el **personal** que recibe el pago; el movimiento queda vinculado a esa persona.
- Muestra el **efectivo disponible** y valida que el gasto no supere ese saldo (validación en pantalla y en el servidor).
- El egreso descuenta el saldo de la caja y queda registrado en el reporte de **Movimientos de caja**.
- **Permiso** propio para controlar quién puede registrar gastos.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 3,0
- **Subtotal:** Bs 120,00

### 3. Recuadros de cajas con más detalle en el dashboard de gerencia
Rediseño de los recuadros de cajas del dashboard de gerencia, con un estilo más compacto y más información de cada caja.

Incluye:
- **Cajas abiertas:** saldo actual, hora de apertura y tiempo abierta; resumen de apertura, ingresos y egresos; desglose de **desembolsos** (con cantidad), **cobros de préstamos**, **ventas** y **gastos**; **monto del último cierre** (con fecha y quién la cerró); responsable y número de movimientos.
- **Cajas cerradas:** datos del **último cierre** (fecha, monto cerrado y quién la cerró) y el personal asignado.
- Estilo más compacto (tipografía y espacios reducidos) para ver más información de un vistazo.
- La información se calcula de forma eficiente, sin hacer más lento el dashboard.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

### 4. Filtro de "Intereses sin pagar" en el listado de préstamos
Nuevo filtro en el listado de préstamos para ver los préstamos según la cantidad de intereses sin pagar.

Incluye:
- Selección múltiple de cantidades: **1, 2, 3, 4 o 5+** (por ejemplo, ver los que tienen 2 y 3 intereses sin pagar a la vez).
- Se combina con los demás filtros (estado, categoría, sucursal, fechas, etc.).
- Etiqueta en cada préstamo con la cantidad de **intereses sin pagar**, visible directamente en el listado.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

### 5. Registro de quién eliminó un interés
En la vista del préstamo, los recuadros de intereses eliminados ahora muestran **quién lo eliminó, cuándo y por qué**.

Incluye:
- Se registra el usuario y el motivo en los tres casos en que se elimina un interés: **eliminación manual**, **al editar el préstamo** y **al eliminar el préstamo**.
- En cada recuadro eliminado se muestra: **Eliminado por** (nombre del personal), **fecha** y **motivo**, con el dato resaltado para que sea legible.
- Recuperación del historial disponible: los intereses eliminados junto con su préstamo toman el motivo y, cuando existe, el usuario que eliminó el préstamo.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

### 6. Ajustes visuales: recuadros de cajas y botón "Cerrar sesión"
Ajustes de presentación en el dashboard y en la barra superior.

Incluye:
- En los recuadros de cajas del dashboard, la etiqueta **Abierta / Cerrada** pasa debajo del nombre para que no se salga del recuadro; los montos grandes del resumen se ajustan para que siempre queden dentro.
- El botón **"Cerrar sesión"** se mueve del navbar al menú del perfil.

- **Tipo:** Ajuste
- **Horas:** 0,5
- **Subtotal:** Bs 20,00

### 7. Intereses sin pagar en los préstamos de la ficha del cliente
En la vista del cliente, cada préstamo de su listado muestra la etiqueta **"N intereses sin pagar"** debajo del estado, igual que en el listado general de préstamos.

Incluye:
- Cantidad de intereses sin pagar por préstamo, calculada de forma eficiente (sin hacer más lenta la ficha del cliente).
- La etiqueta no se muestra en los préstamos que no tienen intereses pendientes.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 0,5
- **Subtotal:** Bs 20,00

### 8. Código del préstamo en los productos del catálogo
En los recuadros de productos del catálogo se muestra el **código del préstamo** al que pertenece cada producto, para hacer una revisión previa antes de publicar.

Incluye:
- Código **PR-** del préstamo cuya prenda está disponible para la venta; si el producto viene de una compra, su código **CO-**; si no tiene origen, "Sin préstamo".
- Cuando un producto pertenece a varios préstamos, se muestran los primeros y un indicador **"+N"** con el resto.
- Visible en la **vista del catálogo** (con enlace al préstamo) y en la **selección de productos** al crear o editar un catálogo.
- El buscador de productos también encuentra por código (ej. PR-00523).
- No se muestra en el enlace público ni en el PDF que ven los clientes.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

### 9. Mejoras en crear/editar catálogo: empresa, sucursal y experiencia de uso
Rediseño del formulario para crear y editar catálogos, con asignación por sucursal.

Incluye:
- **Campo Empresa** para el super administrador, que puede crear catálogos de cualquier empresa.
- **Catálogo por sucursal** (o "Todas las sucursales"): solo se listan los productos de esa sucursal, el contacto se completa con los datos de la sucursal y el personal que no es gerente trabaja solo con su sucursal. La sucursal se muestra en la lista, en el detalle y en el enlace público.
- **Grilla de productos con fotos** y filtros por nombre o código, categoría, origen y sucursal, con opción de **agregar todos los filtrados**.
- **Ordenar los productos arrastrándolos**; el orden se respeta en el enlace público y en el PDF.
- **Vista previa en vivo** de cómo verá el cliente el catálogo.
- **Colores distintivos por sección** (franja a la izquierda y tinte en el encabezado): empresa y sucursal, configuración, productos disponibles para la venta, productos en el catálogo y vista previa, para reconocer cada parte de un vistazo.
- Crear y editar unificados en un solo formulario, con los mismos productos disponibles y protección contra doble guardado.
- Las fotos de los productos (de inventario, del préstamo o de la compra) ahora se muestran también en el enlace público, en el detalle y en el PDF.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 6,0
- **Subtotal:** Bs 240,00

### 10. Botón para restaurar un interés eliminado
En la vista del préstamo, los intereses eliminados se pueden **restaurar** con un botón pequeño, controlado por un permiso propio.

Incluye:
- Botón **"Restaurar"** en el recuadro del interés eliminado, con confirmación y protección contra doble clic.
- **Permiso propio** ("Restaurar interés eliminado"), asignado a Super Admin, Administrador y Gerente; se puede dar o quitar por rol.
- Validaciones: solo en préstamos activos o en mora, y no se restaura si ese periodo ya tiene un interés activo.
- Registro de **quién restauró y cuándo**, visible en el recuadro del interés.
- Al restaurar, el interés vuelve a contar para el pendiente y el estado de mora del préstamo.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

## Resumen

| # | Desarrollo | Tipo | Horas | Tarifa (Bs/h) | Subtotal (Bs) |
|---|-----------|------|:-----:|:-------------:|--------------:|
| 1 | Regla de interés "al día siguiente del aniversario" (configurable) | Mejora | 2,0 | 40,00 | 80,00 |
| 2 | Botón para registrar gastos (egresos) de la caja abierta | Mejora | 3,0 | 40,00 | 120,00 |
| 3 | Recuadros de cajas con más detalle en el dashboard de gerencia | Mejora | 1,5 | 40,00 | 60,00 |
| 4 | Filtro de "Intereses sin pagar" en el listado de préstamos | Mejora | 1,0 | 40,00 | 40,00 |
| 5 | Registro de quién eliminó un interés | Mejora | 1,5 | 40,00 | 60,00 |
| 6 | Ajustes visuales: recuadros de cajas y botón "Cerrar sesión" | Ajuste | 0,5 | 40,00 | 20,00 |
| 7 | Intereses sin pagar en los préstamos de la ficha del cliente | Mejora | 0,5 | 40,00 | 20,00 |
| 8 | Código del préstamo en los productos del catálogo | Mejora | 1,0 | 40,00 | 40,00 |
| 9 | Mejoras en crear/editar catálogo: empresa, sucursal y experiencia de uso | Mejora | 6,0 | 40,00 | 240,00 |
| 10 | Botón para restaurar un interés eliminado | Mejora | 1,5 | 40,00 | 60,00 |
| | | | **18,5** | | **740,00** |

**TOTAL: Bs 740,00**

---

## Notas
- Los montos están expresados en bolivianos (Bs).
- Las horas corresponden a análisis, desarrollo, pruebas y puesta en funcionamiento.
- Estos trabajos son **funcionalidades nuevas** solicitadas, no correcciones de errores.

