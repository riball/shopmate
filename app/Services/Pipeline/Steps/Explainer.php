<?php

namespace App\Services\Pipeline\Steps;

use App\Services\Llm\LlmClient;
use App\Services\Pipeline\FactSheet;
use App\Services\Pipeline\PipelineContext;
use App\Services\Pipeline\Prompts;

/**
 * Explain (AI): the AI writes short sentences ("claims") about each pick.
 * Every claim must say which trace step its facts came from and list those
 * facts, so the Verifier can check it. The AI cannot add products: it only
 * sees the picks, and claims about anything else are rejected.
 */
class Explainer implements PipelineStep
{
    use UsesLlm;

    public function __construct(LlmClient $llm)
    {
        $this->llm = $llm;
    }

    public function handle(PipelineContext $context): StepOutcome
    {
        $sheet = new FactSheet($context);
        $payload = ['request' => $context->session->fullQuery(), 'products' => $sheet->forPrompt()];

        $answer = $this->ask($context, 'explain', Prompts::explainer(),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $payload,
        );
        $context->drafts = $this->normalize($answer['claims'] ?? []);

        $context->trace->note('explain', sprintf('The AI drafted %d claim(s) about %d product(s). Checking each one before showing it.',
            count($context->drafts), count($context->picks)));

        return StepOutcome::Continue;
    }

    /** One rewrite for the claims that failed, with the reasons they failed. */
    public function rewrite(PipelineContext $context, array $failed): array
    {
        $sheet = new FactSheet($context);
        $payload = [
            'products' => $sheet->forPrompt(),
            'failed_claims' => array_map(fn ($f) => ['claim' => $f['claim'], 'reason' => $f['reason']], $failed),
        ];

        $answer = $this->ask($context, 'rewrite', Prompts::rewrite(),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $payload,
        );

        return $this->normalize($answer['claims'] ?? []);
    }

    /** Keep only well-formed claims; the Verifier decides if they're true. */
    private function normalize(mixed $claims): array
    {
        if (! is_array($claims)) {
            return [];
        }
        $out = [];
        foreach ($claims as $claim) {
            if (! is_array($claim) || ! isset($claim['text']) || ! is_string($claim['text'])) {
                continue;
            }
            $out[] = [
                'product_id' => (int) ($claim['product_id'] ?? 0),
                'text' => trim($claim['text']),
                'cites' => is_string($claim['cites'] ?? null) ? strtoupper(trim($claim['cites'])) : null,
                'facts' => is_array($claim['facts'] ?? null) ? array_values($claim['facts']) : [],
            ];
        }

        return $out;
    }
}
