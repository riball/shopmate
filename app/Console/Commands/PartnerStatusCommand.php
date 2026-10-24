<?php

namespace App\Console\Commands;

use App\Enums\PartnerStatus;
use App\Models\Partner;
use App\Services\Partners\PartnerAudit;
use Illuminate\Console\Command;

/**
 * The kill switch from the command line, for when you are on the server
 * during an incident: cut off one partner without stopping the others.
 */
class PartnerStatusCommand extends Command
{
    protected $signature = 'partner:status
        {key : The partner key (pk_...)}
        {status : active, read_only or suspended}';

    protected $description = 'Set a partner to active, read_only or suspended';

    public function handle(PartnerAudit $audit): int
    {
        $partner = Partner::firstWhere('key_id', $this->argument('key'));
        if ($partner === null) {
            $this->error('No partner with that key.');

            return self::FAILURE;
        }

        $new = PartnerStatus::tryFrom((string) $this->argument('status'));
        if ($new === null) {
            $this->error('Status must be one of: '.implode(', ', array_map(fn ($s) => $s->value, PartnerStatus::cases())));

            return self::FAILURE;
        }

        $old = $partner->status;
        $partner->update(['status' => $new]);
        $audit->record('partner.status.changed', $partner, null, ['from' => $old->value, 'to' => $new->value, 'by' => 'cli']);

        $this->info("{$partner->name}: {$old->label()} -> {$new->label()}.");

        return self::SUCCESS;
    }
}
