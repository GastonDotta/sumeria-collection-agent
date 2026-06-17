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
        'merchant_operating_floor',
        'payment_channels',
        'contract_clause_ref',
        'legal_instrument_type',
        'validated_by_legal',
        'validated_by_legal_at',
        'legal_validation_ref',
        'signed_at',
        'active',
    ];

    protected $casts = [
        'payment_channels'            => 'array',
        'authorized_max_holdback_pct' => 'decimal:4',
        'merchant_operating_floor'    => 'decimal:2',
        'validated_by_legal'          => 'boolean',
        'validated_by_legal_at'       => 'datetime',
        'signed_at'                   => 'datetime',
        'active'                      => 'boolean',
    ];

    public function collectionCases(): HasMany
    {
        return $this->hasMany(CollectionCase::class);
    }
}
