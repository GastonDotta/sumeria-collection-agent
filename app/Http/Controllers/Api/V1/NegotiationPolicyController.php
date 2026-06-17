<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PolicyEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NegotiationPolicyController extends Controller
{
    public function __construct(private readonly PolicyEngineService $policyEngine) {}

    public function upsert(Request $request, int $lenderId): JsonResponse
    {
        $validated = $request->validate([
            'min_holdback_pct'            => 'required|numeric|min:0.0001|max:1',
            'max_holdback_pct'            => 'required|numeric|min:0.0001|max:1|gte:min_holdback_pct',
            'max_default_rate'            => 'required|numeric|min:0|max:1',
            'max_recovery_extension_days' => 'required|integer|min:1|max:730',
            'max_exception_requests'      => 'required|integer|min:0|max:20',
            'min_recovery_threshold'      => 'required|numeric|min:0|max:1',
            'contact_hours_start'         => 'required|date_format:H:i',
            'contact_hours_end'           => 'required|date_format:H:i|after:contact_hours_start',
            'jurisdiction'                => 'required|string|size:2',
        ]);

        $policy = $this->policyEngine->upsertPolicy($lenderId, $validated);

        return response()->json(['policy_id' => $policy->id], 201);
    }

    public function show(int $lenderId): JsonResponse
    {
        $policy = $this->policyEngine->getActivePolicy($lenderId);

        return response()->json($policy);
    }
}
