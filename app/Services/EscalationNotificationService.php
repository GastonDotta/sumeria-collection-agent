<?php

namespace App\Services;

use App\Models\CollectionCase;
use App\Models\LenderWebhookConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EscalationNotificationService
{
    public function notify(CollectionCase $case): void
    {
        $payload = [
            'case_id'              => $case->id,
            'merchant_id'          => $case->merchant_id,
            'lender_id'            => $case->lender_id,
            'amount_due'           => $case->amount_due,
            'days_overdue'         => $case->days_overdue,
            'score_at_detection'   => $case->score_at_detection,
            'recovery_probability' => $case->recovery_probability,
            'escalation_reason'    => $case->escalation?->reason,
            'escalated_at'         => now()->toIso8601String(),
            'case_url'             => url("/api/v1/collection-cases/{$case->id}"),
        ];

        $config = LenderWebhookConfig::where('lender_id', $case->lender_id)
            ->where('active', true)
            ->first();

        if (! $config?->escalation_webhook_url) {
            Log::warning("EscalationNotificationService: no hay webhook configurado para lender {$case->lender_id}");
            return;
        }

        if (app()->environment(['local', 'testing'])) {
            Log::info('[EscalationNotificationService FAKE]', $payload);
            return;
        }

        $request = Http::withHeaders($this->buildHeaders($config, $payload));
        $request->post($config->escalation_webhook_url, $payload)->throw();
    }

    private function buildHeaders(LenderWebhookConfig $config, array $payload): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($config->webhook_secret) {
            $signature = hash_hmac('sha256', json_encode($payload), $config->webhook_secret);
            $headers['X-Sumeria-Signature'] = "sha256={$signature}";
        }

        return $headers;
    }
}
