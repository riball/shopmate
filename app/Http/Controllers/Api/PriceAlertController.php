<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceAlert;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Email me when this drops below ₹X". Checked every hour by alerts:check. */
class PriceAlertController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'target_price' => ['required', 'numeric', 'min:1'],
        ]);

        $product = Product::with('bestOffer.store')->findOrFail($data['product_id']);

        if ($product->best_price !== null && $product->best_price <= $data['target_price']) {
            return response()->json([
                'message' => 'It is already ₹'.number_format($product->best_price).' at '.$product->bestOffer?->store?->name.', at or below your target.',
            ], 422);
        }

        PriceAlert::updateOrCreate(
            ['product_id' => $product->id, 'email' => strtolower($data['email'])],
            ['target_price' => $data['target_price'], 'notified_at' => null],
        );

        return response()->json([
            'message' => 'Alert saved. We will email you when any store sells it for ₹'.number_format($data['target_price']).' or less.',
        ], 201);
    }
}
