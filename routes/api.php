<?php

use App\Http\Controllers\Api\V1\Admin\LenderController;
use App\Http\Controllers\Api\V1\CollectionCaseController;
use App\Http\Controllers\Api\V1\ExceptionRequestController;
use App\Http\Controllers\Api\V1\HoldbackMandateController;
use App\Http\Controllers\Api\V1\NegotiationPolicyController;
use App\Http\Controllers\Api\V1\ShadowReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sumeria Collection Agent — API v1
|--------------------------------------------------------------------------
|
| Autenticación: Laravel Sanctum (API token por institución financiera).
| Todos los endpoints requieren el header: Authorization: Bearer {token}
|
| Roles:
|   sumeria-admin → endpoints bajo /admin (gestión de lenders)
|   lender        → todos los demás endpoints (self-service por institución)
|
*/

// --- Admin: gestión de lenders (Sumeria interno) ---
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'ability:sumeria-admin'])->group(function () {
    Route::get('lenders', [LenderController::class, 'index']);
    Route::post('lenders', [LenderController::class, 'store']);
    Route::get('lenders/{lender_id}', [LenderController::class, 'show']);
    Route::post('lenders/{lender_id}/tokens', [LenderController::class, 'issueToken']);
    Route::put('lenders/{lender_id}/webhook', [LenderController::class, 'upsertWebhook']);
    Route::delete('lenders/{lender_id}', [LenderController::class, 'deactivate']);
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // --- Policy Engine (Sprint 1) ---
    Route::post('lenders/{lender_id}/negotiation-policy', [NegotiationPolicyController::class, 'upsert']);
    Route::get('lenders/{lender_id}/negotiation-policy', [NegotiationPolicyController::class, 'show']);

    // --- Holdback Mandates (Sprint 1) ---
    // Registrar al momento de originar un préstamo
    Route::post('lenders/{lender_id}/holdback-mandates', [HoldbackMandateController::class, 'store']);
    Route::get('lenders/{lender_id}/holdback-mandates/{mandate_id}', [HoldbackMandateController::class, 'show']);

    // --- Collection Cases (Sprint 1 base, Decision Engine en Sprint 3-4) ---
    Route::post('collection-cases', [CollectionCaseController::class, 'store']);
    Route::get('collection-cases/{case_id}', [CollectionCaseController::class, 'show']);
    Route::post('collection-cases/{case_id}/close', [CollectionCaseController::class, 'close']);
    Route::post('collection-cases/{case_id}/escalate', [CollectionCaseController::class, 'escalate']);

    // --- Shadow Mode Review (Sprint 3-4) ---
    // Revisión humana de recomendaciones del Decision Engine antes de activar holdback real
    Route::get('lenders/{lender_id}/shadow-reviews', [ShadowReviewController::class, 'index']);
    Route::post('collection-cases/{case_id}/shadow-reviews/approve', [ShadowReviewController::class, 'approve']);
    Route::post('collection-cases/{case_id}/shadow-reviews/reject', [ShadowReviewController::class, 'reject']);

    // --- Exception Requests (Sprint 7-8) ---
    Route::post('collection-cases/{case_id}/exception-requests', [ExceptionRequestController::class, 'store']);

});
