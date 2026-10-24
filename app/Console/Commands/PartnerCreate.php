<?php

namespace App\Console\Commands;

use App\Enums\PartnerStatus;
use App\Models\Partner;
use App\Models\Store;
use App\Services\Partners\IpRange;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Creates a partner for one store and prints its key and secret once.
 * The secret is never shown again (only rotated), like real API dashboards.
 */
class PartnerCreate extends Command
{
    protected $signature = 'partner:create
        {name : Partner name, e.g. "CartWala feed"}
        {store : Slug of the store this partner may update}
        {--ip=* : Allowed IP or CIDR range (repeat for more). Required.}
        {--rate=60 : Requests allowed per minute}';

    protected $description = 'Create a partner API account (key, secret, IP allowlist)';

    public function handle(): int
    {
        $store = Store::firstWhere('slug', $this->argument('store'));
        if ($store === null) {
            $this->error('No store with slug "'.$this->argument('store').'". Stores: '.Store::orderBy('slug')->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        try {
            $ips = IpRange::normalise((array) $this->option('ip'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if ($ips === []) {
            $this->error('Give at least one --ip. An empty allowlist would block every request.');

            return self::FAILURE;
        }

        $rate = (int) $this->option('rate');
        if ($rate < 1 || $rate > 1000) {
            $this->error('--rate must be between 1 and 1000.');

            return self::FAILURE;
        }

        $secret = Partner::newSecret();
        $partner = Partner::create([
            'name' => $this->argument('name'),
            'store_id' => $store->id,
            'key_id' => Partner::newKeyId(),
            'secret' => $secret,
            'allowed_ips' => $ips,
            'status' => PartnerStatus::Active,
            'rate_limit_per_minute' => $rate,
        ]);

        $this->info("Partner \"{$partner->name}\" created for store {$store->slug}.");
        $this->table(['Setting', 'Value'], [
            ['Key (X-Partner-Key)', $partner->key_id],
            ['Secret', $secret],
            ['Allowed IPs', implode(', ', $ips)],
            ['Rate limit', "{$rate} per minute"],
        ]);
        $this->warn('Copy the secret now. It will not be shown again (use partner:rotate-secret to replace it).');

        return self::SUCCESS;
    }
}
