<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_cases', function (Blueprint $table) {
            // ID de la retención activa en el sistema de la pasarela.
            // Nulo si shadow_mode o si aún no se activó.
            $table->string('gateway_holdback_id')->nullable()->after('current_holdback_pct');
            $table->string('gateway_provider')->nullable()->after('gateway_holdback_id')
                ->comment('Nombre del proveedor: mercadopago, pos_x, etc.');
        });
    }

    public function down(): void
    {
        Schema::table('collection_cases', function (Blueprint $table) {
            $table->dropColumn(['gateway_holdback_id', 'gateway_provider']);
        });
    }
};
