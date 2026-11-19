<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles por empresa: company_id NULL = rol de sistema; con valor = rol propio de esa empresa.
 * Producción: database/prod-scripts/001_roles_company_id.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('roles', 'company_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')
                    ->constrained('companies')->cascadeOnDelete();
            });
        }

        // Los roles creados por cargos pasan a ser de la empresa del cargo
        // (solo si todos los cargos que lo usan son de UNA misma empresa).
        DB::statement("
            UPDATE roles r
            JOIN (
                SELECT role_id, MIN(company_id) AS company_id
                FROM cargos
                WHERE role_id IS NOT NULL
                GROUP BY role_id
                HAVING COUNT(DISTINCT company_id) = 1
            ) c ON c.role_id = r.id
            SET r.company_id = c.company_id
            WHERE r.company_id IS NULL
              AND r.slug NOT IN ('super_admin','admin','manager','cashier','employee')
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'company_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
