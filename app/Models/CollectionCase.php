<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CollectionCase extends Model
{
    protected $fillable = [
        'merchant_id',
        'lender_id',
        'holdback_mandate_id',
        'score_at_detection',
        'amount_due',
        'days_overdue',
        'status',
        'recovery_probability',
        'current_holdback_pct',
        'estimated_recovery_days',
        'shadow_recommendation',
        'shadow_reviewed_at',
        'shadow_reviewed_by',
        'gateway_holdback_id',
        'gateway_provider',
    ];

    protected $casts = [
        'amount_due'            => 'decimal:2',
        'recovery_probability'  => 'decimal:4',
        'current_holdback_pct'  => 'decimal:4',
        'shadow_recommendation' => 'array',
        'shadow_reviewed_at'    => 'datetime',
    ];

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(HoldbackMandate::class, 'holdback_mandate_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(HoldbackAdjustment::class);
    }

    public function exceptionRequests(): HasMany
    {
        return $this->hasMany(ExceptionRequest::class);
    }

    public function escalation(): HasOne
    {
        return $this->hasOne(Escalation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
