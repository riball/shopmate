<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Services\Partners\PartnerAudit;
use Illuminate\Console\Command;

/** Replaces a partner's secret (for example after a leak). The old secret stops working immediately. */
class PartnerRotateSecret extends Command
{
    protected $signature = 'partner:rotate-secret {key : The partner key (pk_...)}';

    protected $description = "Replace a partner's signing secret";

    public function handle(PartnerAudit $audit): int
    {
        $partner = Partner::firstWhere('key_id', $this->argument('key'));
        if ($partner === null) {
            $this->error('No partner with that key.');

            return self::FAILURE;
        }

        $secret = Partner::newSecret();
        $partner->update(['secret' => $secret]);
        $audit->record('partner.secret.rotated', $partner, null, ['by' => 'cli']);

        $this->info("New secret for {$partner->name}:");
        $this->line($secret);
        $this->warn('The old secret no longer works. Copy this one now; it will not be shown again.');

        return self::SUCCESS;
    }
}
