<?php

namespace App\Observers;

use App\Models\Loan;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanObserver
{
    /**
     * Al crearse un préstamo, notifica por push a los usuarios de la misma
     * empresa (excepto a quien lo creó). El envío se difiere para no añadir
     * latencia a la petición que crea el préstamo.
     */
    public function created(Loan $loan): void
    {
        $companyId = $loan->company_id;
        if (!$companyId) {
            return;
        }

        $userIds = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('active', true)
            // PRUEBA (Opción B): se notifica también al creador. Para producción,
            // descomenta la línea siguiente para excluirlo:
            // ->when($loan->created_by, fn ($q) => $q->where('user_id', '!=', $loan->created_by))
            ->pluck('user_id')
            ->all();

        if (empty($userIds)) {
            return;
        }

        $clientName = $loan->client?->name ?? 'un cliente';
        $amount = number_format((float) $loan->amount, 2);
        $title = 'Nuevo préstamo registrado';
        $body = "Se registró un préstamo de {$clientName} por {$amount}.";
        $data = ['type' => 'loan_created', 'loan_id' => $loan->id];

        defer(function () use ($userIds, $title, $body, $data) {
            try {
                app(PushNotificationService::class)->sendToUsers($userIds, $title, $body, $data);
            } catch (\Throwable $e) {
                Log::error('Error enviando notificación de préstamo creado', [
                    'loan_id' => $data['loan_id'],
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }
}
