<?php

namespace App\Console\Commands;

use App\Models\CartMandate;
use App\Models\SearchSession;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

/**
 * Scheduled daily: keeps tables small and fast.
 *  - pending cart approvals past their 15 minutes are marked expired
 *  - search sessions older than 30 days are deleted (their steps, picks and claims go with them)
 */
class Cleanup extends Command
{
    protected $signature = 'shopmate:cleanup {--days=30 : Keep searches for this many days}';

    protected $description = 'Expire old cart approvals and delete old searches';

    public function handle(AuditLogger $audit): int
    {
        $expired = CartMandate::where('status', 'pending')->where('expires_at', '<', now())->update(['status' => 'expired']);

        $deleted = 0;
        SearchSession::where('created_at', '<', now()->subDays((int) $this->option('days')))
            ->chunkById(200, function ($sessions) use (&$deleted) {
                $deleted += SearchSession::whereIn('id', $sessions->pluck('id'))->delete();
            });

        $audit->log('maintenance.cleanup', data: ['mandates_expired' => $expired, 'searches_deleted' => $deleted]);
        $this->info("Expired {$expired} cart approval(s); deleted {$deleted} old search(es).");

        return self::SUCCESS;
    }
}
