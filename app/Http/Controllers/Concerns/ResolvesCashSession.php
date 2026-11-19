<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Personal;

/**
 * Resuelve la caja abierta del usuario logueado (vía su Personal → cajas
 * asignadas → sesión sin cerrar). Compartido entre controladores.
 */
trait ResolvesCashSession
{
    protected function resolveOpenCashSession(int $companyId): ?CashRegisterSession
    {
        $personalRecord = Personal::where('user_id', auth()->id())->first();
        if (!$personalRecord) {
            return null;
        }

        $registerIds = CashRegister::where('assigned_personal_id', $personalRecord->id)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) {
            return null;
        }

        return CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    protected function getCashSessionError(int $companyId): ?string
    {
        $personalRecord = Personal::where('user_id', auth()->id())->first();
        if (!$personalRecord) {
            return 'No tienes un registro de personal asociado. Contacta al administrador.';
        }

        $registerIds = CashRegister::where('assigned_personal_id', $personalRecord->id)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) {
            return 'No tienes una caja asignada. Contacta al administrador.';
        }

        $hasOpen = CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->exists();

        if (!$hasOpen) {
            return 'No hay ninguna caja abierta. Debes aperturar una caja antes de registrar ventas.';
        }

        return null;
    }
}
