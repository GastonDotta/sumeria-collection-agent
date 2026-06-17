<?php

namespace Database\Seeders;

use App\Models\Lender;
use App\Models\LenderWebhookConfig;
use App\Models\NegotiationPolicy;
use Illuminate\Database\Seeder;

/**
 * Crea el lender piloto con su política de holdback y webhook configurados.
 * Correr con: php artisan db:seed --class=PilotLenderSeeder
 */
class PilotLenderSeeder extends Seeder
{
    public function run(): void
    {
        $lender = Lender::updateOrCreate(
            ['slug' => 'banco-piloto'],
            [
                'name'          => 'Banco Piloto UY',
                'jurisdiction'  => 'UY',
                'contact_email' => 'tech@banco-piloto.uy',
                'active'        => true,
                'onboarded_at'  => now(),
            ]
        );

        // Política de holdback — arranca en shadow_mode=true para el piloto
        NegotiationPolicy::updateOrCreate(
            ['lender_id' => $lender->id],
            [
                'min_holdback_pct'            => 0.05,
                'max_holdback_pct'            => 0.20,
                'max_default_rate'            => 0.015,
                'max_recovery_extension_days' => 90,
                'max_exception_requests'      => 3,
                'min_recovery_threshold'      => 0.40,
                'contact_hours_start'         => '09:00',
                'contact_hours_end'           => '20:00',
                'jurisdiction'               => 'UY',
                'active'                      => true,
                'shadow_mode'                 => true,
            ]
        );

        LenderWebhookConfig::updateOrCreate(
            ['lender_id' => $lender->id],
            [
                'escalation_webhook_url' => 'https://webhook.site/piloto-escalations',
                'agreement_webhook_url'  => null,
                'webhook_secret'         => 'piloto-secret-changeme',
                'active'                 => true,
            ]
        );

        // Token de acceso para el piloto
        $token = $lender->createToken('piloto-api-token');

        $this->command->info("Lender piloto creado. ID: {$lender->id}");
        $this->command->warn("Token (guardar ahora, no se puede recuperar):");
        $this->command->line($token->plainTextToken);
    }
}
