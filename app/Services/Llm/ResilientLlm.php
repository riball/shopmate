<?php

namespace App\Services\Llm;

/**
 * Tries the main AI (Groq) first. If it fails, answers with the offline
 * rule-based AI instead and remembers why, so the trace can tell the shopper.
 */
class ResilientLlm implements LlmClient
{
    private ?string $lastFallbackReason = null;

    public function __construct(private LlmClient $primary, private LlmClient $fallback) {}

    public function json(string $task, string $system, string $user, array $context = []): array
    {
        $this->lastFallbackReason = null;
        try {
            return $this->primary->json($task, $system, $user, $context);
        } catch (LlmException $e) {
            $this->lastFallbackReason = $e->getMessage();
            report($e);

            return $this->fallback->json($task, $system, $user, $context);
        }
    }

    public function name(): string
    {
        return $this->primary->name();
    }

    /** Why the last call used the offline AI, or null if the main AI answered. */
    public function lastFallbackReason(): ?string
    {
        return $this->lastFallbackReason;
    }
}
