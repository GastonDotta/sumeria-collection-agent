<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lender;
use App\Models\LenderWebhookConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión de instituciones financieras (lenders).
 * Solo accesible con token de rol 'sumeria-admin'.
 *
 * El flujo de onboarding de un nuevo lender es:
 *   1. POST /admin/lenders           → crea el lender
 *   2. POST /admin/lenders/{id}/tokens → genera el API token que usará para autenticarse
 *   3. El lender usa ese token para configurar su policy y sus mandatos via self-service
 */
class LenderController extends Controller
{
    public function index(): JsonResponse
    {
        $lenders = Lender::with(['policy', 'webhookConfig'])
            ->where('active', true)
            ->get();

        return response()->json($lenders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'required|string|max:100|unique:lenders,slug|alpha_dash',
            'jurisdiction'  => 'required|string|size:2',
            'contact_email' => 'required|email',
        ]);

        $lender = Lender::create(array_merge($validated, [
            'active'       => true,
            'onboarded_at' => now(),
        ]));

        return response()->json([
            'lender_id' => $lender->id,
            'slug'      => $lender->slug,
            'message'   => 'Lender creado. Generá un token con POST /admin/lenders/{id}/tokens para habilitarlo.',
        ], 201);
    }

    public function show(int $lenderId): JsonResponse
    {
        $lender = Lender::with(['policy', 'webhookConfig'])->findOrFail($lenderId);

        return response()->json($lender);
    }

    /**
     * Genera un API token para que el lender se autentique en los endpoints self-service.
     * El token solo se muestra una vez — no se puede recuperar después.
     */
    public function issueToken(Request $request, int $lenderId): JsonResponse
    {
        $validated = $request->validate([
            'token_name' => 'required|string|max:100',
        ]);

        $lender = Lender::findOrFail($lenderId);

        $token = $lender->createToken($validated['token_name']);

        return response()->json([
            'token'      => $token->plainTextToken,
            'lender_id'  => $lender->id,
            'token_name' => $validated['token_name'],
            'warning'    => 'Guardá este token en un lugar seguro — no se puede recuperar.',
        ], 201);
    }

    /**
     * Configura o actualiza el webhook del lender para notificaciones de escalamiento.
     */
    public function upsertWebhook(Request $request, int $lenderId): JsonResponse
    {
        $validated = $request->validate([
            'escalation_webhook_url' => 'nullable|url',
            'agreement_webhook_url'  => 'nullable|url',
            'webhook_secret'         => 'nullable|string|min:16',
        ]);

        Lender::findOrFail($lenderId);

        $config = LenderWebhookConfig::updateOrCreate(
            ['lender_id' => $lenderId],
            array_merge($validated, ['active' => true]),
        );

        return response()->json([
            'webhook_config_id' => $config->id,
            'lender_id'         => $lenderId,
        ]);
    }

    public function deactivate(int $lenderId): JsonResponse
    {
        $lender = Lender::findOrFail($lenderId);
        $lender->update(['active' => false]);

        return response()->json(['status' => 'deactivated']);
    }
}
