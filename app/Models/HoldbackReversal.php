<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldbackReversal extends Model
{
    protected $fillable = [
        'collection_case_id',
        'holdback_adjustment_id',
        'amount_to_refund',
        'refund_method',
        'reason',
        'initiated_by',
        'status',
        'executed_at',
        'gateway_reversal_id',
        'gateway_response',
    ];

    protected $casts = [
        'amount_to_refund' => 'decimal:2',
        'executed_at'      => 'datetime',
    ];

    public function collectionCase(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(HoldbackAdjustment::class, 'holdback_adjustment_id');
    }
}
