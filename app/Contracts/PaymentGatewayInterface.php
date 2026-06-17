<?php

namespace App\Contracts;

/**
 * Contrato de integración con pasarelas de pago / POS / wallets.
 *
 * Cada proveedor (MercadoPago, adquirente local, POS propio, etc.) implementa esta interfaz.
 * El HoldbackExecutionEngine no sabe qué proveedor usa — solo llama a esta interfaz.
 *
 * IMPORTANTE: estos métodos escriben sobre la pasarela — activan o cancelan retenciones reales.
 * Solo deben llamarse desde el HoldbackExecutionEngine, nunca directamente desde controladores.
 */
interface PaymentGatewayInterface
{
    /**
     * Nombre del proveedor (para logs y audit trail).
     */
    public function getName(): string;

    /**
     * Activa la retención de un % sobre cada venta entrante del comercio.
     *
     * @param  string $externalMerchantId  ID del comercio en el sistema de la pasarela
     * @param  float  $holdbackPct         Porcentaje a retener (0.0 - 1.0)
     * @param  string $referenceId         ID interno de Sumeria para trazabilidad
     * @return string                      ID de la retención activa en el sistema de la pasarela
     */
    public function activateHoldback(string $externalMerchantId, float $holdbackPct, string $referenceId): string;

    /**
     * Ajusta el % de una retención ya activa.
     *
     * @param  string $gatewayHoldbackId   ID de la retención activa devuelto por activateHoldback
     * @param  float  $newHoldbackPct
     */
    public function adjustHoldback(string $gatewayHoldbackId, float $newHoldbackPct): void;

    /**
     * Cancela una retención activa (caso cerrado o escalado a humano).
     *
     * @param  string $gatewayHoldbackId
     */
    public function cancelHoldback(string $gatewayHoldbackId): void;
}
