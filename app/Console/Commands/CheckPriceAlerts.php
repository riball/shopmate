<?php

namespace App\Console\Commands;

use App\Mail\PriceDropMail;
use App\Models\PriceAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Scheduled hourly: emails everyone whose target price has been reached. Each alert fires once. */
class CheckPriceAlerts extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Send price-drop emails for alerts whose target price was reached';

    public function handle(): int
    {
        $sent = 0;

        PriceAlert::query()
            ->whereNull('notified_at')
            ->join('products', 'products.id', '=', 'price_alerts.product_id')
            ->whereColumn('products.best_price', '<=', 'price_alerts.target_price')
            ->select('price_alerts.*')
            ->with('product.bestOffer.store')
            ->chunkById(100, function ($alerts) use (&$sent) {
                foreach ($alerts as $alert) {
                    Mail::to($alert->email)->send(new PriceDropMail($alert));
                    $alert->update(['notified_at' => now()]);
                    $sent++;
                }
            }, 'price_alerts.id', 'id');

        $this->info("Sent {$sent} price-drop email(s).");

        return self::SUCCESS;
    }
}
