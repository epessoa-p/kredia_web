<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyCapital extends Model
{
    use Concerns\BelongsToCompany;

    protected $table = 'company_capital_entries';

    protected $fillable = [
        'company_id',
        'user_id',
        'type',
        'amount',
        'description',
        'entry_date',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'entry_date' => 'date',
    ];

    public const TYPE_INPUT      = 'input';
    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_LABELS = [
        self::TYPE_INPUT      => 'Aporte',
        self::TYPE_WITHDRAWAL => 'Retiro',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst((string) $this->type);
    }
}
