<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\Catalog\BrowseQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan db:explain-browse smartphones --must=5g --max=20000
 * Prints the browse query, MySQL's EXPLAIN plan for it, and how long it takes.
 * Use it for the README's before/after optimisation story (e.g. drop an index, compare).
 */
class ExplainBrowse extends Command
{
    protected $signature = 'db:explain-browse {category} {--must=* : must-have keys} {--max= : max price} {--sort=best} {--runs=20}';

    protected $description = 'Show the EXPLAIN plan and timing of the browse page query';

    public function handle(BrowseQuery $browse): int
    {
        $category = Category::where('slug', $this->argument('category'))->firstOrFail();
        $query = $browse->build($category, [
            'must' => $this->option('must'),
            'max_price' => $this->option('max'),
            'sort' => $this->option('sort'),
        ])->limit(12);

        $sql = $query->toRawSql();
        $this->line('<info>SQL</info>');
        $this->line($sql);

        $driver = DB::connection()->getDriverName();
        $this->newLine();
        $this->line("<info>EXPLAIN ({$driver})</info>");
        $plan = $driver === 'sqlite'
            ? DB::select('EXPLAIN QUERY PLAN '.$sql)
            : DB::select('EXPLAIN '.$sql);
        $rows = array_map(fn ($r) => (array) $r, $plan);
        if ($rows) {
            $this->table(array_keys($rows[0]), $rows);
        }

        $runs = max(1, (int) $this->option('runs'));
        $start = microtime(true);
        for ($i = 0; $i < $runs; $i++) {
            (clone $query)->get();
        }
        $ms = (microtime(true) - $start) * 1000 / $runs;
        $this->info(sprintf('Average over %d runs: %.2f ms', $runs, $ms));

        return self::SUCCESS;
    }
}
