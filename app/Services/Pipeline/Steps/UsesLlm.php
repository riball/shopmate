<?php

namespace App\Services\Pipeline\Steps;

use App\Services\Llm\LlmClient;
use App\Services\Llm\ResilientLlm;
use App\Services\Pipeline\PipelineContext;

/** Shared helper for AI steps: asks the AI and notes in the trace if the offline fallback answered. */
trait UsesLlm
{
    protected LlmClient $llm;

    protected function ask(PipelineContext $context, string $task, string $system, string $user, array $data = []): array
    {
        $answer = $this->llm->json($task, $system, $user, $data);

        if ($this->llm instanceof ResilientLlm && ($reason = $this->llm->lastFallbackReason())) {
            $context->trace->note($task, "The main AI was unavailable ({$reason}), so the offline rule-based AI answered this step.", 'warning');
        }

        return $answer;
    }
}
