<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExceptionRequest extends Model
{
    protected $fillable = [
        'collection_case_id',
        'requested_by',
        'request_type',
        'raw_message',
        'proposed_resolution',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'proposed_resolution' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function collectionCase(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class);
    }
}
