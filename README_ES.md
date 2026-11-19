# 🏦 Sistema de Préstamos - Multi-Empresa con Roles y Permisos

Un sistema completo de gestión de préstamos desarrollado con **Laravel 12** y **Bootstrap 5**, con soporte para múltiples empresas, control de acceso basado en roles y permisos, y un módulo funcional de préstamos.

## 📋 Características

✅ **Autenticación segura** con contraseñas bcrypt  
✅ **Sistema multi-empresa** - Un usuario puede pertenecer a varias empresas con roles diferentes  
✅ **Roles flexibles**: Super Admin, Admin, Gerente, Cajero, Empleado  
✅ **Control de permisos granular** por rol y empresa  
✅ **Módulo de préstamos** completo con cálculo de amortización  
✅ **Gestión de pagos** de préstamos  
✅ **Dashboard dinámico** con estadísticas según el rol  
✅ **Interfaz amigable** con Bootstrap 5  
✅ **Arquitectura MVC limpia y escalable**

---

## 🚀 Instalación Rápida

### 1️⃣ Requisitos Previos

- **PHP 8.2+**
- **MySQL 5.7+** o MariaDB
- **Composer** (gestor de dependencias de PHP)
- **Node.js** (opcional, para assets)

### 2️⃣ Clonar o Extraer el Proyecto

```bash
# El proyecto está en: D:\Proyectos\Proceso\prestamos
cd D:\Proyectos\Proceso\prestamos
```

### 3️⃣ Instalar Dependencias PHP

```bash
composer install
```

### 4️⃣ Configurar Base de Datos

#### Opción A: Usando las migraciones de Laravel (Recomendado)

```bash
# Crear base de datos MySQL
mysql -u root -p
CREATE DATABASE prestamos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Ejecutar migraciones y seeders
php artisan migrate --seed
```

#### Opción B: Importar el script SQL directamente

```bash
# En la consola de MySQL
mysql -u root -p prestamos_db < database/prestamos_db.sql

# IMPORTANTE: Actualizar las contraseñas en la tabla users después de importar
# Las contraseñas deben ser hashes de bcrypt
```

### 5️⃣ Generar la Clave de Aplicación

```bash
php artisan key:generate
```

### 6️⃣ Iniciar el Servidor

```bash
php artisan serve
```

Acceder a: **http://localhost:8000**

---

## 🔐 Credenciales de Prueba

### Super Administrador (Acceso Global)
```
Email: superadmin@sistema.com
Contraseña: Admin@1234
```

### Administrador de Empresa
```
Email: admin@empresademo.com
Contraseña: Admin@1234
```

---

## 📁 Estructura del Proyecto

```
prestamos/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/LoginController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── Admin/
│   │   │   │   ├── CompanyController.php
│   │   │   │   ├── UserController.php
│   │   │   │   └── RoleController.php
│   │   │   └── Loans/LoanController.php
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php
│   │   │   ├── CheckCompany.php
│   │   │   └── CheckPermission.php
│   │   └── Requests/ (Form Requests para validación)
│   └── Models/
│       ├── User.php
│       ├── Company.php
│       ├── Role.php
│       ├── Permission.php
│       ├── Loan.php
│       ├── LoanType.php
│       └── LoanPayment.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2024_01_01_000001_create_companies_table.php
│   │   ├── 2024_01_01_000002_create_roles_table.php
│   │   ├── 2024_01_01_000003_create_permissions_table.php
│   │   ├── 2024_01_01_000004_create_company_user_table.php
│   │   ├── 2024_01_01_000005_create_permission_tables.php
│   │   ├── 2024_01_01_000006_create_loan_types_table.php
│   │   ├── 2024_01_01_000007_create_loans_table.php
│   │   └── 2024_01_01_000008_create_loan_payments_table.php
│   ├── seeders/
│   │   ├── RoleSeeder.php
│   │   ├── PermissionSeeder.php
│   │   ├── CompanySeeder.php
│   │   └── SuperAdminSeeder.php
│   └── prestamos_db.sql (Script SQL completo)
├── resources/views/
│   ├── layouts/
│   │   ├── base.blade.php
│   │   ├── auth.blade.php
│   │   └── app.blade.php
│   ├── auth/
│   │   ├── login.blade.php
│   │   └── select-company.blade.php
│   ├── dashboard/
│   │   └── index.blade.php
│   ├── admin/
│   │   ├── companies/
│   │   ├── users/
│   │   └── roles/
│   ├── loans/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── create.blade.php
│   └── errors/
│       └── 403.blade.php
├── routes/
│   └── web.php (Todas las rutas)
├── bootstrap/
│   └── app.php (Configuración de middleware)
└── .env (Configuración de entorno)
```

---

## 🗄️ Estructura de Base de Datos

### Tablas Principales

- **users** - Usuarios del sistema
- **companies** - Empresas/Negocios
- **roles** - Roles disponibles (Super Admin, Admin, Gerente, etc.)
- **permissions** - Permisos granulares
- **company_user** (Pivot) - Relación usuario-empresa-rol
- **role_permission** (Pivot) - Relación rol-permiso
- **user_permission** (Pivot) - Permisos adicionales por usuario/empresa
- **loan_types** - Tipos de préstamos por empresa
- **loans** - Registro de préstamos
- **loan_payments** - Pagos de préstamos

---

## 👥 Roles y Permisos

### Roles

| Rol | Descripción | Permisos |
|-----|-------------|----------|
| **Super Admin** | Acceso completo a todo el sistema | Todos los permisos |
| **Admin** | Administrador de empresa específica | Gestión de usuarios, préstamos, reportes |
| **Gerente** | Gerente de préstamos | Ver/Crear/Aprobar préstamos, reportes |
| **Cajero** | Operador de caja | Ver préstamos, registrar pagos |
| **Empleado** | Usuario básico | Solo lectura de préstamos |

### Módulos de Permisos

- **companies** - Ver, Crear, Editar, Eliminar empresas
- **users** - Ver, Crear, Editar, Eliminar usuarios
- **roles** - Ver, Crear, Editar, Eliminar roles
- **loans** - Ver, Crear, Editar, Aprobar, Eliminar préstamos
- **payments** - Ver pagos, Registrar pagos
- **reports** - Ver reportes

---

## 🔧 Configuración del .env

```env
APP_NAME="Sistema de Préstamos"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prestamos_db
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
...
```

---

## 🛠️ Comandos Útiles

```bash
# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed

# Crear un nuevo usuario super admin
php artisan tinker
> $user = new App\Models\User([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('password123'),
    'is_super_admin' => true,
    'active' => true
]);
> $user->save();

# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Ver todas las rutas
php artisan route:list
```

---

## 📊 Flujo de Préstamos

1. **Crear Préstamo** - El usuario crea un nuevo préstamo (estado: "pending")
2. **Aprobar Préstamo** - Un usuario con permisos aprueba el préstamo (estado: "approved")
3. **Activar Préstamo** - Se activa para comenzar el plazo (estado: "active")
4. **Registrar Pagos** - Se registran los pagos según se reciben (estado: sigue "active")
5. **Finalizar** - Cuando se pague todo, se marca como "finished"

---

## 🔒 Seguridad

- ✅ Autenticación con contraseñas bcrypt
- ✅ Middleware de autorización por rol y permiso
- ✅ Validación de requests con Form Requests
- ✅ CSRF protection
- ✅ Control de acceso basado en empresa (ABAC)
- ✅ Super Admin para gestión global

---

## ⚡ Próximas Mejoras (Opcional)

- [ ] Exportar reportes a PDF/Excel
- [ ] Módulo de cobranzas con alertas de vencimiento
- [ ] Sistema de notificaciones por email
- [ ] API REST para integración
- [ ] Gráficos y dashboards avanzados
- [ ] Auditoría de cambios
- [ ] Autenticación de dos factores (2FA)

---

## 📞 Soporte

Para cambios de contraseña o problemas de acceso, usar la consola de Laravel:

```bash
php artisan tinker

> $user = App\Models\User::where('email', 'superadmin@sistema.com')->first();
> $user->update(['password' => Hash::make('NewPassword123')]);
> exit
```

---

## ✅ Checklist de Despliegue

- [ ] Base de datos creada y migraciones ejecutadas
- [ ] Seeders ejecutados (roles, permisos, datos iniciales)
- [ ] .env configurado correctamente
- [ ] APP_KEY generada
- [ ] Permisos de carpetas configurados (storage/, bootstrap/cache/)
- [ ] Session driver configurado (file, database, cache)
- [ ] Servidor web apuntando a la carpeta `public/`
- [ ] Probar login con credenciales de demostración
- [ ] Verificar roles y permisos

---

## 📄 Licencia

Este proyecto es de demostración educativa. Úsalo libremente para aprender.

---

**Proyecto creado con ❤️ usando Laravel 12 y Bootstrap 5**
