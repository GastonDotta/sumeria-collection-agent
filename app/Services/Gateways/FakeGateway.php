<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

/**
 * Gateway fake para desarrollo y tests.
 * No hace ninguna llamada HTTP real — simula respuestas de una pasarela.
 */
class FakeGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'fake';
    }

    public function activateHoldback(string $externalMerchantId, float $holdbackPct, string $referenceId): string
    {
        return 'fake_holdback_' . Str::random(8);
    }

    public function adjustHoldback(string $gatewayHoldbackId, float $newHoldbackPct): void
    {
        // no-op
    }

    public function cancelHoldback(string $gatewayHoldbackId): void
    {
        // no-op
    }
}
