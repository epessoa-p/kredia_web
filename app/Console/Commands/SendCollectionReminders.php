<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Personal;
use App\Services\Installments\InstallmentLoanService;
use App\Services\PushNotificationService;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Recordatorio diario de cobranza (módulo de plan "notifications").
 * Envía a cada cobrador una notificación push con cuántos clientes y cuánto debe cobrar hoy.
 * Pensado para enchufar después WhatsApp/SMS a los clientes con la misma lista.
 */
class SendCollectionReminders extends Command
{
    protected $signature = 'collections:remind {--dry-run : Solo mostrar, sin enviar}';

    protected $description = 'Avisa a cada cobrador lo que tiene que cobrar hoy (push).';

    public function handle(InstallmentLoanService $service, PushNotificationService $push, Tenancy $tenancy): int
    {
        $sent = 0;

        Company::where('active', true)->with('subscription.plan')->get()
            ->filter(fn (Company $c) => $c->subscriptionAllowsWrite() && $c->planAllows('notifications') && $c->planAllows('collections'))
            ->each(function (Company $company) use ($service, $push, $tenancy, &$sent) {
                $tenancy->runAs($company->id, function () use ($company, $service, $push, &$sent) {
                    foreach (Personal::where('active', true)->whereNotNull('user_id')->get() as $collector) {
                        $items = $service->collectionList($collector->id);
                        if ($items->isEmpty()) {
                            continue;
                        }

                        $total = number_format($items->sum('due_today'), 2);
                        $late = $items->where('overdue_count', '>', 0)->count();
                        $body = "Tienes {$items->count()} clientes por cobrar hoy ({$company->currency_symbol} {$total})"
                            . ($late ? ", {$late} con atraso." : '.');

                        $this->line("  {$company->name} · {$collector->full_name}: {$body}");
                        if (!$this->option('dry-run')) {
                            $sent += $push->sendToUsers([$collector->user_id], 'Cobranza del día', $body, ['type' => 'collections_today']);
                        }
                    }
                });
            });

        $this->info("Notificaciones enviadas: {$sent}");

        return self::SUCCESS;
    }
}
