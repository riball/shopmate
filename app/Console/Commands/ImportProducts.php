<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\Sources\DemoSource;
use App\Services\Catalog\Sources\DummyJsonSource;
use App\Services\Catalog\Sources\LocalJsonSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * php artisan products:import --source=demo|local|dummyjson
 * Same command for every source: the private Flipkart files never need to leave your machine.
 */
class ImportProducts extends Command
{
    protected $signature = 'products:import
        {--source=demo : demo (fictional, safe to publish), local (your private Flipkart JSON) or dummyjson (public demo API)}
        {--captured-on= : Date the local snapshot was captured (Y-m-d), defaults to the file date}';

    protected $description = 'Import products, store offers and typed spec facts from a data source';

    public function handle(CatalogImporter $importer): int
    {
        $source = match ($this->option('source')) {
            'demo' => app(DemoSource::class),
            'local' => new LocalJsonSource(capturedOn: $this->option('captured-on')),
            'dummyjson' => app(DummyJsonSource::class),
            default => null,
        };

        if ($source === null) {
            $this->error('Unknown source. Use --source=demo, --source=local or --source=dummyjson.');

            return self::INVALID;
        }

        $this->info("Importing from \"{$source->name()}\"...");
        $count = 0;

        try {
            $report = $importer->import($source, function () use (&$count) {
                if (++$count % 25 === 0) {
                    $this->line("  {$count} listings...");
                }
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        Cache::forget('home:categories');
        Cache::forget('nav:categories');
        Cache::forget('sitemap.xml');

        $this->table(['Created', 'Updated', 'Store offers', 'Spec facts', 'Contradictions (no fact stored)', 'Skipped'], [[
            $report['created'], $report['updated'], $report['offers'], $report['facts'], $report['conflicts'], $report['skipped'],
        ]]);

        return self::SUCCESS;
    }
}
