<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'numero',
        'code',
        'credit_category_id',
        'name',
        'sku',
        'brand',
        'model_year',
        'item_model',
        'material_type',
        'plate',
        'chassis',
        'serial_number',
        'karat',
        'item_type',
        'description',
        'unit',
        'cost',
        'price',
        'active',
    ];

    protected $casts = [
        'model_year' => 'integer',
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Código del producto (products.code) según su origen: el del préstamo que lo creó (PR-00123), el de la
     * compra (CO-00007) o, para ingresos de inventario y altas manuales, el correlativo PD- de la empresa
     * (editable). No se repite dentro de la misma sucursal (products.branch_id). products.numero guarda el
     * número de los códigos PD- para sugerir el siguiente.
     */
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Product $model) {
            if (blank($model->code)) {
                $model->code = static::nextPdCode((int) $model->company_id);
            }
        });
        static::saving(function (Product $model) {
            $model->code = static::normalizeCode($model->code);
            $model->numero = preg_match('/^PD-0*(\d+)$/', (string) $model->code, $m) ? (int) $m[1] : null;
        });
    }

    public static function formatCode(string $prefix, $numero): string
    {
        return $prefix . '-' . str_pad((string) $numero, 5, '0', STR_PAD_LEFT);
    }

    /** Siguiente código PD- de la empresa (cuenta los eliminados para no repetir). */
    public static function nextPdCode(int $companyId): string
    {
        return self::formatCode('PD', (static::withTrashed()->where('company_id', $companyId)->max('numero') ?? 0) + 1);
    }

    /** Mayúsculas y sin espacios; "pd29" o "PR 31" pasan a "PD-00029" / "PR-00031". Vacío = null. */
    public static function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $code));
        if ($code === '') {
            return null;
        }

        return preg_match('/^(PD|PR|CO)-?0*(\d+)$/', $code, $m) ? self::formatCode($m[1], $m[2]) : $code;
    }

    /** ¿Ya existe otro producto con ese código en la misma empresa y sucursal? (incluye eliminados) */
    public static function codeTaken(int $companyId, ?int $branchId, string $code, ?int $ignoreId = null): bool
    {
        return static::withTrashed()
            ->where('company_id', $companyId)
            ->where('code', self::normalizeCode($code))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId), fn ($q) => $q->whereNull('branch_id'))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    /** Código completo si [$q] tiene forma de código ("pr31", "PD-12", "CO 7"); null si no. */
    public static function codeFromSearch(?string $q): ?string
    {
        return preg_match('/^(PD|PR|CO)\s*-?\s*0*(\d+)$/i', trim((string) $q), $m)
            ? self::formatCode(strtoupper($m[1]), $m[2])
            : null;
    }

    /** SKU automático y único: 6 letras del nombre + fecha y hora + sufijo aleatorio (evita choques en el mismo segundo). */
    public static function makeSku(?string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $name), 0, 6));

        do {
            $sku = ($base !== '' ? $base : 'PRD') . '-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3));
        } while (static::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    /** El producto toma el código del documento que lo creó (préstamo o compra) y su sucursal. */
    public function assignOrigin(string $code, ?int $branchId): void
    {
        $this->update(['code' => $code, 'branch_id' => $branchId]);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creditCategory(): BelongsTo
    {
        return $this->belongsTo(CreditCategory::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}