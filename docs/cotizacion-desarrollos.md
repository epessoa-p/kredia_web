# Cotización de desarrollo — Sistema de Préstamos

> Documento editable. Ajusta los datos entre corchetes `[ ]`, las horas o la tarifa según necesites.

**Para:** [Nombre del cliente / Santa Ana]
**De:** [Tu nombre / Eric Pessoa Montaño]
**Fecha:** [dd/mm/aaaa]
**Cotización N°:** [001]
**Tarifa aplicada:** Bs 40,00 / hora

---

## Detalle de los desarrollos

### 1. Panel de reinicio por sucursal
Herramienta administrativa para **dejar una sucursal en cero**, borrando todos sus registros operativos (préstamos, ventas, compras, sus pagos/cuotas/intereses, historial de caja e inventario del almacén) **sin afectar a las demás sucursales** y **conservando** el personal, las cajas configuradas, los clientes y los productos.

Incluye:
- Análisis completo del modelo de datos para garantizar un borrado seguro (que no dañe información de otras sucursales).
- Nuevo permiso `Reiniciar Sucursal` (controlable desde Roles).
- Confirmación obligatoria escribiendo el nombre exacto de la sucursal (evita borrados accidentales).
- Reinicio de los códigos correlativos (PR-, VE-, CO-) a partir de 1.
- Registro de auditoría (quién y cuándo ejecutó el reinicio).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 6
- **Subtotal:** Bs 240,00

### 2. Nombre del personal que registró la operación
Se muestra **quién registró** cada préstamo, venta y compra:
- En la ficha del **cliente**, dentro de las pestañas de Préstamos, Ventas y Compras.
- (Ya disponible previamente en el listado general de préstamos.)

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

### 3. Ventas limitadas al almacén de la caja + recomendación de otras sucursales
Control para que cada usuario **solo pueda vender los productos del almacén de la sucursal ligada a su caja abierta**, con la posibilidad de **consultar** si un producto existe en otra sucursal (para recomendar al cliente ir o pedir el traslado) y sin poder vender si no hay una caja abierta.

Incluye:
- En "Nueva Venta" y "Venta rápida", el catálogo muestra como vendibles solo los productos del almacén de la caja del usuario.
- Los productos de otras sucursales aparecen como **referencia** ("Disponible en Sucursal X"), sin opción de venderlos.
- Sin caja abierta: solo se pueden ver productos; se desactivan los botones de vender (también en la ficha del cliente).
- **Refuerzo de seguridad en el servidor:** la venta siempre se registra en la sucursal/almacén de la caja, aunque se manipule la petición (se cerró una vía por la que se podía vender de otra sucursal).
- Análisis del modelo de datos (sucursal → almacén, almacenes compartidos) para aplicar el criterio correcto.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 5
- **Subtotal:** Bs 200,00

---

## Resumen

| # | Desarrollo | Tipo | Horas | Tarifa (Bs/h) | Subtotal (Bs) |
|---|-----------|------|:-----:|:-------------:|--------------:|
| 1 | Panel de reinicio por sucursal | Mejora | 6,0 | 40,00 | 240,00 |
| 2 | Nombre del personal en ficha del cliente | Mejora | 1,5 | 40,00 | 60,00 |
| 3 | Ventas limitadas al almacén de la caja + recomendación de otras sucursales | Mejora | 5,0 | 40,00 | 200,00 |
| | | | **12,5** | | **500,00** |

**TOTAL: Bs 500,00**

---

## Notas
- Los montos están expresados en bolivianos (Bs).
- Las horas corresponden a análisis, desarrollo, pruebas y puesta en funcionamiento.
- Estos trabajos son **funcionalidades nuevas** solicitadas, no correcciones de errores.
- Validez de la cotización: [15 días] a partir de la fecha.
- Forma de pago: [a convenir].
