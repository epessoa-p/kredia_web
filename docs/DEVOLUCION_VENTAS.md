# Devolución de Ventas — Flujo y Movimientos

## Resumen

La devolución de una venta revierte todos los efectos económicos e inventariales de la transacción original. La venta no se elimina; cambia a estado **`returned`** conservando el historial completo.

---

## Condiciones para procesar una devolución

| Condición | Detalle |
|-----------|---------|
| Estado de la venta | Debe ser `completed`. No se puede devolver una venta ya `returned` o `cancelled`. |
| Caja abierta | Debe existir una sesión de caja activa para registrar el egreso. |
| Permisos | El usuario debe tener el permiso `sales.create`. |

---

## Flujo paso a paso

### 1. Reintegro de inventario

Se crea un `InventoryMovement` de tipo **`in`** (entrada) con cantidad `1` en el almacén original:

```
inventory_movements INSERT:
  type          = 'in'
  quantity      = 1
  warehouse_id  = sale.warehouse_id
  product_id    = sale.product_id
  unit_cost     = sale.cost          ← costo original, no precio de venta
  reference     = 'DEVOLUCION-{id}'
  notes         = 'Devolución de venta #{id}'
```

El stock disponible del producto queda restaurado a su valor previo a la venta.

---

### 2. Reversión de movimientos de caja

#### Venta al Contado (`CONTADO`)

Se crea **un solo** `CashMovement` de tipo **`expense`**:

```
cash_movements INSERT:
  type      = 'expense'
  category  = 'sale_return'
  amount    = sale.amount           ← monto total de la venta
  reference_type = 'App\Models\Sale'
  reference_id   = sale.id
  description    = 'Devolución venta #{id} - {producto}'
```

#### Venta a Crédito (`CREDITO`)

Se crean **N movimientos de egreso**, uno por cada cuota en estado `PAGADA`:

```
PARA CADA installment WHERE status = 'PAGADA':
  cash_movements INSERT:
    type      = 'expense'
    category  = 'sale_return'
    amount    = installment.amount
    reference_type = 'App\Models\Sale'
    reference_id   = sale.id
    description    = 'Devolución cuota {N} - venta #{id}'
```

Las cuotas **no cobradas** (`PENDIENTE`, `VENCIDA`) se marcan como `ANULADA` sin generar movimiento de caja.

---

### 3. Anulación de cuotas pendientes

```
sale_installments UPDATE
  SET status = 'ANULADA'
  WHERE sale_id = {id}
    AND status IN ('PENDIENTE', 'VENCIDA')
```

Las cuotas `PAGADA` conservan su estado (el dinero fue devuelto vía egreso en caja).

---

### 4. Reversión de garantía de préstamo (si aplica)

Si la venta proviene de un producto dado como garantía de préstamo incumplido:

```
loan_collaterals UPDATE
  SET status = 'sellable'
  WHERE id = sale.loan_collateral_id
```

El bien vuelve a quedar disponible para una nueva venta o gestión de cobro.

---

### 5. Actualización del registro de venta

```
sales UPDATE:
  status        = 'returned'
  returned_at   = NOW()
  returned_by   = {user_id}
  return_reason = '{motivo}'        ← enum de razones predefinidas
  return_notes  = '{observaciones}' ← texto libre opcional
```

La venta **no se elimina**. El historial queda íntegro para auditoría.

---

## Diagrama de estados

```
  completed ──[devolución]──► returned
  completed ──[eliminación]──► (soft delete)
  cancelled  ──[no reversible]──  ✗
  returned   ──[no reversible]──  ✗
```

---

## Impacto por módulo

| Módulo | Acción |
|--------|--------|
| `inventory_movements` | INSERT tipo `in`, qty `1` — restaura stock |
| `cash_movements` | INSERT tipo `expense`, categoría `sale_return` — reduce saldo de caja |
| `sale_installments` | UPDATE pendientes/vencidas → `ANULADA` |
| `loan_collaterals` | UPDATE `sold` → `sellable` (solo si aplica) |
| `sales` | UPDATE status → `returned` + campos de auditoría |
| `products` | Sin cambio directo — stock se recalcula desde inventory_movements |

---

## Campos añadidos a la tabla `sales`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `returned_at` | `TIMESTAMP NULL` | Fecha y hora de la devolución |
| `returned_by` | `BIGINT FK → users` | Usuario que procesó la devolución |
| `return_reason` | `VARCHAR(255)` | Motivo seleccionado |
| `return_notes` | `TEXT` | Observaciones adicionales |

---

## Motivos de devolución disponibles

| Clave | Etiqueta |
|-------|----------|
| `producto_defectuoso` | Producto defectuoso |
| `error_registro` | Error en el registro |
| `cliente_arrepentido` | Cliente arrepentido |
| `producto_incorrecto` | Producto incorrecto |
| `otro` | Otro |

---

## Nota de auditoría

Toda devolución queda registrada con:
- Quién la procesó (`returned_by`)
- Cuándo se procesó (`returned_at`)
- Por qué se procesó (`return_reason` + `return_notes`)
- Trazabilidad completa en `cash_movements` e `inventory_movements` con `reference = 'DEVOLUCION-{id}'`
