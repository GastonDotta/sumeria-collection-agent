<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holdback_mandates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('lender_id');
            $table->unsignedBigInteger('loan_id');
            // % máximo autorizado por el comercio en el contrato de préstamo
            $table->decimal('authorized_max_holdback_pct', 5, 4);
            // Canales de pago sobre los que aplica la retención (POS, wallet, gateway)
            $table->json('payment_channels');
            // Referencia a la cláusula contractual donde consta la autorización
            $table->string('contract_clause_ref');
            $table->timestamp('signed_at');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('merchant_id');
            $table->index('lender_id');
            $table->index(['loan_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holdback_mandates');
    }
};
