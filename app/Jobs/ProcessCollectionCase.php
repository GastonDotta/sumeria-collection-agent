<?php

namespace App\Jobs;

use App\Models\CollectionCase;
use App\Services\HoldbackOrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCollectionCase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // segundos entre reintentos

    public function __construct(public readonly int $collectionCaseId) {}

    public function handle(HoldbackOrchestratorService $orchestrator): void
    {
        $case = CollectionCase::findOrFail($this->collectionCaseId);

        // Si el caso ya fue procesado (no está en 'detected'), no hacer nada
        if ($case->status !== 'detected') {
            return;
        }

        $orchestrator->process($case);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessCollectionCase falló', [
            'case_id' => $this->collectionCaseId,
            'error'   => $e->getMessage(),
        ]);
    }
}
