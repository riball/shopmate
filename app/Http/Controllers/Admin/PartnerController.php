<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Services\Partners\PartnerAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin: see partners, their recent API activity, and flip the per-partner kill switch. */
class PartnerController extends Controller
{
    public function index(): View
    {
        $since = now()->subDay();

        $counts = AuditLog::query()
            ->where('subject_type', 'partner')
            ->where('created_at', '>=', $since)
            ->selectRaw('subject_id')
            ->selectRaw("SUM(CASE WHEN event LIKE 'partner.rejected.%' THEN 1 ELSE 0 END) AS rejected")
            ->selectRaw("SUM(CASE WHEN event = 'partner.prices.updated' THEN 1 ELSE 0 END) AS updates")
            ->groupBy('subject_id')
            ->get()
            ->keyBy('subject_id');

        return view('admin.partners', [
            'partners' => Partner::with('store:id,name,slug')->orderBy('name')->get(),
            'counts' => $counts,
            'statuses' => PartnerStatus::cases(),
            'events' => AuditLog::where('event', 'like', 'partner.%')->latest('id')->limit(40)->get(),
        ]);
    }

    public function updateStatus(Request $request, Partner $partner, PartnerAudit $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(PartnerStatus::class)]]);
        $old = $partner->status;
        $new = PartnerStatus::from($data['status']);

        $partner->update(['status' => $new]);
        $audit->record('partner.status.changed', $partner, $request, ['from' => $old->value, 'to' => $new->value, 'by' => 'admin']);

        return back()->with('status', "{$partner->name} is now {$new->label()}.");
    }
}
