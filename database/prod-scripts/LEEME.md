# Scripts para producción

Ejecútalos **en orden**, sobre la base de producción. Todos son **reejecutables**: si se cortan o
se corren dos veces, no fallan ni duplican datos. Cada uno registra su migración en la tabla
`migrations`, así que un futuro `php artisan migrate` no los repetirá.

| # | Archivo | Qué hace |
|---|---|---|
| 1 | `001_roles_company_id.sql` | Roles por empresa (`roles.company_id`). Los roles creados desde Cargos pasan a ser de su empresa. |
| 2 | `002_plans_subscriptions.sql` | Tablas `plans` y `subscriptions`, 3 planes (Básico, Pro, Empresa). **Las empresas existentes quedan en el plan Empresa, activas y sin vencimiento.** |
| 3 | `003_installment_loans_collections.sql` | Préstamos en cuotas, plan de pagos, garantes, rutas de cobranza, GPS y anulación de pagos, bitácora de auditoría, tope de tasa por empresa y 4 permisos nuevos. |

## Pasos del despliegue

1. **Respaldo** de la base: `mysqldump --routines --triggers <base> > respaldo.sql`
2. Subir el código.
3. Ejecutar los scripts: `mysql <base> < 001_roles_company_id.sql` (y luego 002, 003).
4. `php artisan optimize:clear`
5. **Mover los documentos de clientes al almacenamiento privado** (una sola vez; se puede repetir):
   `php artisan clients:move-documents-private --dry-run` → revisar → sin `--dry-run`.
6. Verificar que el cron del servidor ejecuta `php artisan schedule:run` cada minuto
   (mora diaria 01:00 y recordatorio de cobranza 07:00).
7. En el panel del superadmin → **Empresas → Suscripción**: ajustar plan, fechas y límites de cada empresa.

> El script 003 crea procedimientos temporales (`kredia_add_column`, etc.) y los borra al final.
> El usuario de MySQL necesita permiso `CREATE ROUTINE` para ejecutarlo.
