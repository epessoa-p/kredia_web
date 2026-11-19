<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * WHERE company_id = <empresa activa>. Sin empresa activa (superadmin, consola, público) no filtra.
 * Si el modelo define $includeGlobalCompanyRows = true, también incluye filas con company_id NULL
 * (p. ej. plantillas globales del sistema).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(Tenancy::class)->id();
        if ($companyId === null) {
            return;
        }

        $column = $model->qualifyColumn('company_id');

        if (property_exists($model, 'includeGlobalCompanyRows') && $model->includeGlobalCompanyRows) {
            $builder->where(fn (Builder $q) => $q->where($column, $companyId)->orWhereNull($column));

            return;
        }

        $builder->where($column, $companyId);
    }
}
