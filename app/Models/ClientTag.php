<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Etiqueta manual de clientes (ej. CLIENTE FIEL, VIP), definida por cada empresa
 * en "Configurar categorías". El cliente guarda el nombre en clients.tag.
 */
class ClientTag extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'color'];

    public const DEFAULT_COLOR = '#4f46e5';

    /** Colores para elegir en el panel. */
    public const COLORS = [
        '#4f46e5' => 'Índigo',
        '#0ea5e9' => 'Celeste',
        '#0d9488' => 'Turquesa',
        '#16a34a' => 'Verde',
        '#ca8a04' => 'Dorado',
        '#ea580c' => 'Naranja',
        '#dc2626' => 'Rojo',
        '#db2777' => 'Rosa',
        '#7c3aed' => 'Violeta',
        '#475569' => 'Gris',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function normalizeName(?string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }

    /** Etiquetas de una empresa, ordenadas por nombre. */
    public static function forCompany(?int $companyId): Collection
    {
        return $companyId ? static::where('company_id', $companyId)->orderBy('name')->get() : collect();
    }

    /**
     * Colores por empresa y nombre: [company_id => [name => color]].
     *
     * @param  iterable<int>|null  $companyIds  null = todas
     */
    public static function colorMap(?iterable $companyIds = null): array
    {
        $map = [];
        static::query()
            ->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', collect($companyIds)->filter()->unique()->all()))
            ->get(['company_id', 'name', 'color'])
            ->each(function ($t) use (&$map) { $map[$t->company_id][$t->name] = $t->color; });

        return $map;
    }
}
