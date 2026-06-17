<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap #6 — Cap global de Sumeria, independiente de lo que configure cada lender.
 * Ningún lender puede superar este límite absoluto definido a nivel de plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiation_policies', function (Blueprint $table) {
            $table->decimal('platform_max_holdback_pct', 5, 4)
                ->default(0.30)
                ->after('max_holdback_pct')
                ->comment('Tope absoluto de Sumeria — ningún lender puede superar este valor');
        });
    }

    public function down(): void
    {
        Schema::table('negotiation_policies', function (Blueprint $table) {
            $table->dropColumn('platform_max_holdback_pct');
        });
    }
};
