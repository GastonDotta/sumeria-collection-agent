<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('lender_id');
            $table->unsignedBigInteger('holdback_mandate_id');

            // Snapshot del score al momento de detección
            $table->unsignedInteger('score_at_detection');
            $table->decimal('amount_due', 15, 2);
            $table->unsignedInteger('days_overdue');

            $table->enum('status', [
                'detected',
                'holdback_active',
                'holdback_adjusted',
                'exception_pending',
                'escalated',
                'closed_recovered',
                'closed_default',
            ])->default('detected');

            $table->decimal('recovery_probability', 5, 4)->nullable();
            $table->decimal('current_holdback_pct', 5, 4)->nullable();
            $table->unsignedInteger('estimated_recovery_days')->nullable();

            $table->timestamps();

            $table->index('merchant_id');
            $table->index('lender_id');
            $table->index('status');
            $table->foreign('holdback_mandate_id')->references('id')->on('holdback_mandates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_cases');
    }
};
