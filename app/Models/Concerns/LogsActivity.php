<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/**
 * Registra automáticamente en la bitácora la creación, los cambios (antes/después)
 * y la eliminación del modelo. Los campos de $auditExclude no se guardan.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => ActivityLog::record($model, 'created'));

        static::updated(function ($model) {
            $exclude = array_merge(['updated_at', 'password', 'remember_token'], $model->auditExclude ?? []);
            $changes = collect($model->getChanges())->except($exclude);
            if ($changes->isEmpty()) {
                return;
            }

            $before = collect($model->getOriginal())->only($changes->keys());
            ActivityLog::record($model, 'updated', null, [
                'before' => $before->all(),
                'after' => $changes->all(),
            ]);
        });

        static::deleted(fn ($model) => ActivityLog::record($model, 'deleted'));
    }
}
