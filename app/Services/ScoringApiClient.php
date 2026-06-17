<?php

namespace App\Services;

use App\Contracts\ScoringApiInterface;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;

/**
 * Implementación real que consume el API interno de Sumeria scoring.
 * En Sprint 3-4 puede usarse el Fake (ver ScoringApiFake) mientras
 * se acuerda el contrato exacto con el equipo de scoring.
 *
 * Configuración requerida en .env:
 *   SUMERIA_SCORING_API_URL=https://api-interna.sumeria.io
 *   SUMERIA_SCORING_WEBHOOK_SECRET=...
 */
class ScoringApiClient implements ScoringApiInterface
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.sumeria_scoring.url', ''), '/');
        $this->secret  = config('services.sumeria_scoring.secret', '');
    }

    public function getAverageDailySales(int $merchantId, int $lookbackDays = 30): float
    {
        $response = Http::withToken($this->secret)
            ->get("{$this->baseUrl}/internal/merchants/{$merchantId}/sales-summary", [
                'lookback_days' => $lookbackDays,
            ])
            ->throw();

        return (float) $response->json('average_daily_sales');
    }

    public function getPaymentHistory(int $merchantId, int $lookbackDays = 180): array
    {
        $response = Http::withToken($this->secret)
            ->get("{$this->baseUrl}/internal/merchants/{$merchantId}/payment-history", [
                'lookback_days' => $lookbackDays,
            ])
            ->throw();

        return $response->json();
    }

    public function getSalesTrend(int $merchantId): float
    {
        $response = Http::withToken($this->secret)
            ->get("{$this->baseUrl}/internal/merchants/{$merchantId}/sales-trend")
            ->throw();

        return (float) $response->json('trend_factor');
    }
}
