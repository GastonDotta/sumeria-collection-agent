<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\CollectionCase;
use App\Models\HoldbackMandate;

/**
 * Ejecuta las retenciones sobre las pasarelas de pago reales.
 * Es la única capa que habla con PaymentGatewayInterface — el Orchestrator
 * coordina cuándo llamar al ExecutionEngine, pero nunca llama a la pasarela directamente.
 *
 * Resolución de gateway: usa los `payment_channels` del mandato del comercio
 * para saber en qué proveedor(es) activar la retención. Si un canal no tiene
 * gateway registrado, se loguea como no soportado y se continúa con el resto.
 */
class HoldbackExecutionEngine
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways;

    public function __construct(private readonly AuditLogService $auditLog)
    {
        $this->gateways = $this->resolveGateways();
    }

    /**
     * Activa la retención en todos los canales del mandato que tengan gateway registrado.
     * Devuelve el gateway/id de la primera retención exitosa (para el caso con un solo canal).
     */
    public function activate(CollectionCase $case, float $holdbackPct): void
    {
        $mandate    = $case->mandate;
        $referenceId = "case_{$case->id}";

        foreach ($mandate->payment_channels as $channel) {
            $gateway = $this->gateways[$channel] ?? null;

            if (! $gateway) {
                $this->auditLog->log($case->id, 'gateway_not_supported', ['channel' => $channel]);
                continue;
            }

            $gatewayHoldbackId = $gateway->activateHoldback(
                (string) $mandate->merchant_id,
                $holdbackPct,
                $referenceId,
            );

            // Guardamos el primer gateway que responde exitosamente
            if (! $case->gateway_holdback_id) {
                $case->update([
                    'gateway_holdback_id' => $gatewayHoldbackId,
                    'gateway_provider'    => $gateway->getName(),
                ]);
            }

            $this->auditLog->log($case->id, 'gateway_holdback_activated', [
                'provider'           => $gateway->getName(),
                'gateway_holdback_id' => $gatewayHoldbackId,
                'holdback_pct'       => $holdbackPct,
            ]);
        }
    }

    /**
     * Ajusta el % en la pasarela cuando el Orchestrator cambia el holdback activo.
     */
    public function adjust(CollectionCase $case, float $newPct): void
    {
        if (! $case->gateway_holdback_id) {
            return;
        }

        $gateway = $this->gateways[$case->gateway_provider] ?? null;

        if (! $gateway) {
            return;
        }

        $gateway->adjustHoldback($case->gateway_holdback_id, $newPct);

        $this->auditLog->log($case->id, 'gateway_holdback_adjusted', [
            'provider'           => $case->gateway_provider,
            'gateway_holdback_id' => $case->gateway_holdback_id,
            'new_pct'            => $newPct,
        ]);
    }

    /**
     * Cancela la retención al cerrar o escalar el caso.
     */
    public function cancel(CollectionCase $case): void
    {
        if (! $case->gateway_holdback_id) {
            return;
        }

        $gateway = $this->gateways[$case->gateway_provider] ?? null;

        if (! $gateway) {
            return;
        }

        $gateway->cancelHoldback($case->gateway_holdback_id);

        $this->auditLog->log($case->id, 'gateway_holdback_cancelled', [
            'provider'           => $case->gateway_provider,
            'gateway_holdback_id' => $case->gateway_holdback_id,
        ]);
    }

    /**
     * Mapa de channel_name → implementación de gateway.
     * Agregar nuevos proveedores acá al incorporarlos.
     */
    private function resolveGateways(): array
    {
        if (app()->environment(['local', 'testing'])) {
            return [
                'mercadopago' => new \App\Services\Gateways\FakeGateway(),
                'fake'        => new \App\Services\Gateways\FakeGateway(),
            ];
        }

        return [
            'mercadopago' => new \App\Services\Gateways\MercadoPagoGateway(),
            // 'pos_x'    => new \App\Services\Gateways\PosXGateway(),
        ];
    }
}
