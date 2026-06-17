<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Lender extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'slug',
        'jurisdiction',
        'contact_email',
        'active',
        'onboarded_at',
    ];

    protected $casts = [
        'active'        => 'boolean',
        'onboarded_at'  => 'datetime',
    ];

    public function policy(): HasOne
    {
        return $this->hasOne(NegotiationPolicy::class, 'lender_id');
    }

    public function webhookConfig(): HasOne
    {
        return $this->hasOne(LenderWebhookConfig::class, 'lender_id');
    }
}
