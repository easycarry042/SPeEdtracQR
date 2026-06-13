<?php

namespace App\Support\Ai;

/**
 * No-op provider used when AI is disabled (AI_PROVIDER=none). The assistant
 * always falls back to its deterministic, rule-based grounding.
 */
class NullProvider implements LlmProvider
{
    public function chat(string $system, string $userMessage): ?string
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
