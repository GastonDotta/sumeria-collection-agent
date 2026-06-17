<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    public function log(int $collectionCaseId, string $eventType, array $payload): AuditLog
    {
        return AuditLog::create([
            'collection_case_id' => $collectionCaseId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }
}
