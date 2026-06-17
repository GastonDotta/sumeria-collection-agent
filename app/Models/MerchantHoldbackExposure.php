<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantHoldbackExposure extends Model
{
    protected $table = 'merchant_holdback_exposure';

    protected $fillable = [
        'merchant_id',
        'total_active_holdback_pct',
        'active_cases_count',
        'last_recalculated_at',
    ];

    protected $casts = [
        'total_active_holdback_pct' => 'decimal:4',
        'last_recalculated_at'      => 'datetime',
    ];
}
