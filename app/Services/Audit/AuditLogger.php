<?php

namespace App\Services\Audit;

use App\Models\AuditLog;

/** Writes one row per important step (cart signed, checkout refused, ...). */
class AuditLogger
{
    public function log(string $event, ?string $subjectType = null, ?string $subjectId = null, array $data = []): void
    {
        AuditLog::create([
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'data' => $data ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
