<?php

use App\Http\Controllers\Api\CartMandateController;
use App\Http\Controllers\Api\PriceAlertController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
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
