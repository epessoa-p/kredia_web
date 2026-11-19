<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('loans', 'deleted_at')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('loan_interest_payments', 'deleted_at')) {
            Schema::table('loan_interest_payments', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('loan_interest_payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
