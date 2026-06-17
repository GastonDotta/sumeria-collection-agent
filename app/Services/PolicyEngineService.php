<?php

namespace App\Services;

use App\Models\NegotiationPolicy;
use Illuminate\Validation\ValidationException;

class PolicyEngineService
{
    public function upsertPolicy(int $lenderId, array $data): NegotiationPolicy
    {
        $policy = NegotiationPolicy::updateOrCreate(
            ['lender_id' => $lenderId],
            array_merge($data, ['active' => true])
        );

        return $policy;
    }

    public function getActivePolicy(int $lenderId): NegotiationPolicy
    {
        $policy = NegotiationPolicy::where('lender_id', $lenderId)
            ->where('active', true)
            ->first();

        if (! $policy) {
            throw new \DomainException("No existe política activa para el lender {$lenderId}.");
        }

        return $policy;
    }

    /**
     * Valida que un % de holdback propuesto sea válido según la política del lender
     * y no supere el máximo autorizado en el mandato.
     */
    public function assertHoldbackWithinLimits(
        float $proposedPct,
        NegotiationPolicy $policy,
        float $mandateMaxPct
    ): void {
        $effectiveMax = min($policy->max_holdback_pct, $mandateMaxPct);

        if ($proposedPct < $policy->min_holdback_pct || $proposedPct > $effectiveMax) {
            throw new \DomainException(
                "El % de holdback {$proposedPct} está fuera del rango permitido " .
                "[{$policy->min_holdback_pct}, {$effectiveMax}]."
            );
        }
    }

    public function isContactAllowed(NegotiationPolicy $policy): bool
    {
        $now = now()->setTimezone('UTC')->format('H:i');
        return $now >= $policy->contact_hours_start && $now <= $policy->contact_hours_end;
    }
}
