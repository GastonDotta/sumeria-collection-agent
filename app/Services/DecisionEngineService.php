<?php

namespace App\Services;

use App\Contracts\ScoringApiInterface;
use App\Models\CollectionCase;
use App\Models\HoldbackMandate;
use App\Models\NegotiationPolicy;

/**
 * Calcula la probabilidad de recuperación de un caso y el % de holdback óptimo
 * dentro de los límites del Policy Engine y el mandato del comercio.
 *
 * Outputs posibles:
 *   action = 'activate_holdback' → activar retención con el % calculado
 *   action = 'escalate'          → derivar directo a humano (score bajo o flujo insuficiente)
 */
class DecisionEngineService
{
    public function __construct(private readonly ScoringApiInterface $scoringApi) {}

    /**
     * Evalúa un caso y devuelve la recomendación del motor.
     *
     * @return array{
     *   action: 'activate_holdback'|'escalate',
     *   recovery_probability: float,
     *   holdback_pct: float|null,
     *   estimated_recovery_days: int|null,
     *   escalation_reason: string|null,
     * }
     */
    public function evaluate(
        CollectionCase $case,
        NegotiationPolicy $policy,
        HoldbackMandate $mandate,
    ): array {
        $recoveryProb = $this->calculateRecoveryProbability(
            score: $case->score_at_detection,
            merchantId: $case->merchant_id,
        );

        if ($recoveryProb < $policy->min_recovery_threshold) {
            return [
                'action'               => 'escalate',
                'recovery_probability' => $recoveryProb,
                'holdback_pct'         => null,
                'estimated_recovery_days' => null,
                'escalation_reason'    => 'high_risk_score',
            ];
        }

        $avgDailySales = $this->scoringApi->getAverageDailySales($case->merchant_id);

        if ($avgDailySales <= 0) {
            return [
                'action'               => 'escalate',
                'recovery_probability' => $recoveryProb,
                'holdback_pct'         => null,
                'estimated_recovery_days' => null,
                'escalation_reason'    => 'insufficient_sales_flow',
            ];
        }

        $effectiveMax = min(
            (float) $policy->max_holdback_pct,
            (float) $mandate->authorized_max_holdback_pct,
        );

        $holdbackPct = $this->calculateHoldbackPct(
            amountDue: (float) $case->amount_due,
            daysOverdue: $case->days_overdue,
            avgDailySales: $avgDailySales,
            salesTrend: $this->scoringApi->getSalesTrend($case->merchant_id),
            minPct: (float) $policy->min_holdback_pct,
            maxPct: $effectiveMax,
        );

        $estimatedDays = $this->estimateRecoveryDays(
            amountDue: (float) $case->amount_due,
            avgDailySales: $avgDailySales,
            holdbackPct: $holdbackPct,
        );

        if ($estimatedDays > $policy->max_recovery_extension_days) {
            return [
                'action'               => 'escalate',
                'recovery_probability' => $recoveryProb,
                'holdback_pct'         => $holdbackPct,
                'estimated_recovery_days' => $estimatedDays,
                'escalation_reason'    => 'insufficient_sales_flow',
            ];
        }

        return [
            'action'               => 'activate_holdback',
            'recovery_probability' => $recoveryProb,
            'holdback_pct'         => $holdbackPct,
            'estimated_recovery_days' => $estimatedDays,
            'escalation_reason'    => null,
        ];
    }

    /**
     * Probabilidad de recuperación 0-1.
     *
     * Pondera el score normalizado (50%), el historial de pagos (30%)
     * y un factor base del score en sí mismo (20%).
     * Rango de score Sumeria: 0-1000.
     */
    private function calculateRecoveryProbability(int $score, int $merchantId): float
    {
        $normalizedScore = $score / 1000;

        $history         = $this->scoringApi->getPaymentHistory($merchantId);
        $totalPayments   = array_sum($history);
        $historyFactor   = $totalPayments > 0
            ? ($history['on_time'] / $totalPayments)
            : 0.5;

        $probability = ($normalizedScore * 0.5) + ($historyFactor * 0.3) + ($normalizedScore * 0.2);

        return round(min(1.0, max(0.0, $probability)), 4);
    }

    /**
     * Calcula el % de holdback óptimo.
     *
     * Parte del mínimo y lo escala según la urgencia de la deuda (días de atraso,
     * monto relativo al flujo diario) y la tendencia de ventas.
     * Nunca supera el máximo efectivo (mín entre policy y mandato).
     */
    private function calculateHoldbackPct(
        float $amountDue,
        int   $daysOverdue,
        float $avgDailySales,
        float $salesTrend,
        float $minPct,
        float $maxPct,
    ): float {
        // Factor de urgencia: cuántos días de ventas representa la deuda
        $debtToSalesRatio = $amountDue / ($avgDailySales * 30);
        $urgencyFactor    = min(1.0, $debtToSalesRatio);

        // Más días de atraso → más urgencia, hasta saturar en 60 días
        $overdueWeight = min(1.0, $daysOverdue / 60);

        // Tendencia negativa → reducir holdback para no ahogar el comercio
        // Tendencia positiva → podemos ser un poco más agresivos
        $trendAdjustment = 1.0 + ($salesTrend * 0.2);

        $rawPct = $minPct + (($maxPct - $minPct) * $urgencyFactor * $overdueWeight * $trendAdjustment);

        return round(min($maxPct, max($minPct, $rawPct)), 4);
    }

    private function estimateRecoveryDays(float $amountDue, float $avgDailySales, float $holdbackPct): int
    {
        if ($avgDailySales <= 0 || $holdbackPct <= 0) {
            return PHP_INT_MAX;
        }

        return (int) ceil($amountDue / ($avgDailySales * $holdbackPct));
    }
}
