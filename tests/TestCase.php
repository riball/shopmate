<?php

namespace Tests;

use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\Sources\DemoSource;
use App\Services\Llm\LlmClient;
use App\Services\Llm\OfflineLlm;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\ScriptedLlm;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Tests never call the real AI: the offline rule-based AI answers unless a test scripts something.
        config(['shopmate.llm.driver' => 'offline', 'shopmate.llm.groq.api_key' => null]);
        $this->app->bind(LlmClient::class, OfflineLlm::class);
    }

    protected function importDemoCatalog(): void
    {
        app(CatalogImporter::class)->import(new DemoSource);
    }

    protected function scriptLlm(): ScriptedLlm
    {
        $llm = app(ScriptedLlm::class);
        $this->app->instance(LlmClient::class, $llm);

        return $llm;
    }
}
