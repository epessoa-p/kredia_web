<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->string('fcm_token', 255)->nullable()->after('name');
            $table->string('platform', 20)->nullable()->after('fcm_token');

            $table->index('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropIndex(['fcm_token']);
            $table->dropColumn(['fcm_token', 'platform']);
        });
    }
};
