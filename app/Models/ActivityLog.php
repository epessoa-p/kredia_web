<?php

namespace App\Models;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Bitácora de auditoría: quién hizo qué, sobre qué registro y cuándo.
 * Se escribe con ActivityLog::record(...) o automáticamente con el trait Concerns\LogsActivity.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['company_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip'];

    protected $casts = ['properties' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(Model $subject, string $action, ?string $description = null, array $properties = []): self
    {
        $request = app()->bound('request') ? request() : null;

        return static::create([
            'company_id' => $subject->getAttribute('company_id') ?? app(Tenancy::class)->id(),
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip' => $request?->ip(),
        ]);
    }
}
