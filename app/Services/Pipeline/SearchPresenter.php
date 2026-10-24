<?php

namespace App\Services\Pipeline;

use App\Models\Claim;
use App\Models\Recommendation;
use App\Models\SearchSession;
use App\Models\TraceStep;
use App\Support\SpecLabel;

/**
 * Turns a search session into the JSON the results page needs:
 * work order, trace steps (only new ones after ?after=N), the 3 cards with
 * checklists and verified claims, and the "statements checked" banner.
 */
class SearchPresenter
{
    public function present(SearchSession $session, int $afterSeq = 0): array
    {
        $steps = TraceStep::where('search_session_id', $session->id)
            ->where('seq', '>', $afterSeq)
            ->orderBy('seq')
            ->get()
            ->map(fn (TraceStep $s) => [
                'seq' => $s->seq,
                'step_id' => $s->step_id,
                'node' => $s->node,
                'level' => $s->level,
                'thought' => $s->thought,
                'action' => $s->action,
                'observation' => $s->observation,
            ]);

        $payload = [
            'id' => $session->id,
            'status' => $session->status,
            'query' => $session->query,
            'clarification_answer' => $session->clarification_answer,
            'clarify_question' => $session->clarify_question,
            'work_order' => $session->plan,
            'replans' => $session->replans ?? [],
            'ai' => $session->llm_driver,
            'duration_ms' => $session->duration_ms,
            'queued_seconds' => $session->status === 'queued' ? now()->diffInSeconds($session->created_at, true) : 0,
            'steps' => $steps,
            'results' => [],
            'banner' => null,
        ];

        if ($session->status === 'done') {
            $payload['results'] = $this->results($session);
            $payload['banner'] = [
                'checked' => $session->claims_checked,
                'removed' => $session->claims_removed,
                'shown' => array_sum(array_map(fn ($r) => count($r['claims']), $payload['results'])),
            ];
        }

        return $payload;
    }

    private function results(SearchSession $session): array
    {
        $recommendations = Recommendation::where('search_session_id', $session->id)
            ->with(['product.specFacts', 'product.category', 'offer.store'])
            ->orderBy('rank')
            ->get();

        $claims = Claim::where('search_session_id', $session->id)
            ->where('status', 'verified')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');

        $budget = $session->original_budget;

        return $recommendations->map(function (Recommendation $rec) use ($claims, $budget) {
            $product = $rec->product;
            $offer = $rec->offer;

            return [
                'rank' => $rec->rank,
                'score' => $rec->score,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'url' => $product->publicUrl(),
                    'image_url' => $product->image_url,
                    'category' => $product->category->slug,
                    'rating' => $product->rating,
                    'review_count' => $product->review_count,
                    'offers_count' => $product->offers_count,
                    'is_demo' => $product->isDemo(),
                    'source' => match ($product->source) {
                        'demo' => 'Demo catalog (fictional)',
                        'local' => 'Flipkart snapshot',
                        'dummyjson' => 'DummyJSON demo API',
                        default => $product->source,
                    },
                    'captured_on' => $product->captured_on?->format('j M Y'),
                ],
                'offer' => $offer ? [
                    'id' => $offer->id,
                    'store' => $offer->store->name,
                    'price' => $offer->price,
                    'mrp' => $offer->mrp,
                    'url' => $offer->url,
                    'approx' => $offer->isConverted(),
                    'fx' => $offer->isConverted() ? "{$offer->original_currency} {$offer->original_price} × {$offer->fx_rate}" : null,
                    'stale' => $offer->isStale(),
                    'within_budget' => $budget === null ? null : $offer->price <= $budget,
                ] : null,
                'checklist' => collect($rec->checklist)->map(fn ($item, $key) => [
                    'key' => $key,
                    'label' => $item['label'],
                    'status' => $item['status'],
                ])->values(),
                'specs' => $product->specFacts->map(fn ($fact) => [
                    'key' => $fact->key,
                    'label' => SpecLabel::for($product->category->slug, $fact->key),
                    'value' => $fact->display(),
                ])->values(),
                'claims' => ($claims[$product->id] ?? collect())->map(fn ($c) => [
                    'text' => $c->text,
                    'cites' => $c->cites,
                ])->values()->all(),
            ];
        })->all();
    }
}
