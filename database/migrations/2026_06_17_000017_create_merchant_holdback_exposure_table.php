<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap #7 — Exposición agregada por comercio.
 * Rastrea el total de % retenido sobre un comercio considerando TODOS sus préstamos
 * activos, no solo el del caso que se está procesando. Previene la acumulación
 * silenciosa cuando un comercio tiene deuda con múltiples lenders via Sumeria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_holdback_exposure', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id')->unique();
            $table->decimal('total_active_holdback_pct', 6, 4)->default(0)
                ->comment('Suma de todos los current_holdback_pct activos para este comercio');
            $table->unsignedInteger('active_cases_count')->default(0);
            $table->timestamp('last_recalculated_at');
            $table->timestamps();

            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_holdback_exposure');
    }
};
