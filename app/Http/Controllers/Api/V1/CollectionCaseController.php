<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCollectionCase;
use App\Models\CollectionCase;
use App\Services\AuditLogService;
use App\Services\HoldbackMandateService;
use App\Services\PolicyEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionCaseController extends Controller
{
    public function __construct(
        private readonly HoldbackMandateService $mandateService,
        private readonly PolicyEngineService $policyEngine,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Webhook entrante desde el pipeline de scoring de Sumeria.
     * Detecta mora y crea el caso. Sprint 3-4 agrega Decision Engine.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchant_id'        => 'required|integer',
            'lender_id'          => 'required|integer',
            'loan_id'            => 'required|integer',
            'amount_due'         => 'required|numeric|min:0.01',
            'days_overdue'       => 'required|integer|min:1',
            'score_at_detection' => 'required|integer|min:0|max:1000',
        ]);

        // Verificar que existe mandato activo antes de abrir el caso
        $mandate = $this->mandateService->getActiveMandate(
            $validated['merchant_id'],
            $validated['lender_id'],
            $validated['loan_id'],
        );

        // Verificar que existe política activa para el lender
        $this->policyEngine->getActivePolicy($validated['lender_id']);

        $case = CollectionCase::create([
            'merchant_id'         => $validated['merchant_id'],
            'lender_id'           => $validated['lender_id'],
            'holdback_mandate_id' => $mandate->id,
            'score_at_detection'  => $validated['score_at_detection'],
            'amount_due'          => $validated['amount_due'],
            'days_overdue'        => $validated['days_overdue'],
            'status'              => 'detected',
        ]);

        $this->auditLog->log($case->id, 'detected', [
            'score'        => $validated['score_at_detection'],
            'amount_due'   => $validated['amount_due'],
            'days_overdue' => $validated['days_overdue'],
            'mandate_id'   => $mandate->id,
        ]);

        // Despacha el Decision Engine + Orchestrator asincrónicamente
        ProcessCollectionCase::dispatch($case->id);

        return response()->json([
            'case_id' => $case->id,
            'status'  => $case->status,
        ], 201);
    }

    public function show(int $caseId): JsonResponse
    {
        $case = CollectionCase::findOrFail($caseId);

        return response()->json([
            'case_id'                 => $case->id,
            'status'                  => $case->status,
            'recovery_probability'    => $case->recovery_probability,
            'current_holdback_pct'    => $case->current_holdback_pct,
            'estimated_recovery_days' => $case->estimated_recovery_days,
        ]);
    }

    public function close(Request $request, int $caseId): JsonResponse
    {
        $validated = $request->validate([
            'final_amount_recovered' => 'required|numeric|min:0',
            'closing_method'         => 'required|in:holdback,exception_agreement,manual',
        ]);

        $case = CollectionCase::findOrFail($caseId);

        $case->update(['status' => 'closed_recovered']);

        $this->auditLog->log($case->id, 'closed_recovered', $validated);

        return response()->json(['status' => $case->status]);
    }

    public function escalate(Request $request, int $caseId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|in:out_of_policy,insufficient_sales_flow,high_risk_score,merchant_dispute,mandate_limit_reached',
        ]);

        $case = CollectionCase::findOrFail($caseId);

        $case->update(['status' => 'escalated']);

        $escalation = $case->escalation()->create([
            'reason' => $validated['reason'],
        ]);

        $this->auditLog->log($case->id, 'escalated', $validated);

        return response()->json([
            'status'        => $case->status,
            'escalation_id' => $escalation->id,
        ]);
    }
}
