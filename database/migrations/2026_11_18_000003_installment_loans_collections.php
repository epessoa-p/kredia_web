<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 · Negocio de préstamos:
 *  - Préstamos en CUOTAS (diario/semanal/quincenal/mensual; flat, francés, alemán) junto al empeño.
 *  - Plan de pagos (loan_installments) y reparto de cada cobro (loan_payment_allocations).
 *  - Garantes, cobradores y rutas de cobranza, GPS del cobro, anulación de pagos, refinanciación.
 *  - Bitácora de auditoría y tope de tasa por empresa.
 * Producción: database/prod-scripts/003_installment_loans_collections.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Producto de crédito: tipo y forma de pago ─────────────────────
        Schema::table('credit_category_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_category_rules', 'loan_kind')) {
                $table->enum('loan_kind', ['pawn', 'installment'])->default('pawn')->after('name');
            }
            if (!Schema::hasColumn('credit_category_rules', 'installment_method')) {
                $table->enum('installment_method', ['flat', 'french', 'german'])->nullable()->after('loan_kind');
            }
            if (!Schema::hasColumn('credit_category_rules', 'payment_frequency')) {
                $table->enum('payment_frequency', ['daily', 'weekly', 'biweekly', 'monthly'])->nullable()->after('installment_method');
            }
            if (!Schema::hasColumn('credit_category_rules', 'skip_sundays')) {
                $table->boolean('skip_sundays')->default(true)->after('payment_frequency');
            }
        });

        // ── Préstamo ──────────────────────────────────────────────────────
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'loan_kind')) {
                $table->enum('loan_kind', ['pawn', 'installment'])->default('pawn')->after('credit_category_rule_id');
            }
            if (!Schema::hasColumn('loans', 'installment_method')) {
                $table->enum('installment_method', ['flat', 'french', 'german'])->nullable()->after('loan_kind');
            }
            if (!Schema::hasColumn('loans', 'payment_frequency')) {
                $table->enum('payment_frequency', ['daily', 'weekly', 'biweekly', 'monthly'])->nullable()->after('installment_method');
            }
            if (!Schema::hasColumn('loans', 'installments_count')) {
                $table->unsignedSmallInteger('installments_count')->nullable()->after('payment_frequency');
            }
            if (!Schema::hasColumn('loans', 'installment_amount')) {
                $table->decimal('installment_amount', 15, 2)->nullable()->after('installments_count');
            }
            if (!Schema::hasColumn('loans', 'skip_sundays')) {
                $table->boolean('skip_sundays')->default(false)->after('installment_amount');
            }
            if (!Schema::hasColumn('loans', 'collector_id')) {
                $table->foreignId('collector_id')->nullable()->after('created_by')->constrained('personals')->nullOnDelete();
            }
            if (!Schema::hasColumn('loans', 'parent_loan_id')) {
                $table->foreignId('parent_loan_id')->nullable()->after('collector_id')->constrained('loans')->nullOnDelete();
            }
            if (!Schema::hasColumn('loans', 'refinanced_at')) {
                $table->timestamp('refinanced_at')->nullable()->after('parent_loan_id');
            }
        });

        // ── Plan de pagos ─────────────────────────────────────────────────
        if (!Schema::hasTable('loan_installments')) {
            Schema::create('loan_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
                $table->unsignedSmallInteger('number');
                $table->date('due_date');
                $table->decimal('capital', 15, 2);
                $table->decimal('interest', 15, 2);
                $table->decimal('amount', 15, 2);                       // capital + interés
                $table->decimal('penalty', 15, 2)->default(0);          // mora aplicada
                $table->decimal('paid_capital', 15, 2)->default(0);
                $table->decimal('paid_interest', 15, 2)->default(0);
                $table->decimal('paid_penalty', 15, 2)->default(0);
                $table->enum('status', ['pending', 'partial', 'paid'])->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->unique(['loan_id', 'number']);
                $table->index(['loan_id', 'status']);
                $table->index('due_date');
            });
        }

        // ── Pagos: tipo cuota, GPS, anulación, idempotencia ───────────────
        DB::statement("ALTER TABLE loan_payments MODIFY payment_type ENUM('interest','capital','mixed','penalty','installment','refinance') NOT NULL");
        Schema::table('loan_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_payments', 'penalty')) {
                $table->decimal('penalty', 15, 2)->nullable()->after('interest');
            }
            if (!Schema::hasColumn('loan_payments', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('notes');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('loan_payments', 'request_id')) {
                $table->string('request_id', 64)->nullable()->after('longitude');
                $table->unique(['loan_id', 'request_id']);
            }
            if (!Schema::hasColumn('loan_payments', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('request_id');
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
                $table->string('void_reason', 500)->nullable()->after('voided_by');
            }
        });

        if (!Schema::hasTable('loan_payment_allocations')) {
            Schema::create('loan_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_payment_id')->constrained('loan_payments')->cascadeOnDelete();
                $table->foreignId('loan_installment_id')->constrained('loan_installments')->cascadeOnDelete();
                $table->decimal('capital', 15, 2)->default(0);
                $table->decimal('interest', 15, 2)->default(0);
                $table->decimal('penalty', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // ── Garantes ──────────────────────────────────────────────────────
        if (!Schema::hasTable('loan_guarantors')) {
            Schema::create('loan_guarantors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete(); // garante que ya es cliente
                $table->string('name');
                $table->string('id_number', 50)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('address')->nullable();
                $table->string('relationship', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ── Rutas de cobranza ─────────────────────────────────────────────
        if (!Schema::hasTable('collection_routes')) {
            Schema::create('collection_routes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('collector_id')->nullable()->constrained('personals')->nullOnDelete();
                $table->string('name');
                $table->string('description', 500)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'collection_route_id')) {
                $table->foreignId('collection_route_id')->nullable()->constrained('collection_routes')->nullOnDelete();
                $table->unsignedSmallInteger('route_position')->nullable();
            }
        });

        // ── Auditoría ─────────────────────────────────────────────────────
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 50);                 // created, updated, deleted, voided, refinanced…
                $table->string('subject_type', 150);
                $table->unsignedBigInteger('subject_id');
                $table->string('description', 500)->nullable();
                $table->json('properties')->nullable();       // cambios (antes/después)
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['subject_type', 'subject_id']);
            });
        }

        // ── Tope de tasa por empresa ──────────────────────────────────────
        if (!Schema::hasColumn('companies', 'max_interest_rate')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->decimal('max_interest_rate', 8, 2)->nullable()->after('currency_symbol');
            });
        }

        $this->seedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        if (Schema::hasColumn('clients', 'collection_route_id')) {
            Schema::table('clients', fn (Blueprint $t) => $t->dropConstrainedForeignId('collection_route_id'));
            Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('route_position'));
        }
        Schema::dropIfExists('collection_routes');
        Schema::dropIfExists('loan_guarantors');
        Schema::dropIfExists('loan_payment_allocations');
        Schema::dropIfExists('loan_installments');
    }

    /** Permisos nuevos y a qué roles existentes se otorgan (para no quitar acceso a nadie). */
    private function seedPermissions(): void
    {
        $now = now();
        $new = [
            ['slug' => 'collections.view', 'name' => 'Ver cobranza y rutas', 'module' => 'collections', 'grant_if' => 'loans.view'],
            ['slug' => 'collections.manage', 'name' => 'Gestionar rutas y cobradores', 'module' => 'collections', 'grant_if' => 'loans.edit'],
            ['slug' => 'payments.void', 'name' => 'Anular pagos de préstamos', 'module' => 'payments', 'grant_if' => 'loans.delete'],
            ['slug' => 'loans.refinance', 'name' => 'Refinanciar préstamos', 'module' => 'loans', 'grant_if' => 'loans.edit'],
        ];

        foreach ($new as $perm) {
            if (!DB::table('permissions')->where('slug', $perm['slug'])->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $perm['slug'], 'name' => $perm['name'], 'module' => $perm['module'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::statement("
                INSERT IGNORE INTO role_permission (role_id, permission_id)
                SELECT rp.role_id, (SELECT id FROM permissions WHERE slug = ?)
                FROM role_permission rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE p.slug = ?
            ", [$perm['slug'], $perm['grant_if']]);
        }
    }
};
