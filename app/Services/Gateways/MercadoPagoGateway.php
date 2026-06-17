<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

/**
 * Integración con MercadoPago Marketplace / Split de pagos.
 *
 * MercadoPago permite retener un % de cada transacción de un vendedor
 * (split de pagos) en favor del marketplace/acreedor, mediante la API
 * de Money Release o Marketplace Splits.
 *
 * Documentación: https://www.mercadopago.com.ar/developers/es/docs/marketplace
 *
 * Configuración requerida en .env:
 *   MERCADOPAGO_ACCESS_TOKEN=...
 *   MERCADOPAGO_API_URL=https://api.mercadopago.com
 */
class MercadoPagoGateway implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl     = config('services.mercadopago.api_url', 'https://api.mercadopago.com');
        $this->accessToken = config('services.mercadopago.access_token', '');
    }

    public function getName(): string
    {
        return 'mercadopago';
    }

    public function activateHoldback(string $externalMerchantId, float $holdbackPct, string $referenceId): string
    {
        $response = Http::withToken($this->accessToken)
            ->post("{$this->baseUrl}/v1/holdbacks", [
                'collector_id'    => $externalMerchantId,
                'split_ratio'     => $holdbackPct,
                'external_ref'    => $referenceId,
                'reason'          => 'loan_collection',
            ])
            ->throw();

        return (string) $response->json('id');
    }

    public function adjustHoldback(string $gatewayHoldbackId, float $newHoldbackPct): void
    {
        Http::withToken($this->accessToken)
            ->patch("{$this->baseUrl}/v1/holdbacks/{$gatewayHoldbackId}", [
                'split_ratio' => $newHoldbackPct,
            ])
            ->throw();
    }

    public function cancelHoldback(string $gatewayHoldbackId): void
    {
        Http::withToken($this->accessToken)
            ->delete("{$this->baseUrl}/v1/holdbacks/{$gatewayHoldbackId}")
            ->throw();
    }
}
