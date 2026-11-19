# Cotización de desarrollo — Código de productos e inventario, sistema web y app móvil (N.º 07)

> Documento editable. Ajusta los datos entre corchetes `[ ]`, las horas o la tarifa según necesites.

**Para:** [Nombre del cliente / Santa Ana]
**De:** [Tu nombre / Eric Pessoa Montaño]
**Fecha:** [dd/mm/aaaa]
**Cotización N°:** [007]
**Tarifa aplicada:** Bs 40,00 / hora

---

## Detalle de los desarrollos

Código para identificar cada producto de palabra o en una etiqueta, en el sistema web y en la app móvil.

---

### 1. Código correlativo de productos (PD-00001) — sistema web
Cada producto tiene un **código** además del SKU automático, para identificarlo fácilmente.

Incluye:
- Correlativo PD- de la empresa (PD-00001, PD-00002…) que no se repite ni se reutiliza.
- El código se muestra en el inventario (dentro de una etiqueta de color), el detalle del producto, el listado de
  productos, el detalle del préstamo, de la compra y de la venta, el selector de productos al vender y el catálogo de
  venta rápida.
- Búsqueda por código en el inventario y en las ventas (PD-00029, PD-29 o pd29).

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 3,0
- **Subtotal:** Bs 120,00

---

### 2. Código de producto según su origen, editable y sin repetirse — sistema web
El código del producto indica de dónde viene:
- **Producto de un préstamo**: lleva el código del préstamo (PR-00123) desde que se registra; lo conserva al pasar a
  la venta. Los productos de stock que comparten varios préstamos (por ejemplo las garrafas) mantienen su código.
- **Producto de una compra**: lleva el código de la compra (CO-00007).
- **Ingreso de stock y alta manual**: campo **"Código del producto"** con el siguiente correlativo PD- sugerido, que se
  puede cambiar.
- El código **no se repite dentro de la misma sucursal** (se valida al crear y al editar) y se puede corregir en
  Productos › editar. Se actualizaron los productos existentes con el código de su préstamo o compra.
- Corrección incluida: el alta y edición de productos muestra los errores de validación en español (antes solo
  "No fue posible crear el producto").

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 4,0
- **Subtotal:** Bs 160,00

---

### 3. Código de producto en la app móvil
La app muestra el código del producto en el inventario, en el detalle del producto y en la prenda del préstamo, y
permite buscar por ese código.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

---

### 4. Código del producto en el ingreso de stock de la app
El formulario de ingreso de stock de la app tiene el campo **"Código del producto"** con el siguiente PD- sugerido
(se puede cambiar); avisa si el código ya está en uso en la sucursal. La lista y el detalle muestran el código de
origen (PR-, CO- o PD-) y se puede buscar por él.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,5
- **Subtotal:** Bs 60,00

---

### 5. Un producto por cada préstamo o compra (cantidad siempre 1)
Cada artículo que entra es usado y tiene su propio valor (un celular igual puede valer 600 y otro 500). Antes, al
registrar un préstamo o una compra se reutilizaba un producto igual y el inventario mostraba, por ejemplo,
"GARRAFAS cantidad 8".

Incluye:
- Cada préstamo, compra e ingreso de stock (web y app) crea su **propio producto** con cantidad 1, su costo y su
  código (PR-, CO- o PD-).
- Botón **"Separar productos"** para el super administrador (en Productos e Inventario): separa los productos compartidos de antes
  en un producto por préstamo o compra, y les pasa sus movimientos de inventario, garantías y ventas sin cambiar el
  stock. Pide confirmación y solo aparece si hay productos por separar.
- Actualización del manual (Inventario) y de la documentación de la API.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 3,0
- **Subtotal:** Bs 120,00

---

### 6. Cantidad total y filtro de cantidad en el inventario
- **Cantidad total** de unidades según los filtros aplicados (todas las páginas), separada en disponibles, en
  préstamo y en retención; para todos los usuarios.
- Filtro de **cantidad** (más de 1, 3, 5 o 10 unidades del producto en el almacén), **solo para el super
  administrador**, para encontrar los productos que todavía están agrupados.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 1,0
- **Subtotal:** Bs 40,00

---

### 7. Filtro de productos por regularizar en el inventario
Al hacer clic en el aviso **"N por regularizar"** del inventario se ven solo esos productos (prendas de préstamos
eliminados que quedaron sin liberar), para regularizarlos uno por uno. Corrección incluida: las prendas a la venta
de préstamos eliminados ahora también se marcan como "Requiere regularización" en su fila.

- **Tipo:** Funcionalidad nueva (mejora)
- **Horas:** 0,5
- **Subtotal:** Bs 20,00

---

## Resumen

| # | Desarrollo | Tipo | Horas | Tarifa (Bs/h) | Subtotal (Bs) |
|---|-----------|------|:-----:|:-------------:|--------------:|
| 1 | Código correlativo de productos (PD-00001) — web | Mejora | 3,0 | 40,00 | 120,00 |
| 2 | Código de producto según su origen, editable y sin repetirse — web | Mejora | 4,0 | 40,00 | 160,00 |
| 3 | Código de producto en la app móvil | Mejora | 1,0 | 40,00 | 40,00 |
| 4 | Código del producto en el ingreso de stock de la app | Mejora | 1,5 | 40,00 | 60,00 |
| 5 | Un producto por cada préstamo o compra (cantidad 1) y separación de los compartidos | Mejora | 3,0 | 40,00 | 120,00 |
| 6 | Cantidad total y filtro de cantidad en el inventario | Mejora | 1,0 | 40,00 | 40,00 |
| 7 | Filtro de productos por regularizar en el inventario | Mejora | 0,5 | 40,00 | 20,00 |
| | | | **14,0** | | **560,00** |

**TOTAL: Bs 560,00**

---

## Notas
- Los montos están expresados en bolivianos (Bs).
- Las horas corresponden a análisis, desarrollo, pruebas y puesta en funcionamiento.
- Estos trabajos son **funcionalidades nuevas** solicitadas, no correcciones de errores.
- Incluye la actualización del manual de usuario (capítulo Inventario) y de la documentación de la API.
- Validez de la cotización: [15 días] a partir de la fecha.
- Forma de pago: [a convenir].
