<?php

use App\Models\CollectionCase;
use App\Models\HoldbackMandate;
use App\Models\NegotiationPolicy;
use App\Services\DecisionEngineService;
use App\Services\ScoringApiFake;

beforeEach(function () {
    $this->engine = new DecisionEngineService(new ScoringApiFake());
});

function makeCase(int $score, float $amountDue, int $daysOverdue, int $merchantId = 1): CollectionCase
{
    $case = new CollectionCase([
        'merchant_id'        => $merchantId,
        'lender_id'          => 1,
        'score_at_detection' => $score,
        'amount_due'         => $amountDue,
        'days_overdue'       => $daysOverdue,
    ]);
    return $case;
}

function makePolicy(float $minPct = 0.05, float $maxPct = 0.20, float $minThreshold = 0.40): NegotiationPolicy
{
    return new NegotiationPolicy([
        'min_holdback_pct'            => $minPct,
        'max_holdback_pct'            => $maxPct,
        'max_default_rate'            => 0.015,
        'max_recovery_extension_days' => 90,
        'min_recovery_threshold'      => $minThreshold,
    ]);
}

function makeMandate(float $authorizedMax = 0.20): HoldbackMandate
{
    return new HoldbackMandate([
        'merchant_id'                => 1,
        'authorized_max_holdback_pct' => $authorizedMax,
    ]);
}

test('comercio con score alto recibe recomendación de activar holdback', function () {
    // Deuda pequeña (300) para que sea recuperable en <90 días con ventas fake (~150-2500/día)
    $result = $this->engine->evaluate(
        makeCase(score: 750, amountDue: 300, daysOverdue: 10),
        makePolicy(),
        makeMandate(),
    );

    expect($result['action'])->toBe('activate_holdback')
        ->and($result['recovery_probability'])->toBeGreaterThan(0.4)
        ->and($result['holdback_pct'])->toBeGreaterThanOrEqual(0.05)
        ->and($result['holdback_pct'])->toBeLessThanOrEqual(0.20)
        ->and($result['estimated_recovery_days'])->toBeGreaterThan(0);
});

test('comercio con score muy bajo escala por high_risk_score', function () {
    // Score 100 → probability muy baja → debajo del threshold 0.40
    $result = $this->engine->evaluate(
        makeCase(score: 100, amountDue: 50000, daysOverdue: 10),
        makePolicy(minThreshold: 0.70), // threshold alto
        makeMandate(),
    );

    expect($result['action'])->toBe('escalate')
        ->and($result['escalation_reason'])->toBe('high_risk_score');
});

test('holdback_pct nunca supera el máximo de la política', function () {
    $result = $this->engine->evaluate(
        makeCase(score: 900, amountDue: 100, daysOverdue: 60),
        makePolicy(maxPct: 0.15),
        makeMandate(authorizedMax: 0.20),
    );

    // Si se activa (no escala), el pct debe respetar el máximo
    expect($result['action'])->toBeIn(['activate_holdback', 'escalate']);
    if ($result['action'] === 'activate_holdback') {
        expect($result['holdback_pct'])->toBeLessThanOrEqual(0.15);
    }
});

test('holdback_pct nunca supera el máximo autorizado en el mandato', function () {
    $result = $this->engine->evaluate(
        makeCase(score: 900, amountDue: 100, daysOverdue: 60),
        makePolicy(maxPct: 0.25),
        makeMandate(authorizedMax: 0.10), // mandato más restrictivo
    );

    expect($result['action'])->toBeIn(['activate_holdback', 'escalate']);
    if ($result['action'] === 'activate_holdback') {
        expect($result['holdback_pct'])->toBeLessThanOrEqual(0.10);
    }
});

test('holdback_pct siempre es mayor o igual al mínimo de la política', function () {
    $result = $this->engine->evaluate(
        makeCase(score: 800, amountDue: 1000, daysOverdue: 5), // deuda pequeña
        makePolicy(minPct: 0.05),
        makeMandate(),
    );

    if ($result['action'] === 'activate_holdback') {
        expect($result['holdback_pct'])->toBeGreaterThanOrEqual(0.05);
    }
});

test('recovery_probability está entre 0 y 1', function () {
    foreach ([100, 300, 500, 750, 900] as $score) {
        $result = $this->engine->evaluate(
            makeCase(score: $score, amountDue: 50000, daysOverdue: 15, merchantId: $score),
            makePolicy(),
            makeMandate(),
        );
        expect($result['recovery_probability'])->toBeGreaterThanOrEqual(0.0)
            ->and($result['recovery_probability'])->toBeLessThanOrEqual(1.0);
    }
});
