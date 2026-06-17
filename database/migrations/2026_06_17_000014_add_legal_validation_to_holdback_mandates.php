<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap #1 — Validez del mandato por jurisdicción.
 *
 * Ningún holdback real puede activarse hasta que validated_by_legal = true.
 * El tipo de instrumento define el nivel de formalidad requerido por cada país.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdback_mandates', function (Blueprint $table) {
            // Tipo de instrumento legal que respalda el mandato
            $table->enum('legal_instrument_type', [
                'contract_clause',      // Cláusula dentro del contrato de préstamo
                'separate_instrument',  // Documento separado firmado por el comercio
                'registered_assignment' // Cesión de derechos inscripta en registro público
            ])->default('contract_clause')->after('contract_clause_ref');

            // El Policy Engine bloquea la activación si esto es false
            $table->boolean('validated_by_legal')->default(false)->after('legal_instrument_type');
            $table->timestamp('validated_by_legal_at')->nullable()->after('validated_by_legal');
            $table->string('legal_validation_ref')->nullable()->after('validated_by_legal_at')
                ->comment('Número de dictamen o referencia al documento legal de validación');

            // Piso de liquidez mínima del comercio (Gap #4)
            // Calculado por el scoring engine en la originación del préstamo
            $table->decimal('merchant_operating_floor', 12, 2)->default(0)
                ->after('authorized_max_holdback_pct')
                ->comment('Monto diario mínimo que debe quedar libre después del holdback');

            $table->index('validated_by_legal');
        });
    }

    public function down(): void
    {
        Schema::table('holdback_mandates', function (Blueprint $table) {
            $table->dropColumn([
                'legal_instrument_type',
                'validated_by_legal',
                'validated_by_legal_at',
                'legal_validation_ref',
                'merchant_operating_floor',
            ]);
        });
    }
};
