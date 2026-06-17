<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HoldbackMandate extends Model
{
    protected $fillable = [
        'merchant_id',
        'lender_id',
        'loan_id',
        'authorized_max_holdback_pct',
        'payment_channels',
        'contract_clause_ref',
        'signed_at',
        'active',
    ];

    protected $casts = [
        'payment_channels' => 'array',
        'authorized_max_holdback_pct' => 'decimal:4',
        'signed_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function collectionCases(): HasMany
    {
        return $this->hasMany(CollectionCase::class);
    }
}
