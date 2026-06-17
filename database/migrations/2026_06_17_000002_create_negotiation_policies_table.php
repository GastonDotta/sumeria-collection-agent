<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiation_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lender_id')->unique();

            // Límites de holdback
            $table->decimal('min_holdback_pct', 5, 4)->comment('% mínimo de retención en mora temprana');
            $table->decimal('max_holdback_pct', 5, 4)->comment('% máximo de retención permitido por la institución');
            $table->decimal('max_default_rate', 5, 4)->comment('Tasa de mora máxima aplicable sobre saldo');

            // Límites de extensión y excepciones
            $table->unsignedInteger('max_recovery_extension_days')->comment('Plazo máximo de extensión en ajustes o excepciones');
            $table->unsignedInteger('max_exception_requests')->comment('Excepciones permitidas antes de escalar a humano');

            // Umbral de recuperación: debajo de esto, escalar directo sin holdback automático
            $table->decimal('min_recovery_threshold', 5, 4)->comment('Probabilidad mínima para activar holdback automático');

            // Restricciones de contacto/notificación (regulatorio)
            $table->time('contact_hours_start');
            $table->time('contact_hours_end');
            $table->string('jurisdiction', 10)->comment('Código de país — aplica reglas regulatorias específicas');

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('lender_id');
            $table->index('jurisdiction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiation_policies');
    }
};
