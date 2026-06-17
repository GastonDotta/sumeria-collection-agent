<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NegotiationPolicy extends Model
{
    protected $fillable = [
        'lender_id',
        'min_holdback_pct',
        'max_holdback_pct',
        'max_default_rate',
        'max_recovery_extension_days',
        'max_exception_requests',
        'min_recovery_threshold',
        'contact_hours_start',
        'contact_hours_end',
        'jurisdiction',
        'active',
    ];

    protected $casts = [
        'min_holdback_pct' => 'decimal:4',
        'max_holdback_pct' => 'decimal:4',
        'max_default_rate' => 'decimal:4',
        'min_recovery_threshold' => 'decimal:4',
        'active' => 'boolean',
    ];
}
