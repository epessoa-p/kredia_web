<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCategory extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'color',
        'icon',
        'description',
        'min_amount',
        'max_amount',
        'penalty_grace_days',
        'penalty_rate',
        'penalty_fixed_amount',
        'active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'penalty_grace_days' => 'integer',
        'penalty_rate' => 'decimal:2',
        'penalty_fixed_amount' => 'decimal:2',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    const CATEGORIA_VEHICULO = 1;
    const CATEGORIA_JOYAS = 2;
    const CATEGORIA_GARRAFAS = 3;
    const CATEGORIA_ARTICULOS = 4;

    /** Íconos que se pueden elegir para una categoría (clase de Bootstrap Icons => nombre). */
    const ICONS = [
        'bi-truck' => 'Vehículo',
        'bi-car-front' => 'Auto',
        'bi-bicycle' => 'Bicicleta / moto',
        'bi-gem' => 'Joya',
        'bi-watch' => 'Reloj',
        'bi-fire' => 'Garrafa / gas',
        'bi-phone' => 'Celular',
        'bi-tablet' => 'Tablet',
        'bi-laptop' => 'Computadora',
        'bi-pc-display' => 'PC de escritorio',
        'bi-display' => 'Monitor',
        'bi-tv' => 'Televisor',
        'bi-printer' => 'Impresora',
        'bi-camera' => 'Cámara',
        'bi-controller' => 'Consola / juegos',
        'bi-headphones' => 'Audio',
        'bi-speaker' => 'Parlante',
        'bi-music-note-beamed' => 'Instrumento',
        'bi-snow' => 'Heladera',
        'bi-plug' => 'Electrodoméstico',
        'bi-fan' => 'Ventilador',
        'bi-lightning-charge' => 'Generador',
        'bi-tools' => 'Herramienta',
        'bi-hammer' => 'Construcción',
        'bi-lamp' => 'Mueble / hogar',
        'bi-bag' => 'Ropa / bolsos',
        'bi-book' => 'Libros',
        'bi-house' => 'Inmueble',
        'bi-file-earmark-text' => 'Documento',
        'bi-box-seam' => 'Otro artículo',
    ];

    /** Ícono sugerido según el número o el nombre de la categoría (el que se usaba antes de poder elegirlo). */
    public static function guessIcon(?string $name, ?int $id = null): string
    {
        $name = mb_strtoupper((string) $name);

        return match (true) {
            $id === self::CATEGORIA_VEHICULO => 'bi-truck',
            $id === self::CATEGORIA_JOYAS => 'bi-gem',
            $id === self::CATEGORIA_GARRAFAS => 'bi-fire',
            str_contains($name, 'CELULAR') || str_contains($name, 'TELEF') => 'bi-phone',
            str_contains($name, 'COMPU') || str_contains($name, 'LAPTOP') => 'bi-laptop',
            str_contains($name, 'HELADER') || str_contains($name, 'REFRI') => 'bi-snow',
            str_contains($name, 'ELECTRO') => 'bi-plug',
            str_contains($name, 'HERRAM') => 'bi-tools',
            str_contains($name, 'TELEV') || str_starts_with($name, 'TV') || str_contains($name, ' TV') => 'bi-tv',
            str_contains($name, 'JOYA') => 'bi-gem',
            str_contains($name, 'GARRAF') => 'bi-fire',
            str_contains($name, 'VEHIC') || str_contains($name, 'AUTO') => 'bi-truck',
            str_contains($name, 'MOTO') || str_contains($name, 'BICI') => 'bi-bicycle',
            str_contains($name, 'ROPA') => 'bi-bag',
            default => 'bi-box-seam',
        };
    }

    /** Clase del ícono a mostrar: el elegido o, si no tiene, el sugerido. */
    public function getIconClassAttribute(): string
    {
        return isset(self::ICONS[$this->icon ?? '']) ? $this->icon : self::guessIcon($this->name, $this->id);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CreditCategoryRule::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
