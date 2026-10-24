<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SearchSession;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Operations view: recent searches (speed, outcome, claims removed) and the audit log. */
class OpsController extends Controller
{
    public function index(): View
    {
        $since = now()->subDays(7);

        $summary = SearchSession::where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) AS searches')
            ->selectRaw("SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done")
            ->selectRaw("SUM(CASE WHEN status = 'no_results' THEN 1 ELSE 0 END) AS no_results")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->selectRaw("SUM(CASE WHEN status = 'needs_clarification' THEN 1 ELSE 0 END) AS clarifying")
            ->selectRaw('AVG(duration_ms) AS avg_ms')
            ->selectRaw('SUM(claims_checked) AS claims_checked')
            ->selectRaw('SUM(claims_removed) AS claims_removed')
            ->first();

        return view('admin.ops', [
            'summary' => $summary,
            'searches' => SearchSession::latest()->limit(30)->get(),
            'audit' => AuditLog::latest('id')->limit(30)->get(),
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(10)->get(['uuid', 'failed_at', 'exception']),
        ]);
    }
}
