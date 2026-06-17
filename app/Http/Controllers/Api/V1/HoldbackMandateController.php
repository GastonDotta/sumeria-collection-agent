<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\HoldbackMandateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HoldbackMandateController extends Controller
{
    public function __construct(
        private readonly HoldbackMandateService $mandateService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function store(Request $request, int $lenderId): JsonResponse
    {
        $validated = $request->validate([
            'merchant_id'                => 'required|integer',
            'loan_id'                    => 'required|integer',
            'authorized_max_holdback_pct' => 'required|numeric|min:0.0001|max:1',
            'payment_channels'           => 'required|array|min:1',
            'payment_channels.*'         => 'string',
            'contract_clause_ref'        => 'required|string|max:255',
            'signed_at'                  => 'required|date',
        ]);

        $mandate = $this->mandateService->register($lenderId, $validated);

        return response()->json(['mandate_id' => $mandate->id], 201);
    }

    public function show(int $lenderId, int $mandateId): JsonResponse
    {
        $mandate = \App\Models\HoldbackMandate::where('lender_id', $lenderId)
            ->findOrFail($mandateId);

        return response()->json($mandate);
    }
}
