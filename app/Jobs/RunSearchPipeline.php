<?php

namespace App\Jobs;

use App\Models\SearchSession;
use App\Services\Pipeline\PipelineRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs a search in the background (a queue job), so the page stays responsive
 * and polls for trace steps while the AI works.
 * AI calls already retry on their own; the job itself retries only if the
 * worker crashes before finishing.
 */
class RunSearchPipeline implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public array $backoff = [10];

    public function __construct(public string $sessionId) {}

    public function handle(PipelineRunner $runner): void
    {
        $session = SearchSession::find($this->sessionId);
        if (! $session || $session->isFinished()) {
            return;
        }

        $session->steps()->delete();   // clean start if this is a retry
        $session->recommendations()->delete();
        $session->claims()->delete();

        $runner->run($session);
    }

    public function failed(?Throwable $e): void
    {
        SearchSession::whereKey($this->sessionId)->update([
            'status' => 'failed',
            'error' => mb_substr((string) $e?->getMessage(), 0, 1000),
        ]);
    }
}
