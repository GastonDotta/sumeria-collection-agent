<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_case_id');
            $table->string('event_type', 50);
            $table->json('payload');
            // Solo INSERT permitido a nivel de aplicación — sin updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('collection_case_id')->references('id')->on('collection_cases');
            $table->index(['collection_case_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
