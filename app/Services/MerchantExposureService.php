<?php

namespace App\Services;

use App\Models\CollectionCase;
use App\Models\MerchantHoldbackExposure;

/**
 * Gap #7 — Exposición agregada por comercio.
 *
 * Antes de activar o ajustar cualquier holdback, el Decision Engine debe consultar
 * este servicio para saber el % total retenido sobre el comercio across todos sus
 * préstamos activos con cualquier lender en la plataforma.
 */
class MerchantExposureService
{
    /**
     * Recalcula y persiste la exposición total de un comercio.
     * Debe llamarse cada vez que un caso activa, ajusta o cierra holdback.
     */
    public function recalculate(int $merchantId): MerchantHoldbackExposure
    {
        $activeCases = CollectionCase::where('merchant_id', $merchantId)
            ->whereIn('status', ['holdback_active', 'holdback_adjusted'])
            ->get();

        $totalPct   = $activeCases->sum('current_holdback_pct');
        $caseCount  = $activeCases->count();

        return MerchantHoldbackExposure::updateOrCreate(
            ['merchant_id' => $merchantId],
            [
                'total_active_holdback_pct' => $totalPct,
                'active_cases_count'        => $caseCount,
                'last_recalculated_at'      => now(),
            ]
        );
    }

    /**
     * Devuelve el % total activo para un comercio (sin recalcular).
     */
    public function getTotalActivePct(int $merchantId): float
    {
        $exposure = MerchantHoldbackExposure::where('merchant_id', $merchantId)->first();

        return $exposure ? (float) $exposure->total_active_holdback_pct : 0.0;
    }

    /**
     * Gap #4 — Circuit breaker de liquidez.
     *
     * Incrementa el contador de días consecutivos en que el holdback dejó al
     * comercio bajo su piso operativo. Si llega a 3, retorna true y el orquestador
     * debe reducir el % o escalar sin esperar a que el comercio pida excepción.
     */
    public function checkAndIncrementFloorBreach(CollectionCase $case, float $avgDailySales): bool
    {
        $mandate          = $case->mandate;
        $operatingFloor   = (float) ($mandate->merchant_operating_floor ?? 0);
        $currentHoldback  = (float) $case->current_holdback_pct;

        $availableAfterHoldback = $avgDailySales * (1 - $currentHoldback);
        $inBreach               = $availableAfterHoldback < $operatingFloor;

        if ($inBreach) {
            $newBreachDays = ($case->consecutive_floor_breach_days ?? 0) + 1;
            $case->update([
                'consecutive_floor_breach_days' => $newBreachDays,
                'last_floor_check_at'           => now(),
            ]);

            return $newBreachDays >= 3;
        }

        // Si no está en breach, resetear el contador
        if (($case->consecutive_floor_breach_days ?? 0) > 0) {
            $case->update([
                'consecutive_floor_breach_days' => 0,
                'last_floor_check_at'           => now(),
            ]);
        }

        return false;
    }
}
