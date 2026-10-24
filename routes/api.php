<?php

use App\Http\Controllers\Api\CartMandateController;
use App\Http\Controllers\Api\Partner\OfferController as PartnerOfferController;
use App\Http\Controllers\Api\PriceAlertController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Middleware\PartnerGuard;
use Illuminate\Support\Facades\Route;

// JSON endpoints used by the jQuery front end (all under /api).

Route::post('searches', [SearchController::class, 'store'])->middleware('throttle:searches')->name('api.searches.store');
Route::get('searches/{session}', [SearchController::class, 'show'])->name('api.searches.show');
Route::post('searches/{session}/clarify', [SearchController::class, 'clarify'])->middleware('throttle:searches')->name('api.searches.clarify');

Route::get('products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('products/{product}/history', [ProductController::class, 'history'])->name('api.products.history');

Route::post('price-alerts', [PriceAlertController::class, 'store'])->middleware('throttle:20,1')->name('api.alerts.store');

Route::post('cart-mandates', [CartMandateController::class, 'store'])->middleware('throttle:mandates')->name('api.mandates.store');
Route::post('cart-mandates/{mandate}/checkout', [CartMandateController::class, 'checkout'])->middleware('throttle:mandates')->name('api.mandates.checkout');

// Partner API (retailers push their own prices). PartnerGuard runs the checks: failed-attempt
// lockout, key, IP allowlist, signature, kill switch and the partner's own rate limit.
Route::prefix('partner/v1')->name('api.partner.')->middleware(PartnerGuard::class)->group(function () {
    Route::get('offers', [PartnerOfferController::class, 'index'])->name('offers.index');
    Route::put('offers', [PartnerOfferController::class, 'update'])->name('offers.update');
});
