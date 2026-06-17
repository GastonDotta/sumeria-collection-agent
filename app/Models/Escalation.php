<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    protected $fillable = [
        'collection_case_id',
        'reason',
        'assigned_to',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function collectionCase(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class);
    }
}
