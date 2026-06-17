<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldbackAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'collection_case_id',
        'triggered_by',
        'previous_holdback_pct',
        'new_holdback_pct',
        'reason',
    ];

    protected $casts = [
        'previous_holdback_pct' => 'decimal:4',
        'new_holdback_pct' => 'decimal:4',
    ];

    public function collectionCase(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class);
    }
}
