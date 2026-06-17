<?php

use App\Models\NegotiationPolicy;
use App\Services\PolicyEngineService;

beforeEach(function () {
    $this->service = new PolicyEngineService();
});

function makeNegotiationPolicy(float $min, float $max): NegotiationPolicy
{
    return new NegotiationPolicy([
        'min_holdback_pct' => $min,
        'max_holdback_pct' => $max,
    ]);
}

test('holdback dentro del rango no lanza excepción', function () {
    expect(fn () => $this->service->assertHoldbackWithinLimits(0.10, makeNegotiationPolicy(0.05, 0.20), 0.20))
        ->not->toThrow(\DomainException::class);
});

test('holdback exactamente en el mínimo es válido', function () {
    expect(fn () => $this->service->assertHoldbackWithinLimits(0.05, makeNegotiationPolicy(0.05, 0.20), 0.20))
        ->not->toThrow(\DomainException::class);
});

test('holdback exactamente en el máximo de la política es válido', function () {
    expect(fn () => $this->service->assertHoldbackWithinLimits(0.20, makeNegotiationPolicy(0.05, 0.20), 0.20))
        ->not->toThrow(\DomainException::class);
});

test('holdback por debajo del mínimo lanza DomainException', function () {
    expect(fn () => $this->service->assertHoldbackWithinLimits(0.02, makeNegotiationPolicy(0.05, 0.20), 0.20))
        ->toThrow(\DomainException::class);
});

test('holdback por encima del máximo de la política lanza DomainException', function () {
    expect(fn () => $this->service->assertHoldbackWithinLimits(0.25, makeNegotiationPolicy(0.05, 0.20), 0.30))
        ->toThrow(\DomainException::class);
});

test('holdback respeta el máximo del mandato cuando es más restrictivo que la política', function () {
    expect(fn () => $this->service->assertHoldbackWithinLimits(0.15, makeNegotiationPolicy(0.05, 0.20), mandateMaxPct: 0.10))
        ->toThrow(\DomainException::class);
});

test('holdback válido cuando el mandato es más restrictivo y se respeta', function () {
    expect(fn () => $this->service->assertHoldbackWithinLimits(0.08, makeNegotiationPolicy(0.05, 0.20), mandateMaxPct: 0.10))
        ->not->toThrow(\DomainException::class);
});
