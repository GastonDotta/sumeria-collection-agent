<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exception_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_case_id');
            $table->enum('requested_by', ['merchant']);
            $table->enum('request_type', ['pause', 'reduce_pct', 'extend_term', 'dispute']);
            $table->text('raw_message');
            $table->json('proposed_resolution')->nullable();
            $table->enum('status', [
                'pending',
                'approved_within_policy',
                'escalated',
                'rejected',
            ])->default('pending');
            $table->timestamps();
            $table->timestamp('resolved_at')->nullable();

            $table->foreign('collection_case_id')->references('id')->on('collection_cases');
            $table->index(['collection_case_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exception_requests');
    }
};
