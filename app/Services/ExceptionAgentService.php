<?php

namespace App\Services;

use App\Models\CollectionCase;
use App\Models\ExceptionRequest;
use App\Models\NegotiationPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agente LLM (Claude via Anthropic API) que interpreta solicitudes de excepción
 * del comercio en lenguaje natural y propone una resolución dentro de los límites
 * del Policy Engine.
 *
 * El agente puede:
 *   - Aprobar y resolver la excepción dentro de los límites (reduce_pct, pause temporal, extend_term)
 *   - Escalar a humano si la solicitud está fuera de rango o es una disputa
 *
 * El agente NUNCA sabe el límite absoluto de la política — solo conoce el rango
 * disponible para ese caso específico, para evitar ingeniería social.
 *
 * Configuración requerida en .env:
 *   ANTHROPIC_API_KEY=...
 */
class ExceptionAgentService
{
    private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-6';

    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly PolicyEngineService $policyEngine,
    ) {}

    /**
     * Evalúa una solicitud de excepción del comercio y devuelve la resolución.
     *
     * @return array{
     *   status: 'approved_within_policy'|'escalated',
     *   message_to_merchant: string,
     *   proposed_resolution: array|null,
     *   escalation_reason: string|null,
     * }
     */
    public function evaluate(ExceptionRequest $request, CollectionCase $case): array
    {
        $policy = $this->policyEngine->getActivePolicy($case->lender_id);

        // Si es una disputa, escalar directo sin pasar por el LLM
        if ($request->request_type === 'dispute') {
            return [
                'status'               => 'escalated',
                'message_to_merchant'  => 'Tu consulta fue derivada a nuestro equipo para revisión personalizada. Te contactaremos pronto.',
                'proposed_resolution'  => null,
                'escalation_reason'    => 'merchant_dispute',
            ];
        }

        $availableRange = $this->buildAvailableRange($case, $policy);

        // Si no hay margen de maniobra, escalar
        if (! $availableRange['has_room']) {
            return [
                'status'              => 'escalated',
                'message_to_merchant' => 'Tu solicitud requiere revisión por nuestro equipo. Te contactaremos pronto.',
                'proposed_resolution' => null,
                'escalation_reason'   => 'out_of_policy',
            ];
        }

        $llmResponse = $this->callLlm($request->raw_message, $case, $availableRange);

        $this->auditLog->log($case->id, 'exception_llm_evaluated', [
            'request_type' => $request->request_type,
            'llm_response' => $llmResponse,
        ]);

        return $llmResponse;
    }

    private function buildAvailableRange(CollectionCase $case, NegotiationPolicy $policy): array
    {
        $currentPct = (float) $case->current_holdback_pct;
        $minPct     = (float) $policy->min_holdback_pct;
        $mandate    = $case->mandate;
        $effectiveMax = min((float) $policy->max_holdback_pct, (float) $mandate->authorized_max_holdback_pct);

        // Hay margen para bajar si el pct actual es mayor al mínimo
        $canReduce   = $currentPct > $minPct;
        $reduceFloor = $minPct;

        return [
            'has_room'         => $canReduce,
            'current_pct'      => $currentPct,
            'min_allowed_pct'  => $reduceFloor,
            'max_allowed_pct'  => $effectiveMax,
            'max_pause_days'   => 7, // Pausa máxima permitida por defecto
            'max_extension_days' => $policy->max_recovery_extension_days,
        ];
    }

    private function callLlm(string $merchantMessage, CollectionCase $case, array $range): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (app()->environment(['local', 'testing']) || ! $apiKey) {
            return $this->fakeResponse($merchantMessage, $range);
        }

        $systemPrompt = $this->buildSystemPrompt($case, $range);

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post(self::ANTHROPIC_API_URL, [
            'model'      => self::MODEL,
            'max_tokens' => 512,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $merchantMessage],
            ],
        ])->throw()->json();

        $content = $response['content'][0]['text'] ?? '';

        return $this->parseStructuredResponse($content);
    }

    private function buildSystemPrompt(CollectionCase $case, array $range): string
    {
        $currentPct = round($range['current_pct'] * 100, 1);
        $minPct     = round($range['min_allowed_pct'] * 100, 1);
        $maxPause   = $range['max_pause_days'];

        return <<<PROMPT
Sos un agente de atención al comercio de una institución financiera. El comercio tiene una retención activa del {$currentPct}% sobre sus ventas por un saldo pendiente.

Tu rol es evaluar la solicitud del comercio y resolverla de forma empática pero dentro de los límites disponibles para este caso.

ACCIONES DISPONIBLES:
- Reducir el % de retención (mínimo posible: {$minPct}%)
- Autorizar una pausa de hasta {$maxPause} días
- Combinar reducción + extensión de plazo

REGLAS ESTRICTAS:
- No podés prometer ni insinuar condiciones fuera de los límites indicados
- No mencionés los límites exactos al comercio
- Si la solicitud es razonable y tiene solución dentro del rango, resolvela
- Si no tiene solución dentro del rango o suena a disputa de la deuda, escalá

Respondé SIEMPRE en este formato JSON exacto, sin texto adicional:
{
  "status": "approved_within_policy" o "escalated",
  "message_to_merchant": "mensaje empático en español",
  "proposed_resolution": {
    "action": "reduce_pct" | "pause" | "reduce_and_extend" | null,
    "new_holdback_pct": número o null,
    "pause_days": número o null,
    "extension_days": número o null
  },
  "escalation_reason": null o "out_of_policy" o "merchant_dispute"
}
PROMPT;
    }

    private function parseStructuredResponse(string $content): array
    {
        $decoded = json_decode(trim($content), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($decoded['status'])) {
            Log::warning('ExceptionAgentService: respuesta LLM no parseable', ['content' => $content]);
            return [
                'status'              => 'escalated',
                'message_to_merchant' => 'Tu solicitud fue derivada a nuestro equipo para revisión.',
                'proposed_resolution' => null,
                'escalation_reason'   => 'out_of_policy',
            ];
        }

        return $decoded;
    }

    private function fakeResponse(string $message, array $range): array
    {
        $newPct = round($range['min_allowed_pct'] + ($range['current_pct'] - $range['min_allowed_pct']) / 2, 4);

        return [
            'status'              => 'approved_within_policy',
            'message_to_merchant' => 'Entendemos la situación. Vamos a reducir la retención temporalmente. Te avisamos cuando se aplique el ajuste.',
            'proposed_resolution' => [
                'action'           => 'reduce_pct',
                'new_holdback_pct' => $newPct,
                'pause_days'       => null,
                'extension_days'   => null,
            ],
            'escalation_reason' => null,
        ];
    }
}
