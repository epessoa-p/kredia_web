<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'id_number',
        'phone',
        'email',
        'address',
        'location_url',
        'location_lat',
        'location_lng',
        'notes',
        'tag',
        'active',
        'collection_route_id',
        'route_position',
        'photo',
    ];

    protected $appends = ['photo_url'];

    protected $casts = [
        'active' => 'boolean',
        'location_lat' => 'float',
        'location_lng' => 'float',
        'deleted_at' => 'datetime',
    ];

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** Ruta de cobranza a la que pertenece el cliente. */
    public function collectionRoute(): BelongsTo
    {
        return $this->belongsTo(CollectionRoute::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }
}
