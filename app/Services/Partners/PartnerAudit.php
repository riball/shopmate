<?php

namespace App\Services\Partners;

use App\Models\AuditLog;
use App\Models\Partner;
use Illuminate\Http\Request;

/** Writes partner events to the shared audit log, in one consistent shape. */
final class PartnerAudit
{
    public function rejected(Request $request, PartnerRejected $rejection, ?Partner $partner, string $requestId): void
    {
        $key = (string) $request->headers->get('X-Partner-Key', '');

        $this->write("partner.rejected.{$rejection->reason}", $partner, $request, [
            'request_id' => $requestId,
            'key' => $partner ? $partner->key_id : ($key === '' ? null : substr($key, 0, 12).'…'),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ]);
    }

    public function record(string $event, ?Partner $partner, ?Request $request, array $data = []): void
    {
        $this->write($event, $partner, $request, $data);
    }

    private function write(string $event, ?Partner $partner, ?Request $request, array $data): void
    {
        AuditLog::create([
            'event' => substr($event, 0, 60),
            'subject_type' => 'partner',
            'subject_id' => $partner?->id,
            'data' => $data,
            'ip' => $request?->ip(),
        ]);
    }
}
