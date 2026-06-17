<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holdback_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_case_id');
            $table->enum('triggered_by', ['system', 'exception_agreement']);
            $table->decimal('previous_holdback_pct', 5, 4)->nullable();
            $table->decimal('new_holdback_pct', 5, 4);
            $table->string('reason')->comment('mora_inicial, escalada_mora, ajuste_estacional, excepcion_aprobada');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('collection_case_id')->references('id')->on('collection_cases');
            $table->index('collection_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holdback_adjustments');
    }
};
