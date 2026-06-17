<?php

namespace App\Services;

use App\Models\CollectionCase;
use App\Models\HoldbackMandate;
use App\Models\NegotiationPolicy;
use App\Services\EscalationNotificationService;
use App\Services\HoldbackExecutionEngine;
use App\Services\MerchantExposureService;

/**
 * Máquina de estados del caso de cobranza.
 *
 * Transiciones válidas:
 *   detected          → holdback_active | escalated       (vía Decision Engine)
 *   holdback_active   → holdback_adjusted | exception_pending | escalated | closed_recovered
 *   holdback_adjusted → holdback_active | exception_pending | escalated | closed_recovered
 *   exception_pending → holdback_active | holdback_adjusted | escalated | closed_recovered
 *   escalated         → (terminal — solo humano puede re-abrir)
 *   closed_recovered  → (terminal)
 *   closed_default    → (terminal)
 *
 * En shadow_mode el orchestrator calcula la recomendación y la almacena
 * en `shadow_recommendation` pero NO cambia el status ni activa holdback.
 */
class HoldbackOrchestratorService
{
    public function __construct(
        private readonly DecisionEngineService         $decisionEngine,
        private readonly PolicyEngineService           $policyEngine,
        private readonly AuditLogService               $auditLog,
        private readonly HoldbackExecutionEngine       $executionEngine,
        private readonly EscalationNotificationService $escalationNotifier,
        private readonly MerchantExposureService       $exposureService,
    ) {}

    /**
     * Punto de entrada principal. Procesa un caso recién creado (status=detected).
     * Si shadow_mode=true → guarda recomendación para revisión humana.
     * Si shadow_mode=false → activa holdback o escala directamente.
     */
    public function process(CollectionCase $case): void
    {
        $this->assertStatus($case, 'detected');

        $policy  = $this->policyEngine->getActivePolicy($case->lender_id);
        $mandate = $case->mandate;

        // Gap #1 — bloquear si el mandato no tiene validación legal
        $this->policyEngine->assertMandateLegallyValid($mandate);

        $recommendation = $this->decisionEngine->evaluate($case, $policy, $mandate);

        $this->auditLog->log($case->id, 'decision_engine_evaluated', $recommendation);

        if ($policy->shadow_mode) {
            $case->update([
                'shadow_recommendation' => $recommendation,
                'recovery_probability'  => $recommendation['recovery_probability'],
            ]);
            $this->auditLog->log($case->id, 'shadow_evaluated', [
                'shadow_mode' => true,
                'recommendation' => $recommendation,
            ]);
            return;
        }

        $this->applyRecommendation($case, $recommendation);
    }

    /**
     * Aprueba la recomendación en shadow mode. Activa el holdback o escala.
     * Solo disponible mientras status=detected y shadow_recommendation no es null.
     */
    public function approveShadowRecommendation(CollectionCase $case, string $reviewedBy): void
    {
        $this->assertStatus($case, 'detected');

        if (! $case->shadow_recommendation) {
            throw new \DomainException("El caso {$case->id} no tiene una recomendación shadow pendiente.");
        }

        $case->update([
            'shadow_reviewed_at' => now(),
            'shadow_reviewed_by' => $reviewedBy,
        ]);

        $this->auditLog->log($case->id, 'shadow_approved', ['reviewed_by' => $reviewedBy]);

        $this->applyRecommendation($case, $case->shadow_recommendation);
    }

    /**
     * Rechaza la recomendación en shadow mode y escala a humano.
     */
    public function rejectShadowRecommendation(CollectionCase $case, string $reviewedBy): void
    {
        $this->assertStatus($case, 'detected');

        $case->update([
            'shadow_reviewed_at' => now(),
            'shadow_reviewed_by' => $reviewedBy,
        ]);

        $this->transitionToEscalated($case, 'out_of_policy');

        $this->auditLog->log($case->id, 'shadow_rejected', ['reviewed_by' => $reviewedBy]);
    }

    /**
     * Ajusta el % de holdback activo (por cambio estacional u otro trigger del sistema).
     * Solo aplica cuando el caso ya está en holdback_active o holdback_adjusted.
     */
    public function adjustHoldback(CollectionCase $case, float $newPct, string $reason): void
    {
        $this->assertStatusIn($case, ['holdback_active', 'holdback_adjusted']);

        $policy  = $this->policyEngine->getActivePolicy($case->lender_id);
        $mandate = $case->mandate;

        $this->policyEngine->assertHoldbackWithinLimits(
            $newPct,
            $policy,
            (float) $mandate->authorized_max_holdback_pct,
        );

        $case->adjustments()->create([
            'triggered_by'         => 'system',
            'previous_holdback_pct' => $case->current_holdback_pct,
            'new_holdback_pct'     => $newPct,
            'reason'               => $reason,
        ]);

        $case->update([
            'current_holdback_pct' => $newPct,
            'status'               => 'holdback_adjusted',
        ]);

        $this->auditLog->log($case->id, 'holdback_adjusted', [
            'previous_pct' => $case->current_holdback_pct,
            'new_pct'      => $newPct,
            'reason'       => $reason,
        ]);
    }

    /**
     * Marca el caso como recuperado completamente.
     */
    public function closeAsRecovered(CollectionCase $case, float $amountRecovered, string $method): void
    {
        $this->assertStatusIn($case, [
            'holdback_active',
            'holdback_adjusted',
            'exception_pending',
        ]);

        $case->update(['status' => 'closed_recovered']);

        $this->auditLog->log($case->id, 'closed_recovered', [
            'amount_recovered' => $amountRecovered,
            'method'           => $method,
        ]);
    }

    // -------------------------------------------------------------------------
    // Privados
    // -------------------------------------------------------------------------

    private function applyRecommendation(CollectionCase $case, array $recommendation): void
    {
        if ($recommendation['action'] === 'escalate') {
            $this->transitionToEscalated($case, $recommendation['escalation_reason']);
            return;
        }

        $holdbackPct = $recommendation['holdback_pct'];

        $case->adjustments()->create([
            'triggered_by'         => 'system',
            'previous_holdback_pct' => null,
            'new_holdback_pct'     => $holdbackPct,
            'reason'               => 'mora_inicial',
        ]);

        $case->update([
            'status'                  => 'holdback_active',
            'recovery_probability'    => $recommendation['recovery_probability'],
            'current_holdback_pct'    => $holdbackPct,
            'estimated_recovery_days' => $recommendation['estimated_recovery_days'],
        ]);

        // Sprint 5-6: ejecutar retención real sobre la pasarela
        $this->executionEngine->activate($case, $holdbackPct);

        // Gap #7 — recalcular exposición agregada del comercio
        $this->exposureService->recalculate($case->merchant_id);

        $this->auditLog->log($case->id, 'holdback_activated', [
            'holdback_pct'            => $holdbackPct,
            'estimated_recovery_days' => $recommendation['estimated_recovery_days'],
        ]);
    }

    private function transitionToEscalated(CollectionCase $case, string $reason): void
    {
        // Cancelar retención activa en la pasarela antes de escalar
        if ($case->gateway_holdback_id) {
            $this->executionEngine->cancel($case);
        }

        $case->update(['status' => 'escalated']);
        $case->escalation()->create(['reason' => $reason]);
        $this->auditLog->log($case->id, 'escalated', ['reason' => $reason]);

        // Gap #7 — recalcular exposición al cerrar un holdback activo
        $this->exposureService->recalculate($case->merchant_id);

        // Sprint 7-8: notificar al lender vía webhook/CRM
        $this->escalationNotifier->notify($case->fresh()->load('escalation'));
    }

    private function assertStatus(CollectionCase $case, string $expected): void
    {
        if ($case->status !== $expected) {
            throw new \DomainException(
                "El caso {$case->id} está en status '{$case->status}', se esperaba '{$expected}'."
            );
        }
    }

    private function assertStatusIn(CollectionCase $case, array $allowed): void
    {
        if (! in_array($case->status, $allowed, true)) {
            throw new \DomainException(
                "El caso {$case->id} está en status '{$case->status}'. " .
                "Estados permitidos: " . implode(', ', $allowed) . '.'
            );
        }
    }
}
