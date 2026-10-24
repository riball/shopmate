<?php

namespace App\Services\Llm;

/**
 * Anything that can answer a prompt with JSON. The pipeline only talks to this
 * interface, so Groq, the offline rule-based AI and the scripted test AI are
 * interchangeable (the "dependency inversion" idea from OOP).
 *
 * $task tells non-LLM implementations what is being asked
 * ("plan", "replan_pick", "explain", "rewrite"); $context carries the same
 * information as the prompt, but as structured data.
 */
interface LlmClient
{
    /**
     * @throws LlmException when no usable JSON comes back
     */
    public function json(string $task, string $system, string $user, array $context = []): array;

    /** Shown to the shopper, e.g. "Groq · openai/gpt-oss-120b" or "Offline AI (rule-based)". */
    public function name(): string;
}
