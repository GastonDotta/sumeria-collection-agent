<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CollectionCase;
use App\Services\AuditLogService;
use App\Services\EscalationNotificationService;
use App\Services\ExceptionAgentService;
use App\Services\HoldbackExecutionEngine;
use App\Services\NotificationService;
use App\Services\PolicyEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recibe solicitudes de excepción del comercio (vía WhatsApp webhook o API directa).
 * Delega la evaluación al ExceptionAgentService (LLM) y aplica la resolución.
 */
class ExceptionRequestController extends Controller
{
    public function __construct(
        private readonly ExceptionAgentService        $exceptionAgent,
        private readonly PolicyEngineService          $policyEngine,
        private readonly HoldbackExecutionEngine      $executionEngine,
        private readonly NotificationService          $notifier,
        private readonly EscalationNotificationService $escalationNotifier,
        private readonly AuditLogService              $auditLog,
    ) {}

    public function store(Request $request, int $caseId): JsonResponse
    {
        $validated = $request->validate([
            'raw_message'    => 'required|string|max:2000',
            'channel'        => 'required|in:whatsapp,email,api',
            'merchant_phone' => 'nullable|string',
        ]);

        $case = CollectionCase::findOrFail($caseId);

        if (! in_array($case->status, ['holdback_active', 'holdback_adjusted'], true)) {
            return response()->json([
                'error' => 'No hay holdback activo sobre este caso.',
            ], 422);
        }

        $policy = $this->policyEngine->getActivePolicy($case->lender_id);

        $exceptionsUsed = $case->exceptionRequests()
            ->whereIn('status', ['approved_within_policy'])
            ->count();

        if ($exceptionsUsed >= $policy->max_exception_requests) {
            $exceptionRequest = $case->exceptionRequests()->create([
                'requested_by' => 'merchant',
                'request_type' => 'reduce_pct',
                'raw_message'  => $validated['raw_message'],
                'status'       => 'escalated',
            ]);

            $case->update(['status' => 'escalated']);
            $case->escalation()->create(['reason' => 'out_of_policy']);
            $this->auditLog->log($case->id, 'escalated', ['reason' => 'max_exceptions_reached']);
            $this->escalationNotifier->notify($case);

            return response()->json([
                'next_action' => 'escalated',
                'status'      => 'escalated',
            ]);
        }

        $requestType = $this->inferRequestType($validated['raw_message']);

        $exceptionRequest = $case->exceptionRequests()->create([
            'requested_by' => 'merchant',
            'request_type' => $requestType,
            'raw_message'  => $validated['raw_message'],
            'status'       => 'pending',
        ]);

        $this->auditLog->log($case->id, 'exception_requested', [
            'request_type' => $requestType,
            'channel'      => $validated['channel'],
        ]);

        $resolution = $this->exceptionAgent->evaluate($exceptionRequest, $case);

        $exceptionRequest->update([
            'proposed_resolution' => $resolution['proposed_resolution'],
            'status'              => $resolution['status'],
            'resolved_at'         => now(),
        ]);

        if ($resolution['status'] === 'approved_within_policy') {
            $this->applyResolution($case, $resolution['proposed_resolution']);
            $case->update(['status' => 'exception_pending']);
        } else {
            $case->update(['status' => 'escalated']);
            $case->escalation()->create(['reason' => $resolution['escalation_reason']]);
            $this->auditLog->log($case->id, 'escalated', ['reason' => $resolution['escalation_reason']]);
            $this->escalationNotifier->notify($case->fresh()->load('escalation'));
        }

        if ($validated['merchant_phone'] ?? null) {
            $this->notifier->notifyHoldbackAdjusted(
                $case->fresh(),
                $validated['merchant_phone'],
                lenderName: "la institución financiera",
                previousPct: (float) $case->current_holdback_pct,
                newPct: (float) ($resolution['proposed_resolution']['new_holdback_pct'] ?? $case->current_holdback_pct),
                reason: 'ajuste por tu solicitud',
            );
        }

        return response()->json([
            'next_action'          => $resolution['status'],
            'message_to_merchant'  => $resolution['message_to_merchant'],
        ]);
    }

    private function applyResolution(CollectionCase $case, ?array $resolution): void
    {
        if (! $resolution) {
            return;
        }

        if (isset($resolution['new_holdback_pct']) && $resolution['new_holdback_pct'] !== null) {
            $newPct = (float) $resolution['new_holdback_pct'];

            $case->adjustments()->create([
                'triggered_by'         => 'exception_agreement',
                'previous_holdback_pct' => $case->current_holdback_pct,
                'new_holdback_pct'     => $newPct,
                'reason'               => 'excepcion_aprobada',
            ]);

            $this->executionEngine->adjust($case, $newPct);

            $case->update(['current_holdback_pct' => $newPct]);

            $this->auditLog->log($case->id, 'holdback_adjusted', [
                'triggered_by' => 'exception_agreement',
                'new_pct'      => $newPct,
            ]);
        }
    }

    private function inferRequestType(string $message): string
    {
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'disput') || str_contains($lower, 'no debo') || str_contains($lower, 'error')) {
            return 'dispute';
        }
        if (str_contains($lower, 'paus') || str_contains($lower, 'detener') || str_contains($lower, 'frenar')) {
            return 'pause';
        }
        if (str_contains($lower, 'plazo') || str_contains($lower, 'extender') || str_contains($lower, 'más tiempo')) {
            return 'extend_term';
        }

        return 'reduce_pct';
    }
}
