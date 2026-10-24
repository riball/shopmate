<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\SearchSession;
use App\Services\Pipeline\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importDemoCatalog();
    }

    private function search(string $query): SearchSession
    {
        $session = SearchSession::create(['query' => $query, 'status' => 'queued']);

        return app(PipelineRunner::class)->run($session);
    }

    public function test_full_search_returns_verified_picks_within_budget(): void
    {
        $session = $this->search('True wireless earbuds under ₹2,000 with a good mic');

        $this->assertSame('done', $session->status);
        $this->assertSame(2000.0, $session->original_budget);
        $this->assertCount(3, $session->recommendations);

        foreach ($session->recommendations()->with('offer', 'product')->get() as $rec) {
            $this->assertLessThanOrEqual(2000, $rec->offer->price);
            $this->assertSame('met', $rec->checklist['true_wireless']['status']);
        }

        // Variants of the same model are collapsed into one pick.
        $groups = $session->recommendations->map(fn ($r) => $r->product->model_group);
        $this->assertSame($groups->unique()->count(), $groups->count());

        $this->assertGreaterThan(0, $session->claims_checked);
        $this->assertSame(0, $session->claims_removed);
        $this->assertSame(0, Claim::where('search_session_id', $session->id)->where('status', 'rejected')->count());
    }

    public function test_a_listing_that_contradicts_itself_is_never_shown_as_yes(): void
    {
        $session = $this->search('true wireless earbuds with mic under 2000');

        foreach ($session->recommendations()->with('product')->get() as $rec) {
            if ($rec->product->name === 'BoltBeat Groove Pro TWS') {
                $this->assertSame('conflict', $rec->checklist['microphone']['status']);
            }
        }
        $this->assertSame('done', $session->status);
    }

    public function test_asks_one_question_when_no_product_type_is_given(): void
    {
        $session = $this->search("Something for my dad's birthday");

        $this->assertSame('needs_clarification', $session->status);
        $this->assertNotEmpty($session->clarify_question);

        // After the answer it searches, and it never asks a second time.
        $session->update(['clarification_answer' => 'something nice', 'clarified' => true, 'status' => 'queued']);
        $again = app(PipelineRunner::class)->run($session);
        $this->assertSame('no_results', $again->status);
    }

    public function test_relaxes_the_search_and_says_what_was_given_up(): void
    {
        $session = $this->search('5G phone with AMOLED under 15k');

        $this->assertSame('done', $session->status);
        $this->assertCount(1, $session->replans);
        $this->assertSame('raise_budget', $session->replans[0]['type']);
        $this->assertLessThanOrEqual(15000 * 1.5, $session->effective_budget);
        $this->assertSame(15000.0, $session->original_budget, 'verification keeps the original budget');
    }

    public function test_an_invalid_replan_pick_falls_back_to_the_fixed_rule(): void
    {
        $this->scriptLlm()->on('replan_pick', ['option_id' => 'buy_something_else', 'reason' => 'made up']);

        $session = $this->search('5G phone with AMOLED under 15k');

        $this->assertSame('rule', $session->replans[0]['picked_by']);
        $this->assertSame('done', $session->status);
    }

    public function test_wrong_claims_get_one_rewrite_and_are_deleted_if_still_wrong(): void
    {
        $llm = $this->scriptLlm();
        // First draft: correct claims plus one with a wrong price and one that smuggles in a number.
        $llm->on('explain', function (array $context, $offline) {
            $claims = $offline->json('explain', '', '', $context)['claims'];
            $p = $context['products'][0];
            $claims[] = ['product_id' => $p['product_id'], 'text' => 'Only ₹99 today!', 'cites' => $p['steps']['search'], 'facts' => [['field' => 'price', 'value' => 99]]];
            $claims[] = ['product_id' => $p['product_id'], 'text' => 'Battery lasts 500 hours.', 'cites' => $p['steps']['search'], 'facts' => [['field' => 'price', 'value' => $p['price']]]];

            return ['claims' => $claims];
        });
        // The rewrite is still wrong, so both must be deleted.
        $llm->on('rewrite', fn ($context) => ['claims' => [
            ['product_id' => $context['products'][0]['product_id'], 'text' => 'Rated 5★.', 'cites' => 'T99', 'facts' => [['field' => 'rating', 'value' => 5]]],
        ]]);

        $session = $this->search('True wireless earbuds under ₹2,000 with a good mic');

        $this->assertSame('done', $session->status);
        $this->assertSame(2, $session->claims_removed);
        $this->assertContains('rewrite', $llm->calls);

        $shown = Claim::where('search_session_id', $session->id)->where('status', 'verified')->pluck('text');
        $this->assertFalse($shown->contains('Only ₹99 today!'));
        $this->assertFalse($shown->contains('Battery lasts 500 hours.'));

        $reasons = Claim::where('search_session_id', $session->id)->where('status', 'rejected')->pluck('reason')->implode(' | ');
        $this->assertStringContainsString('price is wrong', $reasons);
        $this->assertStringContainsString('500', $reasons);
        $this->assertStringContainsString('does not exist', $reasons);
    }

    public function test_the_ai_cannot_add_a_product(): void
    {
        $this->scriptLlm()->on('explain', fn ($context) => ['claims' => [
            ['product_id' => 999999, 'text' => 'Try the SuperPhone X!', 'cites' => 'T2', 'facts' => [['field' => 'price', 'value' => 1]]],
        ]]);

        $session = $this->search('True wireless earbuds under ₹2,000 with a good mic');

        $this->assertSame(0, Claim::where('search_session_id', $session->id)->where('status', 'verified')->count());
        $this->assertNotContains(999999, $session->recommendations->pluck('product_id')->all());
    }

    public function test_api_flow_start_and_poll(): void
    {
        config(['queue.default' => 'sync']);

        $id = $this->postJson('/api/searches', ['query' => 'Smartwatch with GPS under 10000'])
            ->assertCreated()
            ->json('id');

        $this->getJson("/api/searches/{$id}")
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('work_order.category', 'smartwatches')
            ->assertJsonStructure(['steps' => [['seq', 'step_id', 'node', 'observation']], 'results' => [['rank', 'product', 'offer', 'checklist', 'claims']], 'banner']);

        // Polling with ?after= returns only newer steps.
        $this->getJson("/api/searches/{$id}?after=100")->assertJsonCount(0, 'steps');
    }

    public function test_search_requests_are_validated(): void
    {
        $this->postJson('/api/searches', ['query' => 'x'])->assertStatus(422)->assertJsonValidationErrors('query');
    }
}
