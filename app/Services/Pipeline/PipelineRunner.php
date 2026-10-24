<?php

namespace App\Services\Pipeline;

use App\Models\SearchSession;
use App\Services\Llm\LlmClient;
use App\Services\Pipeline\Steps\Clarifier;
use App\Services\Pipeline\Steps\Explainer;
use App\Services\Pipeline\Steps\Planner;
use App\Services\Pipeline\Steps\Ranker;
use App\Services\Pipeline\Steps\Replanner;
use App\Services\Pipeline\Steps\Responder;
use App\Services\Pipeline\Steps\Searcher;
use App\Services\Pipeline\Steps\StepOutcome;
use App\Services\Pipeline\Steps\Verifier;
use Throwable;

/**
 * Runs the fixed flowchart for one search:
 *
 *   Planner → Clarify → Search ⇄ Replan (max 2) → Rank → Explain → Verify → Respond
 *
 * The AI makes decisions inside Planner, Replan and Explain; plain PHP does
 * everything that must be exact (search, filtering, ranking, checking, prices).
 */
class PipelineRunner
{
    public function __construct(
        private LlmClient $llm,
        private Planner $planner,
        private Clarifier $clarifier,
        private Searcher $searcher,
        private Replanner $replanner,
        private Ranker $ranker,
        private Explainer $explainer,
        private Verifier $verifier,
        private Responder $responder,
    ) {}

    public function run(SearchSession $session): SearchSession
    {
        $started = microtime(true);
        $session->update(['status' => 'running', 'llm_driver' => $this->llm->name(), 'error' => null]);
        $context = new PipelineContext($session, new Trace($session));

        try {
            $this->steps($context);
        } catch (Throwable $e) {
            report($e);
            $context->trace->note('error', 'The search stopped because of an unexpected error. Please try again.', 'warning');
            $session->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
        }

        $session->update(['duration_ms' => (int) round((microtime(true) - $started) * 1000)]);

        return $session->refresh();
    }

    private function steps(PipelineContext $context): void
    {
        foreach ([$this->planner, $this->clarifier] as $step) {
            if ($step->handle($context) === StepOutcome::Stop) {
                return;
            }
        }

        $this->searcher->handle($context);
        $replans = 0;
        while ($context->candidates === [] && $replans < (int) config('shopmate.pipeline.max_replans', 2)) {
            if ($this->replanner->handle($context) === StepOutcome::Stop) {
                break;
            }
            $replans++;
            $this->searcher->handle($context);
        }

        if ($context->candidates === []) {
            $context->trace->note('respond', 'No listing matches this request, even after relaxing it. Try a higher budget or fewer must-haves.', 'warning');
            $context->session->update(['status' => 'no_results', 'plan' => $context->workOrder()]);

            return;
        }

        foreach ([$this->ranker, $this->explainer, $this->verifier, $this->responder] as $step) {
            $step->handle($context);
        }
    }
}
