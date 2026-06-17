<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LenderWebhookConfig extends Model
{
    protected $fillable = [
        'lender_id',
        'escalation_webhook_url',
        'agreement_webhook_url',
        'webhook_secret',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $hidden = ['webhook_secret'];

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }
}
