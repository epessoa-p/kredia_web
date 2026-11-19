# API Móvil — Módulo de Préstamos

Guía técnica para el desarrollador de la app móvil que cubre el flujo completo
de consulta de datos previos y registro de un nuevo préstamo.

---

## Autenticación

Todas las peticiones requieren el header:

```
Authorization: Bearer <token>
```

El token se obtiene en `POST /api/v1/auth/login` y se incluye en cada petición.

### Datos que devuelve el login

```json
{
  "token": "...",
  "user": { "id": 5, "name": "Ana López", "is_super_admin": false },
  "company": { "id": 1, "name": "Prestamos SA", "currency_symbol": "Bs" },
  "permissions": ["loans.view", "loans.create", "clients.view", ...],
  "cash_register": { ... } ,
  "companies": [{ "id": 1, "name": "Prestamos SA", "ruc": "...", "currency_symbol": "Bs" }]
}
```

**Guardar localmente:** `token`, `company.id`, `user.id` y `permissions`.
Si `company` es `null` (super admin), pedir que elija una de `companies` y llamar
`POST /api/v1/auth/company { "company_id": 1 }` antes de abrir el formulario: sin empresa,
`GET /loans/categories` responde 403.  
La app debe verificar que el usuario tenga el permiso `loans.create` para
habilitar el formulario de nuevo préstamo.

---

## Lógica de caja y sucursal

### Por qué la sucursal NO es seleccionable

Cada cajero tiene una **caja asignada** y esa caja pertenece a una **sucursal**.
Al registrar el préstamo, el sistema deriva la sucursal de la caja abierta del
usuario, igual que en la web. Si el usuario intenta crear un préstamo sin tener
una caja abierta, la API retorna **HTTP 409**.

### Moneda de la empresa

`POST /auth/login` y `GET /auth/me` devuelven `company.currency_symbol` (ej. `"Bs"`), configurable por
empresa en la web. La app lo usa para mostrar todos los montos (`lib/utils/currency.dart`).

### Cómo saber si hay una caja abierta

- `POST /auth/login` y `GET /auth/me` devuelven `cash_register` (caja asignada y si está abierta).
- `GET /api/v1/cash-session` devuelve el estado completo de la caja (saldo, sucursal, si puede abrir/cerrar);
  la app lo usa en la hoja de caja y para mostrar el saldo disponible en el formulario.
- El servidor vuelve a comprobarlo al registrar: si no hay caja abierta, el POST responde 409:

```json
{
  "message": "No hay una caja abierta asignada...",
  "code": "no_open_cash_session"
}
```

Cuando el POST devuelve **201**, el objeto `loan.branch` contiene el nombre de
la sucursal con la que quedó registrado el préstamo.

---

## Flujo de pantallas recomendado

```
1. Login  →  guardar token, company_id, permissions
2. Pantalla "Nuevo Préstamo"
   a. Cargar categorías          GET /api/v1/loans/categories
   b. El usuario selecciona una categoría → mostrar campos dinámicos (ver sección de campos)
   c. Cargar clientes (buscador) GET /api/v1/clients?q=...
   d. Usuario llena datos del producto y montos
3. POST /api/v1/loans            → 201 OK  |  409 sin caja  |  422 validación
4. Mostrar comprobante con loan.code (ej. "PR-00042")
```

---

## Endpoint: Categorías y reglas de crédito

```
GET /api/v1/loans/categories
```

**Permiso requerido:** `loans.view`

Llamar este endpoint al iniciar la pantalla de nuevo préstamo. La respuesta
contiene las categorías **activas** de la empresa (igual que la web) y, para cada una, las
reglas de crédito activas con todos los parámetros que necesita el formulario.

> La empresa puede tener más categorías que las 4 básicas (ej. COMPUTADORA, CELULAR):
> el selector debe armarse con esta lista, no con valores fijos. Las categorías distintas de
> 1 (Vehículo), 2 (Joyas) y 3 (Garrafas) usan los campos de **Artículos** (nombre, marca, serial).
> Registrar con una categoría desactivada responde 422.
> Como en la web, la tasa editable (Vehículo/Joyas) acepta cualquier entero de 1 a 100, y
> `min_amount`/`max_amount` de la regla son informativos (no se validan).

### Respuesta

```json
{
  "categories": [
    {
      "id": 1,
      "name": "Vehículo",
      "interest_rate_editable": true,
      "rules": [
        {
          "id": 5,
          "name": "Regla estándar",
          "interest_rate": 10.0,
          "interest_period": "monthly",
          "term_months": 3,
          "min_amount": null,
          "max_amount": null
        }
      ]
    },
    {
      "id": 2,
      "name": "Joyas",
      "interest_rate_editable": true,
      "rules": [{ "id": 6, "interest_rate": 8.0, "interest_period": "monthly", "term_months": 2, ... }]
    },
    {
      "id": 3,
      "name": "Garrafas",
      "interest_rate_editable": false,
      "rules": [{ "id": 7, "interest_rate": 5.0, "interest_period": "monthly", "term_months": 1, ... }]
    },
    {
      "id": 4,
      "name": "Artículos",
      "interest_rate_editable": false,
      "rules": [{ "id": 8, "interest_rate": 5.0, "interest_period": "monthly", "term_months": 1, ... }]
    }
  ]
}
```

### Campos obligatorios de producto por categoría

| id | nombre       | Campos obligatorios del producto              |
|----|--------------|-----------------------------------------------|
| 1  | Vehículo     | `product_name`, `product_brand`, `product_model_year`, `product_plate`, `product_chassis` |
| 2  | Joyas        | `product_item_type`, `product_karat`          |
| 3  | Garrafas     | ninguno (el nombre lo pone el sistema)        |
| 4  | Artículos    | `product_name`, `product_brand`, `product_serial_number` |

---

## Lógica de tasa de interés y reglas por categoría

### Cómo funciona

Cada categoría tiene una o más **reglas de crédito** configuradas en el sistema.
Al seleccionar una categoría, el sistema elige **automáticamente la primera regla
activa** (ordenada por tasa de interés, luego por ID). La app debe hacer lo mismo.

La regla define tres valores que el formulario debe pre-rellenar:

| Campo | Pre-rellenar con | ¿Editable por el usuario? |
|-------|-----------------|--------------------------|
| `interest_rate` | `rule.interest_rate` | Depende de la categoría (ver abajo) |
| `interest_period` | `rule.interest_period` | ❌ Nunca — campo oculto |
| `term_months` | `rule.term_months` | ❌ Nunca — campo oculto |

### Editabilidad de `interest_rate` por categoría

El campo `interest_rate_editable` de la respuesta de `/loans/categories` indica esto directamente:

| Categoría | `interest_rate_editable` | Comportamiento |
|-----------|:---:|---|
| 1 — Vehículo | `true` | Pre-rellenar con `rule.interest_rate`; **el usuario puede modificarla** |
| 2 — Joyas | `true` | Pre-rellenar con `rule.interest_rate`; **el usuario puede modificarla** |
| 3 — Garrafas | `false` | Mostrar la tasa como texto informativo; **no editable** |
| 4 — Artículos | `false` | Mostrar la tasa como texto informativo; **no editable** |

> **Importante:** el servidor **ignora** el valor de `interest_rate` que la app
> envíe para categorías 3 y 4. Siempre usa el de la regla. No es necesario
> enviar `interest_rate` para esas categorías, pero tampoco causa error si se envía.

### Validación de monto

Igual que en la web, `min_amount` y `max_amount` de la regla son **informativos**: no se validan
(ni en la app ni en el servidor). Lo que sí se valida es que `amount` no supere el **saldo de la caja
abierta** (422 con el mensaje de saldo insuficiente).

### Flujo recomendado al seleccionar categoría

```
1. Llamar GET /api/v1/loans/categories al cargar la pantalla (una sola vez).
2. Cuando el usuario elige una categoría:
   a. Tomar el primer elemento de category.rules → selectedRule
   b. Pre-rellenar interest_rate con selectedRule.interest_rate
   c. Si category.interest_rate_editable == false → mostrar interest_rate como
      texto solo lectura (o badge); deshabilitar el input
   d. Guardar selectedRule.interest_period y selectedRule.term_months en variables
      internas (no mostrar al usuario; se envían al POST)
   e. Mostrar/ocultar los grupos de campos de producto según la categoría
3. Al hacer POST, incluir solo interest_rate (si es editable).
   No enviar interest_period ni term_months — el servidor los toma de la regla.
```

### Ejemplo: categoría Garrafas (tasa no editable)

```
Categoría seleccionada: Garrafas (id=3)
selectedRule = categories[2].rules[0]
  → interest_rate: 5.0   (mostrar como texto, no input)
  → interest_period: "monthly"  (guardado internamente)
  → term_months: 1              (guardado internamente)
```

El POST solo necesita enviar `client_id`, `credit_category_id` y `amount`.
No enviar `interest_rate` — el servidor usará 5.0 de la regla.

---

## Endpoint: Buscar clientes

```
GET /api/v1/clients?q=<texto>&per_page=20
```

**Respuesta:** lista paginada de clientes (id, name, id_number, phone, email).

El campo `q` busca por nombre o número de documento.  
Mostrar un buscador con debounce de ~400 ms; no cargar todos los clientes al
inicio.

**Permiso requerido:** `clients.view`

---

## Endpoint: Registrar préstamo

```
POST /api/v1/loans
Content-Type: multipart/form-data   (si adjunta imágenes)
              application/json       (sin imágenes)
```

**Permiso requerido:** `loans.create`

### Campos del body

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `client_id` | integer | ✅ | ID del cliente seleccionado |
| `credit_category_id` | integer | ✅ | ID de la categoría (1–4) |
| `amount` | numeric | ✅ | Monto del préstamo en la moneda de la empresa |
| `interest_rate` | integer 1–100 | ⬜ | Tasa de interés. En cat. 1 y 2 el usuario la puede editar; en cat. 3 y 4 el servidor la ignora y usa siempre la de la regla |
| `start_date` | date `YYYY-MM-DD` | ⬜ | Solo si el usuario tiene permiso `loans.set_start_date`; si no, se usa hoy |
| `product_price` | numeric | ⬜ | Precio referencial del producto |
| `product_details` | string ≤1000 | ⬜ | Observaciones del producto |
| `images[]` | file (jpg/png/webp ≤5MB) | ⬜ | Fotos del producto (multipart) |
| `images_base64[]` | string base64 `data:image/...;base64,...` | ⬜ | Fotos en base64 |
| `request_id` | string ≤64 | ⬜ | Id único del intento. Reutilizarlo en los reintentos: si ya se registró, responde 409 `duplicate_request` y no crea otro préstamo |

> Igual que la web: el monto no puede superar el **saldo disponible en la caja** abierta (422 en `amount`),
> todo se guarda en una transacción (préstamo, desembolso en caja, prenda, imágenes y primer interés) y
> los mensajes de validación vienen en español.

#### Campos de producto por categoría

**Categoría 1 — Vehículo**
| Campo | Tipo | Req |
|-------|------|-----|
| `product_name` | string | ✅ |
| `product_brand` | string | ✅ |
| `product_model_year` | integer (1900–año actual+1) | ✅ |
| `product_plate` | string ≤120 | ✅ |
| `product_chassis` | string ≤120 | ✅ |
| `product_details` | string | ⬜ |

**Categoría 2 — Joyas**
| Campo | Tipo | Req |
|-------|------|-----|
| `product_item_type` | string ≤120 | ✅ — Ej: "Collar", "Anillo" |
| `product_karat` | string | ✅ — Uno de: `10K` `14K` `18K` `21K` `22K` `24K` |
| `product_material_type` | string | ⬜ — Uno de: `Oro` `Plata` `Platino` `Acero` `Otro` |
| `product_details` | string | ⬜ |

**Categoría 3 — Garrafas**  
Solo `product_details` (opcional). El sistema crea el producto con el nombre de la categoría.

**Categoría 4 — Artículos**
| Campo | Tipo | Req |
|-------|------|-----|
| `product_name` | string ≤255 | ✅ |
| `product_brand` | string ≤255 | ✅ |
| `product_serial_number` | string ≤120 | ✅ |
| `product_item_model` | string ≤255 | ⬜ — Modelo del artículo (ej: "ThinkPad E15") |
| `product_details` | string | ⬜ |

---

### Respuestas

#### ✅ 201 — Préstamo creado

```json
{
  "message": "Préstamo registrado exitosamente.",
  "loan": {
    "id": 87,
    "numero": 42,
    "code": "PR-00042",
    "amount": 1500.00,
    "interest_rate": 10,
    "interest_period": "monthly",
    "term_months": 3,
    "start_date": "2026-05-21",
    "end_date": "2026-08-21",
    "status": "active",
    "monthly_payment": 150.00,
    "total_to_pay": 1950.00,
    "branch": { "id": 2, "name": "Sucursal Centro" },
    "client": { "id": 14, "name": "Carlos Ramírez" },
    "category": { "id": 1, "name": "Vehículo" },
    "product": { "id": 33, "name": "Corolla" },
    "created_at": "2026-05-21T10:35:22+00:00"
  }
}
```

#### ❌ 409 — Sin caja abierta

```json
{
  "message": "No hay una caja abierta asignada a tu usuario. Apertura una caja antes de registrar préstamos.",
  "code": "no_open_cash_session"
}
```

Mostrar un mensaje al usuario indicando que debe aperturar la caja desde la
web o desde la sección de cajas de la app antes de poder registrar préstamos.

#### ❌ 409 — Caja sin sucursal configurada

```json
{
  "message": "La caja activa no tiene una sucursal asociada. Contacta al administrador.",
  "code": "cash_register_no_branch"
}
```

#### ❌ 409 — Envío duplicado

```json
{
  "message": "Este préstamo ya fue enviado. Revisa la lista de préstamos antes de intentarlo de nuevo.",
  "code": "duplicate_request"
}
```

#### ❌ 422 — Error de validación

```json
{
  "message": "Error de validación.",
  "errors": {
    "client_id": ["Selecciona un cliente."],
    "product_plate": ["El campo placa es obligatorio para vehículos."]
  }
}
```

Saldo insuficiente en caja:

```json
{
  "message": "El monto del préstamo (Bs 5,000.00) supera el saldo disponible en caja (Bs 1,670.00).",
  "errors": { "amount": ["El monto del préstamo (Bs 5,000.00) supera el saldo disponible en caja (Bs 1,670.00)."] }
}
```

Algunas reglas responden 422 solo con `message` (sin `errors`), por ejemplo
"Cliente no encontrado o inactivo en esta empresa." — mostrar ese mensaje.

Mapear cada clave de `errors` al campo del formulario y mostrar el mensaje
debajo del input correspondiente.

#### ❌ 403 — Sin permiso o empresa

```json
{ "message": "No tienes empresa activa asignada." }
```

#### ❌ 500 — Error interno

```json
{
  "message": "No fue posible registrar el préstamo. Por favor intenta nuevamente.",
  "code": "internal_error"
}
```

---

## Lógica de campos dinámicos por categoría

Cuando el usuario selecciona una categoría, la app debe:

1. Ocultar todos los grupos de campos de producto.
2. Mostrar solo el grupo correspondiente a la categoría seleccionada.
3. Deshabilitar/habilitar el campo `interest_rate`:
   - Categorías **3 y 4**: deshabilitar; la tasa la impone la regla del sistema.
   - Categorías **1 y 2**: habilitar; el usuario puede ingresar la tasa manualmente.

### Mapa de campos visibles por categoría

| Grupo de campos | Cat 1 | Cat 2 | Cat 3 | Cat 4 |
|-----------------|:-----:|:-----:|:-----:|:-----:|
| `product_name` | ✅ | — | — | ✅ |
| `product_brand` | ✅ | — | — | ✅ |
| `product_model_year` | ✅ | — | — | — |
| `product_plate` | ✅ | — | — | — |
| `product_chassis` | ✅ | — | — | — |
| `product_item_type` | — | ✅ | — | — |
| `product_karat` | — | ✅ | — | — |
| `product_material_type` | — | ✅ | — | — |
| `product_serial_number` | — | — | — | ✅ |
| `product_item_model` | — | — | — | ✅ |
| `product_details` | ✅ | ✅ | ✅ | ✅ |
| `product_price` | ✅ | ✅ | ✅ | ✅ |
| `interest_rate` (editable) | ✅ | ✅ | ❌ | ❌ |

---

## Ejemplo completo — Vehículo

```http
POST /api/v1/loans
Authorization: Bearer eyJ...
Content-Type: application/json

{
  "client_id": 14,
  "credit_category_id": 1,
  "amount": 5000,
  "interest_rate": 10,
  "product_name": "Corolla",
  "product_brand": "Toyota",
  "product_model_year": 2019,
  "product_plate": "AB123CD",
  "product_chassis": "JT2BK12U9W0123456",
  "product_price": 12000,
  "product_details": "Buen estado, sin rayones."
}
```

---

## Ejemplo completo — Artículo con imagen base64

```http
POST /api/v1/loans
Authorization: Bearer eyJ...
Content-Type: application/json

{
  "client_id": 22,
  "credit_category_id": 4,
  "amount": 300,
  "product_name": "Laptop",
  "product_brand": "Lenovo",
  "product_serial_number": "PF3N2ABC",
  "product_item_model": "ThinkPad E15",
  "product_details": "Con cargador, 16GB RAM.",
  "images_base64": [
    "data:image/jpeg;base64,/9j/4AAQSkZJRgAB..."
  ]
}
```

---

## Ejemplo completo — Joya multipart

```http
POST /api/v1/loans
Authorization: Bearer eyJ...
Content-Type: multipart/form-data

client_id=22
credit_category_id=2
amount=800
product_item_type=Collar
product_karat=18K
product_material_type=Oro
product_details=Con piedras, buen estado
images[]=@foto_collar.jpg
```

---

## Notas para el desarrollador

- **No crear clientes desde esta pantalla.** El flujo móvil solo selecciona
  clientes existentes. Si se necesita crear un cliente nuevo, hacerlo desde la
  sección de clientes y luego volver a crear el préstamo.
- **La sucursal es automática.** No mostrar un selector de sucursal. Mostrar
  el nombre de la sucursal como texto informativo después del 201, usando
  `loan.branch.name`.
- **Categorías y reglas:** llamar `GET /api/v1/loans/categories` al cargar la
  pantalla. El campo `interest_rate_editable` de cada categoría indica si el
  usuario puede modificar la tasa o si es solo lectura.
- **`interest_period`** no se envía — lo define la regla de la categoría.
- **`term_months`** tampoco se envía — lo define la regla de la categoría.
- **`interest_rate` en cat. 3 y 4:** mostrar como texto informativo (no input
  editable). El servidor ignora el valor que se envíe y usa el de la regla.
- **`interest_rate` en cat. 1 y 2:** pre-rellenar con `rule.interest_rate`;
  el usuario puede cambiarlo. El servidor usa el valor enviado.
- El campo `start_date` solo tiene efecto si el usuario tiene el permiso
  `loans.set_start_date` en sus permisos del login. Si no lo tiene, ignorar
  el campo en el formulario.
- Las imágenes pueden enviarse como **multipart** (`images[]`) o en
  **base64** (`images_base64[]`). Máximo 10 imágenes por préstamo, 5 MB cada una.
- Cada préstamo registrado genera automáticamente un **movimiento de caja**
  de tipo egreso por el monto desembolsado.
