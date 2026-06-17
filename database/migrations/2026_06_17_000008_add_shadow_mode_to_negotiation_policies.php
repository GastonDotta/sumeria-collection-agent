<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiation_policies', function (Blueprint $table) {
            // En shadow mode el Decision Engine calcula todo pero no activa holdback real.
            // Un humano del lender debe aprobar cada recomendación antes de ejecutar.
            $table->boolean('shadow_mode')->default(true)->after('active')
                ->comment('true = solo calcula y loguea, no activa holdback. false = ejecución automática.');
        });
    }

    public function down(): void
    {
        Schema::table('negotiation_policies', function (Blueprint $table) {
            $table->dropColumn('shadow_mode');
        });
    }
};
