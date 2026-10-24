<?php

namespace Tests\Support;

use App\Services\Llm\LlmClient;
use App\Services\Llm\OfflineLlm;

/**
 * Test AI: returns prepared answers per task (in order), and falls back to the
 * offline AI for tasks with no prepared answer. Records every call so tests
 * can check what the pipeline asked.
 */
class ScriptedLlm implements LlmClient
{
    /** @var array<string, array<int, array|callable>> */
    private array $script = [];

    public array $calls = [];

    public function __construct(private OfflineLlm $offline) {}

    public function on(string $task, array|callable $answer): self
    {
        $this->script[$task][] = $answer;

        return $this;
    }

    public function json(string $task, string $system, string $user, array $context = []): array
    {
        $this->calls[] = $task;
        if (! empty($this->script[$task])) {
            $answer = array_shift($this->script[$task]);

            return is_callable($answer) ? $answer($context, $this->offline) : $answer;
        }

        return $this->offline->json($task, $system, $user, $context);
    }

    public function name(): string
    {
        return 'Scripted test AI';
    }
}
