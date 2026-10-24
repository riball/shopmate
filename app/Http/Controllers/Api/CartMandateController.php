<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductOffer;
use App\Services\Cart\CartMandateService;
use App\Services\Cart\MandateException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mock purchase: approve (sign) a cart, then check out with the signed cart. */
class CartMandateController extends Controller
{
    public function __construct(private CartMandateService $mandates) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offer_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'between:1,5'],
            'consent' => ['required', 'boolean'],
            'search_session_id' => ['nullable', 'uuid', 'exists:search_sessions,id'],
        ]);

        // Only the offer ID comes from the browser; the price is looked up here.
        $offer = ProductOffer::with('product', 'store')->findOrFail($data['offer_id']);

        try {
            $mandate = $this->mandates->create($offer, (int) ($data['quantity'] ?? 1), (bool) $data['consent'], $data['search_session_id'] ?? null);
        } catch (MandateException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'mandate_id' => $mandate->id,
            'payload' => $mandate->payload,
            'signature' => $mandate->signature,
            'expires_at' => $mandate->expires_at->toIso8601String(),
        ], 201);
    }

    public function checkout(Request $request, string $mandate): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'array'],
            'signature' => ['required', 'string', 'size:64'],
        ]);

        try {
            $done = $this->mandates->checkout($mandate, $data['payload'], $data['signature']);
        } catch (MandateException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'message' => 'Mock order placed. No money was charged.',
            'order_ref' => $done->order_ref,
            'total' => $done->total,
        ]);
    }
}
