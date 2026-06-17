<?php

namespace App\Services;

use App\Contracts\ScoringApiInterface;

/**
 * Implementación fake para desarrollo y tests.
 * Devuelve datos sintéticos plausibles basados en el merchant_id.
 * Activar en AppServiceProvider cuando APP_ENV=local o testing.
 */
class ScoringApiFake implements ScoringApiInterface
{
    public function getAverageDailySales(int $merchantId, int $lookbackDays = 30): float
    {
        // Semilla determinista por merchant para que los tests sean reproducibles
        srand($merchantId * 7);
        return round(rand(15000, 250000) / 100, 2);
    }

    public function getPaymentHistory(int $merchantId, int $lookbackDays = 180): array
    {
        srand($merchantId * 13);
        $total   = rand(6, 24);
        $default = rand(0, 2);
        $late    = rand(0, 4);
        return [
            'on_time' => $total - $late - $default,
            'late'    => $late,
            'default' => $default,
        ];
    }

    public function getSalesTrend(int $merchantId): float
    {
        srand($merchantId * 31);
        return round((rand(-100, 100) / 100), 2);
    }
}
