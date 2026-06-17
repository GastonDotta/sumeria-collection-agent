<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lenders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->comment('Identificador legible: ej. banco-xyz');
            $table->string('jurisdiction', 10)->comment('País principal de operación');
            $table->string('contact_email');
            $table->boolean('active')->default(true);
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lenders');
    }
};
