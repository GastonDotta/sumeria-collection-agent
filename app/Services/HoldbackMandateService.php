<?php

namespace App\Services;

use App\Models\HoldbackMandate;

class HoldbackMandateService
{
    public function register(int $lenderId, array $data): HoldbackMandate
    {
        // Un préstamo solo puede tener un mandato activo a la vez
        HoldbackMandate::where('lender_id', $lenderId)
            ->where('loan_id', $data['loan_id'])
            ->update(['active' => false]);

        return HoldbackMandate::create(array_merge($data, [
            'lender_id' => $lenderId,
            'active' => true,
        ]));
    }

    /**
     * Devuelve el mandato activo del comercio para el préstamo dado.
     * Lanza excepción si no existe, porque ninguna retención puede ejecutarse sin mandato.
     */
    public function getActiveMandate(int $merchantId, int $lenderId, int $loanId): HoldbackMandate
    {
        $mandate = HoldbackMandate::where('merchant_id', $merchantId)
            ->where('lender_id', $lenderId)
            ->where('loan_id', $loanId)
            ->where('active', true)
            ->first();

        if (! $mandate) {
            throw new \DomainException(
                "No existe mandato de holdback activo para merchant {$merchantId}, loan {$loanId}."
            );
        }

        return $mandate;
    }
}
