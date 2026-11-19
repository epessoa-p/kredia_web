<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('loan_collaterals', 'stored_at')) {
            Schema::table('loan_collaterals', function (Blueprint $table) {
                $table->dateTime('stored_at')->nullable()->after('retained_at');
            });
        }

        // Expandir el ENUM status para incluir 'stored' (MySQL).
        DB::statement("ALTER TABLE `loan_collaterals`
            MODIFY COLUMN `status` ENUM('retained','stored','sellable','released','sold')
            NOT NULL DEFAULT 'retained'");
    }

    public function down(): void
    {
        if (Schema::hasColumn('loan_collaterals', 'stored_at')) {
            Schema::table('loan_collaterals', function (Blueprint $table) {
                $table->dropColumn('stored_at');
            });
        }
    }
};
