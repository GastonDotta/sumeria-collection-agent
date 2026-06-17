<?php

namespace App\Services;

use App\Models\CollectionCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envía notificaciones informativas al comercio sobre el holdback activo.
 * Canal principal: WhatsApp Business API (Meta).
 *
 * IMPORTANTE: este servicio NOTIFICA — no negocia ni pide consentimiento.
 * La decisión ya fue tomada por el Orchestrator. El comercio recibe información
 * de lo que está pasando, no una propuesta a aceptar o rechazar.
 *
 * Configuración requerida en .env:
 *   WA_API_URL=https://graph.facebook.com/v19.0
 *   WA_API_TOKEN=...
 *   WA_PHONE_NUMBER_ID=...
 *
 * Los mensajes van siempre con la identidad del lender (whitelabel).
 * El número de teléfono del comercio se obtiene del ScoringApiClient (Sprint 5+).
 */
class NotificationService
{
    private string $apiUrl;
    private string $token;
    private string $phoneNumberId;

    public function __construct(private readonly AuditLogService $auditLog)
    {
        $this->apiUrl        = rtrim(config('services.whatsapp.api_url', ''), '/');
        $this->token         = config('services.whatsapp.token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
    }

    /**
     * Notifica al comercio que se activó el holdback sobre su flujo de ventas.
     */
    public function notifyHoldbackActivated(CollectionCase $case, string $merchantPhone, string $lenderName): void
    {
        $pct     = round((float) $case->current_holdback_pct * 100, 1);
        $days    = $case->estimated_recovery_days;

        $message = "Hola, te contactamos de parte de {$lenderName}. "
            . "Detectamos un atraso en tu cuota. Según lo acordado en tu contrato, "
            . "comenzamos a retener el {$pct}% de tus ventas diarias hasta regularizar el saldo pendiente "
            . "(estimado en {$days} días al ritmo actual de tus ventas). "
            . "Si tenés alguna consulta o situación particular, respondé este mensaje y te ayudamos.";

        $this->send($merchantPhone, $message);

        $this->auditLog->log($case->id, 'notification_sent', [
            'type'    => 'holdback_activated',
            'channel' => 'whatsapp',
            'pct'     => $pct,
        ]);
    }

    /**
     * Notifica al comercio un ajuste del % de holdback (por excepción aprobada o estacionalidad).
     */
    public function notifyHoldbackAdjusted(
        CollectionCase $case,
        string $merchantPhone,
        string $lenderName,
        float $previousPct,
        float $newPct,
        string $reason,
    ): void {
        $prev   = round($previousPct * 100, 1);
        $new    = round($newPct * 100, 1);

        $message = "Hola, te contactamos de parte de {$lenderName}. "
            . "Ajustamos la retención sobre tus ventas de {$prev}% a {$new}% ({$reason}). "
            . "Cualquier consulta, respondé este mensaje.";

        $this->send($merchantPhone, $message);

        $this->auditLog->log($case->id, 'notification_sent', [
            'type'         => 'holdback_adjusted',
            'channel'      => 'whatsapp',
            'previous_pct' => $prev,
            'new_pct'      => $new,
        ]);
    }

    /**
     * Notifica al comercio que su caso fue escalado a revisión humana.
     */
    public function notifyEscalated(CollectionCase $case, string $merchantPhone, string $lenderName): void
    {
        $message = "Hola, te contactamos de parte de {$lenderName}. "
            . "Tu situación requiere atención personalizada. "
            . "Un representante se va a comunicar con vos pronto para resolver el saldo pendiente.";

        $this->send($merchantPhone, $message);

        $this->auditLog->log($case->id, 'notification_sent', [
            'type'    => 'escalated',
            'channel' => 'whatsapp',
        ]);
    }

    /**
     * Notifica al comercio que la deuda fue recuperada y el holdback finalizado.
     */
    public function notifyClosed(CollectionCase $case, string $merchantPhone, string $lenderName): void
    {
        $message = "Hola, te contactamos de parte de {$lenderName}. "
            . "Tu saldo ha sido regularizado completamente. "
            . "La retención sobre tus ventas fue cancelada. ¡Gracias!";

        $this->send($merchantPhone, $message);

        $this->auditLog->log($case->id, 'notification_sent', [
            'type'    => 'closed_recovered',
            'channel' => 'whatsapp',
        ]);
    }

    private function send(string $phone, string $text): void
    {
        if (app()->environment(['local', 'testing'])) {
            Log::info("[NotificationService FAKE] → {$phone}: {$text}");
            return;
        }

        Http::withToken($this->token)
            ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => ['body' => $text],
            ])
            ->throw();
    }
}
