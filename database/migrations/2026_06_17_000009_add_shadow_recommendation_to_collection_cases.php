<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_cases', function (Blueprint $table) {
            // Recomendación del Decision Engine en shadow mode, pendiente de aprobación humana
            $table->json('shadow_recommendation')->nullable()->after('estimated_recovery_days')
                ->comment('Resultado del Decision Engine en shadow_mode, antes de activar holdback.');
            $table->timestamp('shadow_reviewed_at')->nullable()->after('shadow_recommendation');
            $table->string('shadow_reviewed_by')->nullable()->after('shadow_reviewed_at')
                ->comment('Usuario del lender que aprobó o rechazó la recomendación.');
        });
    }

    public function down(): void
    {
        Schema::table('collection_cases', function (Blueprint $table) {
            $table->dropColumn(['shadow_recommendation', 'shadow_reviewed_at', 'shadow_reviewed_by']);
        });
    }
};
