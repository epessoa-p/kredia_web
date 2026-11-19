<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suscripción: vincula UNA empresa con UN plan (1:1). GLOBAL (sin scope de empresa):
 * si se filtrara por empresa, la empresa no podría comprobar su propio acceso.
 *
 * El estado efectivo se DERIVA de las fechas: una suscripción vencida corta sola.
 *  - trial  vigente                → lee y escribe
 *  - active vigente (o sin fecha)  → lee y escribe
 *  - vencida dentro de grace_days  → solo lectura
 *  - vencida fuera de gracia       → bloqueada
 *  - suspended / cancelled         → bloqueada
 */
class Subscription extends Model
{
    public const STATUSES = [
        'trial' => 'Prueba',
        'active' => 'Activa',
        'past_due' => 'Pago pendiente',
        'suspended' => 'Suspendida',
        'cancelled' => 'Cancelada',
    ];

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'trial_ends_at', 'current_period_end', 'grace_days', 'notes', 'created_by',
        'max_users_override', 'max_branches_override', 'max_products_override', 'max_loans_override', 'features_override',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_days' => 'integer',
        'max_users_override' => 'integer',
        'max_branches_override' => 'integer',
        'max_products_override' => 'integer',
        'max_loans_override' => 'integer',
        'features_override' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Límites y módulos efectivos (override de la empresa ?? plan) ──────────

    public function effectiveLimitFor(string $key): ?int
    {
        $override = $this->{"max_{$key}_override"} ?? null;

        return $override ?? $this->plan?->limitFor($key);
    }

    public function effectiveFeatures(): array
    {
        return $this->features_override ?? ($this->plan?->features ?? []);
    }

    // ── Estado derivado de fechas ───────────────────────────────────────────

    public function onTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isCurrent(): bool
    {
        return $this->status === 'active'
            && ($this->current_period_end === null || $this->current_period_end->isFuture());
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, ['suspended', 'cancelled'], true);
    }

    /** Fin del periodo (prueba o pagado) a partir del cual corre la gracia. */
    public function periodEndsAt(): ?Carbon
    {
        return $this->status === 'trial' ? $this->trial_ends_at : $this->current_period_end;
    }

    public function graceEndsAt(): ?Carbon
    {
        return $this->periodEndsAt()?->copy()->addDays((int) $this->grace_days);
    }

    public function inGrace(): bool
    {
        if ($this->isBlocked() || $this->onTrial() || $this->isCurrent()) {
            return false;
        }

        return (bool) $this->graceEndsAt()?->isFuture();
    }

    public function allowsWrite(): bool
    {
        return !$this->isBlocked() && ($this->onTrial() || $this->isCurrent());
    }

    public function allowsRead(): bool
    {
        return $this->allowsWrite() || $this->inGrace();
    }

    /** Estado efectivo legible: trial | active | grace | expired | suspended | cancelled. */
    public function effectiveStatus(): string
    {
        return match (true) {
            $this->isBlocked() => $this->status,
            $this->onTrial() => 'trial',
            $this->isCurrent() => 'active',
            $this->inGrace() => 'grace',
            default => 'expired',
        };
    }

    /** Días que faltan para el fin del periodo (null = sin vencimiento). */
    public function daysLeft(): ?int
    {
        $end = $this->periodEndsAt();
        if (!$end) {
            return null;
        }

        return $end->isFuture() ? (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay()) : 0;
    }

    /** Resumen para la API y la UI. */
    public function toStatusArray(): array
    {
        return [
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'allows_write' => $this->allowsWrite(),
            'allows_read' => $this->allowsRead(),
            'period_ends_at' => $this->periodEndsAt()?->toIso8601String(),
            'grace_ends_at' => $this->graceEndsAt()?->toIso8601String(),
            'days_left' => $this->daysLeft(),
        ];
    }
}
