# Estándar visual de Kredia (web)

Todo el estilo vive en **`public/css/kredia-ui.css`** (se carga en `layouts/base.blade.php`).
Los componentes Blade están en **`resources/views/components/`**.

## 1. Tamaños de letra: SOLO la escala

| Clase | Variable | Tamaño | Uso |
|---|---|---|---|
| `.fs-xs` | `--fs-xs` | .72rem | etiquetas, encabezados de tabla, chips, ayudas |
| `.fs-sm` | `--fs-sm` | .8rem | tablas, textos secundarios, botones |
| `.fs-base` | `--fs-base` | .875rem | texto normal, formularios |
| `.fs-md` | `--fs-md` | 1rem | títulos de tarjeta / sección |
| `.fs-lg` | `--fs-lg` | 1.25rem | título de página |
| `.fs-xl` | `--fs-xl` | 1.6rem | cifras grandes (KPI) |

- **Prohibido** `style="font-size:..."` y `font-size:` en `@push('styles')` dentro de las vistas.
  En CSS de la vista se usa `font-size: var(--fs-sm)` (nunca un número).
- Excepción: **PDF y recibos térmicos** (`*/pdf.blade.php`, `receipt-pdf`, `contracts/pdf`) usan medidas fijas.
- `h1..h6` y `.fs-1..fs-6` de Bootstrap ya están mapeados a la escala dentro de `.compact-page`.

## 2. Botones: un solo diseño

```blade
<x-btn icon="plus-lg" :href="route('clients.create')">Nuevo cliente</x-btn>      {{-- crear (primary) --}}
<x-btn type="submit" icon="check-lg">Guardar</x-btn>                               {{-- guardar --}}
<x-btn variant="secondary" icon="arrow-left" :href="route('clients.index')">Volver</x-btn>
<x-btn variant="secondary" :href="...">Cancelar</x-btn>
<x-btn variant="danger" type="submit" icon="trash">Eliminar</x-btn>
<x-btn variant="success" type="submit" icon="cash">Cobrar</x-btn>
<x-btn variant="module" ...>Acción principal con el color del módulo</x-btn>
<x-icon-btn icon="pencil" label="Editar" :href="route('clients.edit', $c)" />       {{-- solo ícono --}}
<x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
```

| Acción | Variante |
|---|---|
| Crear / Nuevo / Guardar / Buscar / Filtrar | `primary` |
| Volver / Cancelar / Limpiar / Exportar / filtros | `secondary` |
| Eliminar / Anular | `danger` (o `outline-danger` si es secundaria) |
| Cobrar / Confirmar / Aperturar caja | `success` |
| Acciones discretas en tablas | `<x-icon-btn>` |

- Tamaño por defecto `sm` (32px). `size="md"` (38px) solo para el botón principal de formularios largos o modales.
- **No** usar `rounded-pill`, `fw-bold`, `px-4`, `px-5`, gradientes ni clases de botón propias
  (`sv-mode-btn`, `cs-quick-btn`, etc.). Selectores de modo → `.k-seg` con `.btn`.
- Si una clase propia la usa JavaScript, se conserva **solo como gancho** (sin estilos).
- Atributos extra (`data-*`, `onclick`, `form`, `disabled`, `id`, `name`, `value`) pasan tal cual al componente.

## 3. Encabezado de página

```blade
<x-page-header title="Clientes" icon="people" :count="$clients->total()">
    <x-btn icon="plus-lg" :href="route('clients.create')">Nuevo cliente</x-btn>
</x-page-header>

<x-page-header title="Editar cliente" icon="pencil-square" :back="route('clients.index')" />
```

El color del ícono sale automáticamente del módulo (`config/menu.php`).

## 4. Colores por módulo

| Módulo | Color | Clase |
|---|---|---|
| Inicio | `#2563eb` | `.mod-dashboard` |
| Préstamos / categorías | `#7c3aed` | `.mod-loans` |
| Cobranza / pagos | `#db2777` | `.mod-collections` |
| Clientes | `#0891b2` | `.mod-clients` |
| Caja / movimientos | `#16a34a` | `.mod-cash` |
| Inventario / productos / almacenes | `#ea580c` | `.mod-inventory` |
| Ventas | `#ca8a04` | `.mod-sales` |
| Compras | `#dc2626` | `.mod-purchases` |
| Catálogos | `#0d9488` | `.mod-catalogs` |
| Reportes | `#4f46e5` | `.mod-reports` |
| Capital | `#059669` | `.mod-capital` |
| Administración | `#475569` | `.mod-admin` |

Dentro de una página: `var(--mod-color)`, `var(--mod-bg)`, `.text-mod`, `.bg-mod-soft`.

## 5. Estados

```blade
<x-status-badge :status="$loan->status" />          {{-- ACTIVO, EN_MORA, INCUMPLIDO, FINALIZADO… --}}
<x-status-badge tone="warning">Pendiente</x-status-badge>
```

## 6. Menú

Se define una sola vez en `config/menu.php` (escritorio y celular usan `layouts/partials/sidebar-menu.blade.php`).
Para agregar una opción: añadir el ítem con `route`, `icon`, `module` y `permission`.
