<?php

namespace App\Services;

use App\Models\HoldbackMandate;
use App\Models\NegotiationPolicy;

class PolicyEngineService
{
    public function __construct(
        private readonly MerchantExposureService $exposureService,
    ) {}

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
     * Gap #1 — Bloquea activación si el mandato no tiene validación legal.
     * Ningún holdback real puede ejecutarse sin el sello de legal explícito en la tabla.
     */
    public function assertMandateLegallyValid(HoldbackMandate $mandate): void
    {
        if (! $mandate->validated_by_legal) {
            throw new \DomainException(
                "El mandato {$mandate->id} no tiene validación legal para la jurisdicción. " .
                "Se requiere validated_by_legal = true antes de activar holdback real."
            );
        }
    }

    /**
     * Valida que un % de holdback propuesto sea válido contra:
     *   - la política del lender (min/max)
     *   - el máximo autorizado en el mandato
     *   - Gap #6: el cap global de la plataforma Sumeria
     *   - Gap #7: la exposición agregada del comercio
     */
    public function assertHoldbackWithinLimits(
        float $proposedPct,
        NegotiationPolicy $policy,
        float $mandateMaxPct,
        ?int  $merchantId = null,
    ): void {
        // Gap #6 — tope absoluto de Sumeria, nunca superable por ningún lender
        $platformCap  = (float) ($policy->platform_max_holdback_pct ?? 0.30);
        $effectiveMax = min($policy->max_holdback_pct, $mandateMaxPct, $platformCap);

        if ($proposedPct < $policy->min_holdback_pct || $proposedPct > $effectiveMax) {
            throw new \DomainException(
                "El % de holdback {$proposedPct} está fuera del rango permitido " .
                "[{$policy->min_holdback_pct}, {$effectiveMax}] " .
                "(policy: {$policy->max_holdback_pct}, mandato: {$mandateMaxPct}, plataforma: {$platformCap})."
            );
        }

        // Gap #7 — verificar que la exposición agregada del comercio no supere el cap
        if ($merchantId !== null) {
            $currentExposure = $this->exposureService->getTotalActivePct($merchantId);
            if (($currentExposure + $proposedPct) > $platformCap) {
                throw new \DomainException(
                    "El comercio {$merchantId} ya tiene una exposición agregada de {$currentExposure} " .
                    "en holdbacks activos. Agregar {$proposedPct} superaría el cap de plataforma {$platformCap}."
                );
            }
        }
    }

    public function isContactAllowed(NegotiationPolicy $policy): bool
    {
        $now = now()->setTimezone('UTC')->format('H:i');
        return $now >= $policy->contact_hours_start && $now <= $policy->contact_hours_end;
    }
}
