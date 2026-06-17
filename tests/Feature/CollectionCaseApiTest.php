<?php

use App\Models\CollectionCase;
use App\Models\Lender;
use App\Models\HoldbackMandate;
use App\Models\NegotiationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLenderWithToken(): array
{
    $lender = Lender::create([
        'name'          => 'Banco Test',
        'slug'          => 'banco-test',
        'jurisdiction'  => 'UY',
        'contact_email' => 'test@banco.uy',
        'active'        => true,
        'onboarded_at'  => now(),
    ]);

    NegotiationPolicy::create([
        'lender_id'                   => $lender->id,
        'min_holdback_pct'            => 0.05,
        'max_holdback_pct'            => 0.20,
        'platform_max_holdback_pct'   => 0.30,
        'max_default_rate'            => 0.015,
        'max_recovery_extension_days' => 90,
        'max_exception_requests'      => 3,
        'min_recovery_threshold'      => 0.40,
        'contact_hours_start'         => '09:00',
        'contact_hours_end'           => '20:00',
        'jurisdiction'                => 'UY',
        'active'                      => true,
        'shadow_mode'                 => true,
    ]);

    $token = $lender->createToken('test-token')->plainTextToken;

    return ['lender' => $lender, 'token' => $token];
}

function createMandate(int $lenderId, int $merchantId = 4521, int $loanId = 7710): HoldbackMandate
{
    return HoldbackMandate::create([
        'merchant_id'                => $merchantId,
        'lender_id'                  => $lenderId,
        'loan_id'                    => $loanId,
        'authorized_max_holdback_pct' => 0.20,
        'merchant_operating_floor'   => 0,
        'payment_channels'           => ['fake'],
        'contract_clause_ref'        => 'anexo-3.2',
        'legal_instrument_type'      => 'contract_clause',
        'validated_by_legal'         => true,
        'validated_by_legal_at'      => now(),
        'legal_validation_ref'       => 'dictamen-2026-001',
        'signed_at'                  => now(),
        'active'                     => true,
    ]);
}

// --- Policy Engine ---

test('POST negotiation-policy crea política correctamente', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();

    $response = $this->withToken($token)
        ->postJson("/api/v1/lenders/{$lender->id}/negotiation-policy", [
            'min_holdback_pct'            => 0.05,
            'max_holdback_pct'            => 0.20,
            'max_default_rate'            => 0.015,
            'max_recovery_extension_days' => 90,
            'max_exception_requests'      => 3,
            'min_recovery_threshold'      => 0.40,
            'contact_hours_start'         => '09:00',
            'contact_hours_end'           => '20:00',
            'jurisdiction'               => 'UY',
        ]);

    $response->assertStatus(201)->assertJsonStructure(['policy_id']);
});

test('POST negotiation-policy rechaza max menor que min', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();

    $this->withToken($token)
        ->postJson("/api/v1/lenders/{$lender->id}/negotiation-policy", [
            'min_holdback_pct' => 0.20,
            'max_holdback_pct' => 0.05, // max < min → inválido
            'max_default_rate'            => 0.015,
            'max_recovery_extension_days' => 90,
            'max_exception_requests'      => 3,
            'min_recovery_threshold'      => 0.40,
            'contact_hours_start'         => '09:00',
            'contact_hours_end'           => '20:00',
            'jurisdiction'               => 'UY',
        ])
        ->assertStatus(422);
});

// --- Holdback Mandates ---

test('POST holdback-mandates registra un mandato', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();

    $this->withToken($token)
        ->postJson("/api/v1/lenders/{$lender->id}/holdback-mandates", [
            'merchant_id'                => 4521,
            'loan_id'                    => 7710,
            'authorized_max_holdback_pct' => 0.20,
            'payment_channels'           => ['fake'],
            'contract_clause_ref'        => 'anexo-3.2',
            'signed_at'                  => now()->toIso8601String(),
        ])
        ->assertStatus(201)
        ->assertJsonStructure(['mandate_id']);
});

// --- Collection Cases ---

test('POST collection-cases crea caso cuando hay mandato y política activos', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();
    createMandate($lender->id);

    $response = $this->withToken($token)
        ->postJson('/api/v1/collection-cases', [
            'merchant_id'        => 4521,
            'lender_id'          => $lender->id,
            'loan_id'            => 7710,
            'amount_due'         => 85000.00,
            'days_overdue'       => 7,
            'score_at_detection' => 612,
        ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['status' => 'detected']);
});

test('POST collection-cases falla sin mandato activo', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();
    // No creamos mandato

    $this->withToken($token)
        ->postJson('/api/v1/collection-cases', [
            'merchant_id'        => 9999,
            'lender_id'          => $lender->id,
            'loan_id'            => 1111,
            'amount_due'         => 50000.00,
            'days_overdue'       => 7,
            'score_at_detection' => 600,
        ])
        ->assertStatus(500); // DomainException → no existe mandato
});

test('GET collection-cases devuelve estado del caso', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();
    createMandate($lender->id);

    $createResponse = $this->withToken($token)
        ->postJson('/api/v1/collection-cases', [
            'merchant_id'        => 4521,
            'lender_id'          => $lender->id,
            'loan_id'            => 7710,
            'amount_due'         => 50000.00,
            'days_overdue'       => 5,
            'score_at_detection' => 700,
        ]);

    $caseId = $createResponse->json('case_id');

    $this->withToken($token)
        ->getJson("/api/v1/collection-cases/{$caseId}")
        ->assertStatus(200)
        ->assertJsonStructure(['case_id', 'status']);
});

test('POST escalate transiciona el caso a escalated', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();
    createMandate($lender->id);

    $case = CollectionCase::create([
        'merchant_id'         => 4521,
        'lender_id'           => $lender->id,
        'holdback_mandate_id' => HoldbackMandate::first()->id,
        'score_at_detection'  => 400,
        'amount_due'          => 50000,
        'days_overdue'        => 30,
        'status'              => 'detected',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/collection-cases/{$case->id}/escalate", [
            'reason' => 'high_risk_score',
        ])
        ->assertStatus(200)
        ->assertJsonFragment(['status' => 'escalated']);

    expect($case->fresh()->status)->toBe('escalated');
});

// --- Shadow Review ---

test('shadow approve transiciona el caso según la recomendación del Decision Engine', function () {
    ['lender' => $lender, 'token' => $token] = createLenderWithToken();
    createMandate($lender->id);

    // Crear caso con shadow_recommendation simulada
    $case = CollectionCase::create([
        'merchant_id'         => 4521,
        'lender_id'           => $lender->id,
        'holdback_mandate_id' => HoldbackMandate::first()->id,
        'score_at_detection'  => 700,
        'amount_due'          => 50000,
        'days_overdue'        => 7,
        'status'              => 'detected',
        'shadow_recommendation' => [
            'action'               => 'activate_holdback',
            'recovery_probability' => 0.75,
            'holdback_pct'         => 0.10,
            'estimated_recovery_days' => 40,
            'escalation_reason'    => null,
        ],
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/collection-cases/{$case->id}/shadow-reviews/approve", [
            'reviewed_by' => 'ana.garcia@banco.uy',
        ])
        ->assertStatus(200);

    expect($case->fresh()->status)->toBeIn(['holdback_active', 'escalated']);
});

test('sin autenticación los endpoints retornan 401', function () {
    $this->getJson('/api/v1/collection-cases/1')->assertStatus(401);
    $this->postJson('/api/v1/collection-cases', [])->assertStatus(401);
});
