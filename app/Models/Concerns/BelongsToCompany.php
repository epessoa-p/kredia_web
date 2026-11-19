<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de datos de un tenant: se filtra automáticamente por la empresa activa
 * y, al crearse sin company_id, toma la empresa activa.
 *
 * Para saltar el filtro de forma puntual: Model::withoutCompanyScope()->... o ->forCompany($id).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $companyId = app(Tenancy::class)->id();
                if ($companyId) {
                    $model->company_id = $companyId;
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class);
    }

    /** Filtra por una empresa concreta, ignorando la empresa activa. */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class)
            ->where($this->qualifyColumn('company_id'), $companyId);
    }
}
