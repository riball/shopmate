<?php

namespace App\Services\Pipeline\Steps;

use App\Models\Recommendation;
use App\Services\Pipeline\PipelineContext;
use Illuminate\Support\Facades\DB;

/** Respond: saves the picks with their checklists; only verified claims are kept for display. */
class Responder implements PipelineStep
{
    public function handle(PipelineContext $context): StepOutcome
    {
        DB::transaction(function () use ($context) {
            foreach ($context->picks as $pick) {
                Recommendation::create([
                    'search_session_id' => $context->session->id,
                    'product_id' => $pick->product->id,
                    'product_offer_id' => $pick->offer->id,
                    'rank' => $pick->rank,
                    'score' => round($pick->score, 4),
                    'checklist' => $pick->checklist,
                ]);
            }

            $context->session->update(['status' => 'done', 'plan' => $context->workOrder()]);
        });

        return StepOutcome::Continue;
    }
}
