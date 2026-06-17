<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap #4 — Circuit breaker automático.
 * Rastrea cuántos días consecutivos el holdback activo dejó al comercio
 * por debajo del piso de liquidez operativa. Al llegar a 3 días, el sistema
 * reduce el % automáticamente o escala sin esperar a que el comercio pida excepción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_cases', function (Blueprint $table) {
            $table->unsignedInteger('consecutive_floor_breach_days')->default(0)
                ->after('estimated_recovery_days')
                ->comment('Días consecutivos que el holdback dejó al comercio bajo su piso operativo');
            $table->timestamp('last_floor_check_at')->nullable()->after('consecutive_floor_breach_days');
        });
    }

    public function down(): void
    {
        Schema::table('collection_cases', function (Blueprint $table) {
            $table->dropColumn(['consecutive_floor_breach_days', 'last_floor_check_at']);
        });
    }
};
