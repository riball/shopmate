<?php

namespace App\Http\Controllers;

use App\Models\ProductOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * /health: one URL an uptime monitor or CloudWatch can call.
 * Returns 200 when everything is fine and 503 when something needs attention.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $checks['database'] = ['ok' => true, 'ms' => round((microtime(true) - $start) * 1000, 1)];
        } catch (Throwable $e) {
            $checks['database'] = ['ok' => false, 'error' => 'Database unreachable'];
        }

        if ($checks['database']['ok']) {
            $pending = config('queue.default') === 'database' ? DB::table('jobs')->count() : null;
            $oldest = config('queue.default') === 'database' ? DB::table('jobs')->min('created_at') : null;
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            // Jobs waiting over 2 minutes usually means the queue worker is not running.
            $stuck = $oldest !== null && $oldest < now()->subMinutes(2)->timestamp;
            $checks['queue'] = ['ok' => ! $stuck, 'pending' => $pending, 'failed_last_24h' => $failed];

            $lastCheck = ProductOffer::max('last_checked_at');
            $checks['prices'] = [
                'ok' => $lastCheck !== null,
                'last_refreshed' => $lastCheck,
            ];
        }

        $checks['ai'] = ['ok' => true, 'driver' => config('shopmate.llm.driver')];

        $ok = collect($checks)->every(fn ($c) => $c['ok']);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
