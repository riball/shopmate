<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Llm\GroqClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\OfflineLlm;
use App\Services\Llm\ResilientLlm;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Which AI the pipeline talks to. Everything else depends only on the LlmClient interface.
        $this->app->bind(LlmClient::class, function ($app) {
            $config = config('shopmate.llm');
            $offline = $app->make(OfflineLlm::class);

            if ($config['driver'] !== 'groq' || empty($config['groq']['api_key'])) {
                return $offline;
            }

            $groq = new GroqClient(
                apiKey: $config['groq']['api_key'],
                model: $config['groq']['model'],
                baseUrl: $config['groq']['base_url'],
                timeout: $config['groq']['timeout'],
            );

            return $config['fallback_to_offline'] ? new ResilientLlm($groq, $offline) : $groq;
        });
    }

    public function boot(): void
    {
        // Short names in seo_meta.seoable_type instead of full class names.
        Relation::enforceMorphMap([
            'category' => Category::class,
            'product' => Product::class,
            'page' => Page::class,
        ]);

        // Outside production, log any lazy loading (a sign of the N+1 query problem) instead of failing silently.
        if (! $this->app->isProduction()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
                Log::warning('Lazy loading detected (possible N+1 query)', ['model' => $model::class, 'relation' => $relation]);
            });
        }

        // Each search costs AI calls, so limit how often one visitor can start one.
        RateLimiter::for('searches', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('mandates', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
