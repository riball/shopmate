<?php

namespace App\Services\Pipeline\Steps;

use App\Services\Llm\LlmClient;
use App\Services\Pipeline\PipelineContext;
use App\Services\Pipeline\Prompts;
use App\Services\Pipeline\Requirement;

/**
 * Replan (AI picks, code checks): when nothing meets every requirement, code
 * works out each single change that would bring real products back:
 *   - raise the budget (by up to 50%), or
 *   - drop one must-have
 * and counts how many products each unlocks. The AI picks one; if its pick
 * isn't one of the options, a fixed rule decides instead. The shopper sees
 * exactly what was given up.
 */
class Replanner implements PipelineStep
{
    use UsesLlm;

    public function __construct(LlmClient $llm, private Searcher $searcher)
    {
        $this->llm = $llm;
    }

    public function handle(PipelineContext $context): StepOutcome
    {
        $options = $this->options($context);

        if ($options === []) {
            $context->trace->note('replan', 'No single change (a budget raise of up to 50% or dropping one must-have) brings back any products.', 'warning');

            return StepOutcome::Stop;
        }

        $answer = $this->ask($context, 'replan_pick', Prompts::replan(),
            json_encode(['request' => $context->session->fullQuery(), 'options' => array_values($options)], JSON_UNESCAPED_UNICODE),
            ['options' => array_values($options)],
        );

        $pickedId = is_string($answer['option_id'] ?? null) ? $answer['option_id'] : null;
        $pickedBy = 'ai';
        if (! isset($options[$pickedId])) {
            $pickedId = $this->ruleBasedPick($options);
            $pickedBy = 'rule';
        }
        $option = $options[$pickedId];

        if ($option['type'] === 'raise_budget') {
            $context->effectiveBudget = $option['new_budget'];
        } else {
            $context->dropped[] = $option['requirement'];
        }

        $context->replans[] = [
            'option_id' => $pickedId,
            'type' => $option['type'],
            'summary' => $option['summary'],
            'unlocks' => $option['unlocks'],
            'picked_by' => $pickedBy,
            'reason' => $pickedBy === 'ai' && is_string($answer['reason'] ?? null) ? $answer['reason'] : 'Picked by the fixed rule: the option that unlocks the most products.',
        ];

        $context->trace->tool('replan',
            'Nothing met every requirement, so find the smallest change that brings real products back.',
            'replan(options='.count($options).')',
            "Gave up: {$option['summary']} (unlocks {$option['unlocks']} product".($option['unlocks'] === 1 ? '' : 's').'). Chosen by '.($pickedBy === 'ai' ? 'the AI' : 'the fixed rule').'.',
            data: ['options' => array_values($options), 'picked' => $pickedId],
        );

        $context->session->update([
            'replans' => $context->replans,
            'effective_budget' => $context->effectiveBudget,
            'plan' => $context->workOrder(),
        ]);

        return StepOutcome::Continue;
    }

    /** @return array<string, array> option id => option */
    public function options(PipelineContext $context): array
    {
        $options = [];
        $active = $context->activeRequirements();
        $budget = $context->effectiveBudget;
        $maxRaise = (float) config('shopmate.pipeline.max_budget_raise', 0.5);

        if ($budget !== null && $context->originalBudget !== null) {
            $cheapest = $this->searcher->cheapestPassing($context->category, $active);
            $cap = $context->originalBudget * (1 + $maxRaise);
            if ($cheapest !== null && $cheapest > $budget && $cheapest <= $cap) {
                $newBudget = min(ceil($cheapest / 100) * 100, $cap);
                $unlocks = count($this->searcher->find($context->category, $newBudget, $active)['candidates']);
                $options['raise_budget'] = [
                    'id' => 'raise_budget',
                    'type' => 'raise_budget',
                    'new_budget' => (float) $newBudget,
                    'summary' => sprintf('budget raised from ₹%s to ₹%s', number_format($budget), number_format($newBudget)),
                    'unlocks' => $unlocks,
                ];
            }
        }

        foreach ($active as $requirement) {
            if (! $requirement->isSupported()) {
                continue;   // unsupported wishes never remove products, so dropping them changes nothing
            }
            $without = array_values(array_filter($active, fn (Requirement $r) => $r->key !== $requirement->key));
            $unlocks = count($this->searcher->find($context->category, $budget, $without)['candidates']);
            if ($unlocks > 0) {
                $options["drop_{$requirement->key}"] = [
                    'id' => "drop_{$requirement->key}",
                    'type' => 'drop_requirement',
                    'requirement' => $requirement->key,
                    'summary' => "dropped \"{$requirement->label}\"",
                    'unlocks' => $unlocks,
                ];
            }
        }

        return array_filter($options, fn ($o) => $o['unlocks'] > 0);
    }

    /** Most products unlocked wins; on a tie, a budget raise beats dropping a must-have. */
    private function ruleBasedPick(array $options): string
    {
        uasort($options, fn ($a, $b) => [$b['unlocks'], $a['type'] === 'raise_budget' ? 0 : 1] <=> [$a['unlocks'], $b['type'] === 'raise_budget' ? 0 : 1]);

        return array_key_first($options);
    }
}
