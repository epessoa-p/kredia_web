## Plan: Flujo de Caja — Ingresos y Egresos

El sistema actualmente solo registra **ingresos** (pagos) vinculados a sesiones de caja, pero **no registra egresos** (desembolsos de préstamos). No existe tabla centralizada de movimientos de caja. Se necesita crear `cash_movements`, integrar validación de caja abierta en creación de préstamos, y alimentar el módulo de Movimientos con datos reales.

### Diagnóstico Actual

| Acción | ¿Requiere caja abierta? | ¿Registra movimiento de caja? |
|--------|:---:|:---:|
| Crear préstamo (desembolso) | **NO** | **NO** — dinero sale sin registro |
| Pago de interés | SÍ | Solo en `loan_payments` |
| Amortización | SÍ | Solo en `loan_payments` |
| Liquidación | SÍ | Solo en `loan_payments` |
| Pago general | SÍ | Solo en `loan_payments` |

---

### Phase 1: Base de datos

1. **Crear tabla `cash_movements`** — registro unificado de todo flujo de caja
   - `type` ENUM('income','expense'), `category` VARCHAR (loan_disbursement, loan_payment, interest_payment, amortization, liquidation, other)
   - Referencia polimórfica a Loan o LoanPayment
   - FK a `cash_registers`, `cash_register_sessions`, `users`, `companies`
2. **Agregar a `loans`**: columnas `cash_register_id` y `cash_register_session_id` (nullable, para desembolso)

### Phase 2: Modelo y relaciones

3. **Crear** `app/Models/CashMovement.php` con relaciones polimórficas
4. **Actualizar** `app/Models/Loan.php` — agregar `cash_register_id`, `cash_register_session_id` a fillable + relaciones
5. **Actualizar** `app/Models/CashRegisterSession.php` — agregar `cashMovements()` HasMany

### Phase 3: Crear préstamo requiere caja abierta + registra egreso

6. **Modificar** `LoanController::create()` — pasar `$activeCashSession` y `$cashSessionError` a la vista
7. **Modificar** `LoanController::store()` — validar caja abierta, guardar cash_register en loan, crear `CashMovement(expense, loan_disbursement)`

### Phase 4: Pagos registran ingreso

8. **Modificar** `LoanPaymentController::store()` — crear `CashMovement(income, loan_payment)`
9. **Modificar** `LoanActionController::storeInterestPayment()` — crear `CashMovement(income, interest_payment)`
10. **Modificar** `LoanActionController::storeAmortization()` — crear `CashMovement(income, amortization)`
11. **Modificar** `LoanActionController::storeLiquidation()` — crear `CashMovement(income, liquidation)`
12. **Modificar** `LoanController::recordPayment()` — crear `CashMovement(income, loan_payment)`

### Phase 5: Vista — Caja en formulario de crear préstamo

13. **Actualizar** `resources/views/loans/create.blade.php` — banner verde/rojo de estado de caja (mismo patrón que payments/create), deshabilitar submit sin caja abierta

### Phase 6: Vista — Info desembolso en show préstamo

14. **Actualizar** `resources/views/loans/show.blade.php` — mostrar "Desembolsado desde: Caja X · Sesión #N"

### Phase 7: Módulo Movimientos con datos reales

15. **Actualizar** `ReportController::movements()` — reemplazar query de `LoanPayment` por `CashMovement`, filtrar por tipo, categoría, calcular KPIs reales
16. **Actualizar** `resources/views/reports/movements.blade.php` — tabla con columna tipo (ingreso verde / egreso rojo), categoría con labels, filtro por tipo ingreso/egreso

---

### Archivos a crear
- `database/schema_updates_20260414_cash_movements.sql`
- `app/Models/CashMovement.php`

### Archivos a modificar
- `app/Models/Loan.php`, `app/Models/CashRegisterSession.php`
- `app/Http/Controllers/Loans/LoanController.php`
- `app/Http/Controllers/Loans/LoanPaymentController.php`
- `app/Http/Controllers/Loans/LoanActionController.php`
- `app/Http/Controllers/Reports/ReportController.php`
- `resources/views/loans/create.blade.php`
- `resources/views/loans/show.blade.php`
- `resources/views/reports/movements.blade.php`

### Verificación
1. Crear préstamo SIN caja abierta → error, submit deshabilitado
2. Crear préstamo CON caja → loan guardado + CashMovement(expense) creado
3. Registrar pago → LoanPayment + CashMovement(income)
4. Pago interés / Amortización / Liquidación → cada uno crea su CashMovement(income)
5. Movimientos → muestra ambos tipos con KPIs reales (ingresos, egresos, utilidad)
6. Show préstamo → muestra caja de desembolso
7. Cerrar caja → montos cuadran con movimientos registrados

### Decisiones
- Tabla `cash_movements` separada (no se modifica `loan_payments`) para backward compatibility
- Préstamos/pagos anteriores NO tendrán CashMovement retroactivo — solo nuevos registros
- `category` es VARCHAR (no ENUM) para flexibilidad futura (gastos operativos, ajustes)
- Columnas nullable en `loans` para no romper registros existentes
