<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Groq's OpenAI-compatible chat API, asked to reply in JSON mode.
 * Rate limits (HTTP 429) and server errors are retried with a short wait.
 */
class GroqClient implements LlmClient
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.groq.com/openai/v1',
        private int $timeout = 60,
    ) {}

    public function json(string $task, string $system, string $user, array $context = []): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->retry(3, 1500, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503], true)))
                ->post('/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new LlmException("Groq returned HTTP {$e->response->status()} for task '{$task}'.", previous: $e);
        } catch (ConnectionException $e) {
            throw new LlmException("Could not reach Groq for task '{$task}'.", previous: $e);
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $decoded = json_decode($this->stripFences($content), true);
        if (! is_array($decoded)) {
            throw new LlmException("Groq did not return valid JSON for task '{$task}'.");
        }

        return $decoded;
    }

    public function name(): string
    {
        return "Groq · {$this->model}";
    }

    private function stripFences(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content);
        }

        return $content;
    }
}
