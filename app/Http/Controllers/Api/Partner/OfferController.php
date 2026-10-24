<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\ProductOffer;
use App\Services\Partners\PartnerAudit;
use App\Services\Partners\PartnerPriceUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner API: a retailer reads and updates prices for its own store.
 * Every request has already passed PartnerGuard (key, IP allowlist,
 * signature, kill switch) and the per-partner rate limit.
 */
class OfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $partner = $this->partner($request);

        $offers = ProductOffer::query()
            ->where('store_id', $partner->store_id)
            ->with('product:id,slug,name')
            ->orderBy('id')
            ->paginate(100);

        return response()->json([
            'store' => $partner->store->slug,
            'data' => $offers->getCollection()->map(fn (ProductOffer $offer) => [
                'product' => $offer->product->slug,
                'name' => $offer->product->name,
                'price' => $offer->price,
                'in_stock' => $offer->in_stock,
                'last_checked_at' => $offer->last_checked_at?->toIso8601String(),
            ])->values(),
            'meta' => ['page' => $offers->currentPage(), 'pages' => $offers->lastPage(), 'total' => $offers->total()],
        ]);
    }

    public function update(Request $request, PartnerPriceUpdater $updater, PartnerAudit $audit): JsonResponse
    {
        $partner = $this->partner($request);

        $validated = $request->validate([
            'offers' => ['required', 'array', 'min:1', 'max:'.config('shopmate.partners.max_items_per_request')],
            'offers.*.product' => ['required', 'string', 'max:255'],
            'offers.*.price' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'offers.*.in_stock' => ['sometimes', 'boolean'],
        ]);

        $report = $updater->apply($partner, $validated['offers']);

        $audit->record('partner.prices.updated', $partner, $request, [
            'request_id' => $request->attributes->get('request_id'),
            'updated' => $report['updated'],
            'unchanged' => $report['unchanged'],
            'skipped' => $report['skipped'],
        ]);

        return response()->json($report);
    }

    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }
}
