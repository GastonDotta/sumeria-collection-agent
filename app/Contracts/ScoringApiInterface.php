<?php

namespace App\Contracts;

/**
 * Contrato de solo lectura con el motor de scoring existente de Sumeria.
 * Este servicio nunca escribe sobre el scoring — solo consume datos.
 *
 * La implementación real (ScoringApiClient) consume la API interna de Sumeria.
 * En tests se usa un fake que devuelve datos sintéticos.
 */
interface ScoringApiInterface
{
    /**
     * Devuelve el flujo promedio de ventas diarias del comercio en los últimos N días.
     * Usado por el Decision Engine para calcular el % de holdback viable y el plazo estimado.
     *
     * @return float Monto promedio de ventas diarias (en la moneda base del comercio)
     */
    public function getAverageDailySales(int $merchantId, int $lookbackDays = 30): float;

    /**
     * Devuelve el historial de comportamiento de pago del comercio (cuántas cuotas
     * pagó a tiempo, cuántas con atraso, cuántas en default) en el período dado.
     *
     * @return array{on_time: int, late: int, default: int}
     */
    public function getPaymentHistory(int $merchantId, int $lookbackDays = 180): array;

    /**
     * Devuelve la tendencia de ventas del comercio (positiva, estable o negativa)
     * comparando los últimos 30 días vs los 30 anteriores.
     *
     * @return float Factor entre -1.0 (caída total) y 1.0 (crecimiento máximo)
     */
    public function getSalesTrend(int $merchantId): float;
}
