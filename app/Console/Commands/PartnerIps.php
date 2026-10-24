<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Services\Partners\IpRange;
use App\Services\Partners\PartnerAudit;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Adds or removes allowed IPs / CIDR ranges for a partner, or lists them. */
class PartnerIps extends Command
{
    protected $signature = 'partner:ips
        {key : The partner key (pk_...)}
        {--add=* : IP or CIDR range to allow}
        {--remove=* : IP or CIDR range to stop allowing}';

    protected $description = "Show or change a partner's IP allowlist";

    public function handle(): int
    {
        $partner = Partner::firstWhere('key_id', $this->argument('key'));
        if ($partner === null) {
            $this->error('No partner with that key.');

            return self::FAILURE;
        }

        try {
            $add = IpRange::normalise((array) $this->option('add'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $remove = array_map('trim', (array) $this->option('remove'));

        $ips = array_values(array_unique(array_merge(
            array_diff((array) $partner->allowed_ips, $remove),
            $add,
        )));

        if ($add !== [] || $remove !== []) {
            if ($ips === []) {
                $this->warn('The allowlist is now empty, so this partner is blocked until an IP is added.');
            }
            $partner->update(['allowed_ips' => $ips]);
            app(PartnerAudit::class)->record('partner.ips.changed', $partner, null, ['added' => $add, 'removed' => $remove, 'by' => 'cli']);
        }

        $this->info("Allowed IPs for {$partner->name}: ".($ips ? implode(', ', $ips) : '(none)'));

        return self::SUCCESS;
    }
}
