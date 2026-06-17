<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap #3 — Reversión real de fondos retenidos.
 * No solo un cambio de estado en la DB: rastrea el monto, el método y la ejecución
 * real contra la pasarela dentro de la ventana de 24-48hs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holdback_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_case_id')->constrained('collection_cases');
            $table->foreignId('holdback_adjustment_id')->nullable()->constrained('holdback_adjustments')
                ->comment('Ajuste específico que se revierte; null si se revierte el holdback inicial');

            $table->decimal('amount_to_refund', 12, 2);
            $table->enum('refund_method', [
                'credit_next_settlement', // Crédito en la próxima liquidación
                'direct_transfer',        // Transferencia directa al comercio
                'balance_adjustment',     // Ajuste de saldo en la plataforma
            ]);
            $table->string('reason');
            $table->string('initiated_by')->comment('Email del operador o "system"');

            // Estado de ejecución contra la pasarela
            $table->enum('status', ['pending', 'executed', 'failed'])->default('pending');
            $table->timestamp('executed_at')->nullable();
            $table->string('gateway_reversal_id')->nullable();
            $table->text('gateway_response')->nullable();

            $table->timestamps();

            $table->index(['collection_case_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holdback_reversals');
    }
};
