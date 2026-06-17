<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_case_id');
            $table->enum('reason', [
                'out_of_policy',
                'insufficient_sales_flow',
                'high_risk_score',
                'merchant_dispute',
                'mandate_limit_reached',
            ]);
            $table->string('assigned_to')->nullable()->comment('Referencia al usuario/equipo humano del lender');
            $table->timestamps();
            $table->timestamp('resolved_at')->nullable();

            $table->foreign('collection_case_id')->references('id')->on('collection_cases');
            $table->index('collection_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
