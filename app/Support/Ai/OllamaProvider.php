<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Self-hosted LLM via Ollama (https://ollama.com). Runs a local model such as
 * llama3.2 or phi3 — no external API, no data egress. Any failure (server down,
 * model not pulled, timeout) returns null so the assistant degrades gracefully.
 */
class OllamaProvider implements LlmProvider
{
    public function __construct(
        private string $url,
        private string $model,
        private int $timeout = 30,
        private string $keepAlive = '30m',
    ) {}

    public function chat(string $system, string $userMessage): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->endpoint('/api/chat'), [
                    'model' => $this->model,
                    'stream' => false,
                    // Keep the model resident so the next request stays warm.
                    'keep_alive' => $this->keepAlive,
                    'options' => [
                        // Low temperature: this is factual lookup, not creative writing.
                        'temperature' => 0.2,
                    ],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Ollama chat failed', ['status' => $response->status()]);

                return null;
            }

            $content = trim((string) $response->json('message.content', ''));

            return $content !== '' ? $content : null;
        } catch (Throwable $e) {
            Log::warning('Ollama unreachable: '.$e->getMessage());

            return null;
        }
    }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(2)->get($this->endpoint('/api/tags'))->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->url, '/').$path;
    }
}
