<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planes (producto comercial del operador) y suscripciones (1:1 empresa → plan).
 * Ver ARQUITECTURA-EMPRESAS-PLANES-PERMISOS.md §3.3, §7 y §8.
 * Producción: database/prod-scripts/002_plans_subscriptions.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');
                $table->unsignedSmallInteger('trial_days')->default(0);
                $table->unsignedInteger('max_users')->nullable();      // NULL = ilimitado
                $table->unsignedInteger('max_branches')->nullable();
                $table->unsignedInteger('max_products')->nullable();
                $table->unsignedInteger('max_loans')->nullable();      // préstamos vigentes
                $table->json('features')->nullable();                  // módulos incluidos
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans');
                $table->enum('status', ['trial', 'active', 'past_due', 'suspended', 'cancelled'])->default('trial');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_end')->nullable();   // NULL con status active = sin vencimiento
                $table->unsignedSmallInteger('grace_days')->default(3);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('max_users_override')->nullable();
                $table->unsignedInteger('max_branches_override')->nullable();
                $table->unsignedInteger('max_products_override')->nullable();
                $table->unsignedInteger('max_loans_override')->nullable();
                $table->json('features_override')->nullable();
                $table->timestamps();
                $table->index('status');
            });
        }

        $this->seedPlans();
        $this->subscribeExistingCompanies();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }

    private function seedPlans(): void
    {
        $now = now();
        $plans = [
            ['slug' => 'basico', 'name' => 'Básico', 'price' => 0, 'max_users' => 3, 'max_branches' => 1, 'max_products' => 500, 'max_loans' => 300, 'sort_order' => 1,
                'features' => ['pawn', 'mobile_app'],
                'description' => 'Empeño con 1 sucursal y app móvil.'],
            ['slug' => 'pro', 'name' => 'Pro', 'price' => 0, 'max_users' => 10, 'max_branches' => 3, 'max_products' => 5000, 'max_loans' => null, 'sort_order' => 2,
                'features' => ['pawn', 'installments', 'collections', 'inventory', 'sales', 'purchases', 'catalogs', 'mobile_app'],
                'description' => 'Empeño + cuotas, cobranza, inventario y ventas.'],
            ['slug' => 'empresa', 'name' => 'Empresa', 'price' => 0, 'max_users' => null, 'max_branches' => null, 'max_products' => null, 'max_loans' => null, 'sort_order' => 3,
                'features' => ['pawn', 'installments', 'collections', 'inventory', 'sales', 'purchases', 'catalogs', 'capital', 'notifications', 'mobile_app'],
                'description' => 'Todo incluido y sin límites.'],
        ];

        foreach ($plans as $plan) {
            if (DB::table('plans')->where('slug', $plan['slug'])->exists()) {
                continue;
            }
            DB::table('plans')->insert(array_merge($plan, [
                'features' => json_encode($plan['features']),
                'billing_period' => 'monthly',
                'trial_days' => 15,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /** Las empresas que ya operan quedan en el plan Empresa, activas y sin vencimiento. */
    private function subscribeExistingCompanies(): void
    {
        $planId = DB::table('plans')->where('slug', 'empresa')->value('id');
        if (!$planId) {
            return;
        }

        DB::statement("
            INSERT INTO subscriptions (company_id, plan_id, status, grace_days, notes, created_at, updated_at)
            SELECT c.id, ?, 'active', 3, 'Suscripción inicial (empresa existente antes del SaaS).', NOW(), NOW()
            FROM companies c
            WHERE NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.company_id = c.id)
        ", [$planId]);
    }
};
