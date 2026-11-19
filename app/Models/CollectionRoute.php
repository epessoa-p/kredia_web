<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ruta de cobranza: un cobrador recorre a sus clientes en un orden fijo. */
class CollectionRoute extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'branch_id', 'collector_id', 'name', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'collector_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'collection_route_id')->orderBy('route_position')->orderBy('name');
    }
}
