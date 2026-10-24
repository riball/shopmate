<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\OpsController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/search/{session}', [PageController::class, 'search'])->name('search.show');
Route::get('/c/{category}', [PageController::class, 'browse'])->name('browse');
Route::get('/p/{product}', [PageController::class, 'product'])->name('products.show');
Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', fn () => response(implode("\n", [
    'User-agent: *', 'Disallow: /admin', 'Disallow: /api', 'Disallow: /search/', '', 'Sitemap: '.route('sitemap'),
]), 200, ['Content-Type' => 'text/plain']))->name('robots');
Route::get('/health', HealthController::class)->name('health');

// Admin (SEO editor + operations view)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(AdminOnly::class)->group(function () {
        Route::redirect('/', '/admin/seo');
        Route::get('seo', [SeoController::class, 'index'])->name('seo.index');
        Route::get('seo/{type}/{id}', [SeoController::class, 'edit'])->whereNumber('id')->name('seo.edit');
        Route::put('seo/{type}/{id}', [SeoController::class, 'update'])->whereNumber('id')->name('seo.update');
        Route::get('ops', [OpsController::class, 'index'])->name('ops');
    });
});
