<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Catalog extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'created_by',
        'title',
        'description',
        'slug',
        'cover_color',
        'logo_path',
        'show_prices',
        'show_contact',
        'contact_phone',
        'contact_whatsapp',
        'contact_email',
        'active',
        'expires_at',
    ];

    protected $casts = [
        'show_prices' => 'boolean',
        'show_contact' => 'boolean',
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Sucursal del catálogo (null = todas las sucursales). */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class)->orderBy('sort_order');
    }

    public function isAccessible(): bool
    {
        if (!$this->active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function getPublicUrl(): string
    {
        return url('/c/' . $this->slug);
    }
}
