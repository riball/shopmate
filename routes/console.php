<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Automation (Laravel Scheduler)
|--------------------------------------------------------------------------
| One cron entry on the server runs all of these:
|   * * * * * cd /var/www/shopmate && php artisan schedule:run >> /dev/null 2>&1
| Locally: php artisan schedule:work
*/

// Refresh prices every 6 hours (demo prices are nudged only when DEMO_SIMULATE_PRICES=true).
Schedule::command('prices:refresh'.(config('shopmate.demo_simulate_prices') ? ' --simulate-demo' : ''))
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer();

// Email shoppers whose target price was reached.
Schedule::command('alerts:check')->hourly()->withoutOverlapping();

// Expire old cart approvals, delete searches older than 30 days.
Schedule::command('shopmate:cleanup')->dailyAt('03:15');

// Remove failed queue jobs older than a week.
Schedule::command('queue:prune-failed --hours=168')->daily();
