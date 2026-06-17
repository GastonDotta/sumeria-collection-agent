<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CollectionCase;
use App\Services\HoldbackOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de revisión humana en shadow mode.
 * Un usuario del lender consulta la recomendación del Decision Engine y
 * la aprueba o rechaza. Solo disponible cuando el caso está en status=detected
 * y tiene una shadow_recommendation.
 */
class ShadowReviewController extends Controller
{
    public function __construct(
        private readonly HoldbackOrchestratorService $orchestrator,
    ) {}

    /**
     * Lista los casos pendientes de revisión humana para un lender.
     */
    public function index(Request $request, int $lenderId): JsonResponse
    {
        $cases = CollectionCase::where('lender_id', $lenderId)
            ->where('status', 'detected')
            ->whereNotNull('shadow_recommendation')
            ->whereNull('shadow_reviewed_at')
            ->with('mandate')
            ->latest()
            ->paginate(20);

        return response()->json($cases);
    }

    /**
     * Aprueba la recomendación — activa holdback o escala según lo que calculó el Decision Engine.
     */
    public function approve(Request $request, int $caseId): JsonResponse
    {
        $validated = $request->validate([
            'reviewed_by' => 'required|string|max:255',
        ]);

        $case = CollectionCase::findOrFail($caseId);

        $this->orchestrator->approveShadowRecommendation($case, $validated['reviewed_by']);

        $case->refresh();

        return response()->json([
            'case_id' => $case->id,
            'status'  => $case->status,
        ]);
    }

    /**
     * Rechaza la recomendación — escala el caso a revisión humana completa.
     */
    public function reject(Request $request, int $caseId): JsonResponse
    {
        $validated = $request->validate([
            'reviewed_by' => 'required|string|max:255',
        ]);

        $case = CollectionCase::findOrFail($caseId);

        $this->orchestrator->rejectShadowRecommendation($case, $validated['reviewed_by']);

        return response()->json([
            'case_id' => $case->id,
            'status'  => 'escalated',
        ]);
    }
}
