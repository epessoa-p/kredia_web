# Cotización de desarrollo — Formulario único para ingresar productos (N.º 08)

> Documento editable. Ajusta los datos entre corchetes `[ ]`, las horas o la tarifa según necesites.

**Para:** [Nombre del cliente / Santa Ana]
**De:** [Tu nombre / Eric Pessoa Montaño]
**Fecha:** [dd/mm/aaaa]
**Cotización N°:** [008]
**Tarifa aplicada:** Bs 40,00 / hora

---

## Detalle de los desarrollos

---

### 1. Formulario único para ingresar productos (rediseño) — sistema web
Antes había dos formas de crear un producto: **Productos › Nuevo producto** (creaba un producto sin sucursal, sin
stock ni fotos, que no aparecía en el inventario) e **Inventario › Ingreso de stock**. Como cada producto es un
artículo único (cantidad 1), crear un producto es ingresarlo al inventario: ahora hay **un solo formulario,
"Ingresar producto"**, al que llevan los botones de Inventario, Productos y la ficha del almacén.

Incluye:
- **Solo se elige la sucursal** (botones con su color); el producto entra al almacén de esa sucursal, sin elegir
  almacén. El personal que no es gerente queda fijo a su sucursal (también se valida en el servidor).
- Formulario rediseñado en **pasos numerados** (sucursal, categoría, datos, valores, fotos, más detalles) que se
  marcan en verde al completarse y en rojo si tienen errores, con un **resumen** a la derecha (en el celular, al final).
- **Categorías como tarjetas con ícono**; según la categoría aparecen sus datos: vehículo, joya, garrafa (nombre
  automático) y, para el resto (celular, computadora, heladera…), los de artículo: marca, modelo y serie/IMEI
  (antes esas categorías no pedían ningún dato).
- **Costo** (obligatorio) y **precio de venta** con la moneda de la empresa y la **ganancia estimada** en vivo.
- Fecha, referencia y notas en "Más detalles" (plegado); fotos desde archivo o cámara.
- Mensajes de error en español junto a cada campo, aviso antes de enviar si falta algo y protección contra doble envío.
- Actualización del manual de usuario (capítulo Inventario).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 3,5
- **Subtotal:** Bs 140,00

---

### 2. Ícono de las categorías y regla de crédito única — sistema web
Al crear o editar una categoría de crédito:
- Campo **Ícono**: se elige de una lista de 30 íconos (vehículo, joya, garrafa, celular, computadora, heladera,
  herramienta, ropa…) con **vista previa** en el color de la categoría; al crear una categoría se sugiere uno según el
  nombre. El ícono se ve en las tarjetas de "Ingresar producto", en la lista y en el detalle de categorías, y lo
  recibe la app en el inventario. Las categorías existentes reciben automáticamente el ícono que ya mostraban.
- **Una sola regla de crédito** por categoría (sin "Agregar regla" ni "Quitar"): interés % y plazo tope obligatorios;
  la regla siempre queda activa. Su **nombre se genera solo** (ej. "10% MENSUAL · 3 MESES", con vista previa) y usa el
  **monto mínimo y máximo de la categoría** (antes se pedían dos veces); se valida que el máximo no sea menor que el mínimo.
- Corrección incluida: los errores al guardar una categoría ahora se muestran en español junto a cada campo (antes
  solo "No fue posible crear la categoría").
- Actualización del manual de usuario (Administración › Categorías de crédito).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 2,0
- **Subtotal:** Bs 80,00

---

## Resumen

| # | Desarrollo | Tipo | Horas | Tarifa (Bs/h) | Subtotal (Bs) |
|---|-----------|------|:-----:|:-------------:|--------------:|
| 1 | Formulario único para ingresar productos (rediseño) — web | Mejora | 3,5 | 40,00 | 140,00 |
| 2 | Ícono de las categorías y regla de crédito única — web | Mejora | 2,0 | 40,00 | 80,00 |
| | | | **5,5** | | **220,00** |

**TOTAL: Bs 220,00**

---

## Notas
- Los montos están expresados en bolivianos (Bs).
- Las horas corresponden a análisis, desarrollo, pruebas y puesta en funcionamiento.
- Estos trabajos son **funcionalidades nuevas** solicitadas, no correcciones de errores.
- Incluye la actualización del manual de usuario (capítulo Inventario).
- Validez de la cotización: [15 días] a partir de la fecha.
- Forma de pago: [a convenir].
