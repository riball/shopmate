<?php

namespace App\Services\Pipeline\Steps;

use App\Services\Pipeline\PipelineContext;

/**
 * Clarify: if the request names no kind of product, ask ONE question instead
 * of guessing. The answer is added to the original request and the pipeline
 * runs again. It never asks twice.
 */
class Clarifier implements PipelineStep
{
    public const DEFAULT_QUESTION = 'What kind of product are you looking for? For example earphones, a smartphone, a laptop, a smartwatch or running shoes.';

    public function handle(PipelineContext $context): StepOutcome
    {
        if ($context->category !== null) {
            return StepOutcome::Continue;
        }

        $session = $context->session;

        if ($session->clarified) {
            $context->trace->note('clarify',
                'Still no product type after the follow-up answer, so the search stopped instead of guessing. Try naming the product, e.g. "earbuds under ₹2,000".',
                'warning');
            $session->update(['status' => 'no_results']);

            return StepOutcome::Stop;
        }

        $question = trim((string) $context->clarifyQuestion) ?: self::DEFAULT_QUESTION;
        $context->trace->note('clarify', "Asked the shopper: \"{$question}\"");
        $session->update(['status' => 'needs_clarification', 'clarify_question' => $question]);

        return StepOutcome::Stop;
    }
}
