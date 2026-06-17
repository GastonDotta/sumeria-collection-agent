<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lender_webhook_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lender_id')->unique();
            $table->string('escalation_webhook_url')->nullable()
                ->comment('URL del CRM/sistema del lender que recibe casos escalados');
            $table->string('agreement_webhook_url')->nullable()
                ->comment('URL que recibe notificación cuando el agente cierra un caso');
            $table->string('webhook_secret')->nullable()
                ->comment('Secret para firmar el payload (HMAC-SHA256)');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('lender_id')->references('id')->on('lenders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_webhook_configs');
    }
};
