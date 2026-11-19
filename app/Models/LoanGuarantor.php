<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Garante (aval) de un préstamo. Puede ser un cliente registrado o solo sus datos. */
class LoanGuarantor extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'loan_id', 'client_id', 'name', 'id_number', 'phone', 'address', 'relationship', 'notes',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
