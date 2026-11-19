<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Campos de devolución en la tabla sales ──────────────────────
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable()->after('sale_date');
            $table->foreignId('returned_by')->nullable()->after('returned_at')
                  ->constrained('users')->nullOnDelete();
            $table->string('return_reason', 255)->nullable()->after('returned_by');
            $table->text('return_notes')->nullable()->after('return_reason');
        });

        // ── 2. Ampliar ENUM status de sale_installments para incluir ANULADA ─
        DB::statement("ALTER TABLE sale_installments MODIFY COLUMN status ENUM('PENDIENTE','PAGADA','VENCIDA','ANULADA') NOT NULL DEFAULT 'PENDIENTE'");
    }

    public function down(): void
    {
        // Revertir ENUM
        DB::statement("ALTER TABLE sale_installments MODIFY COLUMN status ENUM('PENDIENTE','PAGADA','VENCIDA') NOT NULL DEFAULT 'PENDIENTE'");

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['returned_by']);
            $table->dropColumn(['returned_at', 'returned_by', 'return_reason', 'return_notes']);
        });
    }
};
