<?php

namespace App\Support\Ai;

/**
 * Provider-agnostic LLM contract. Concrete drivers (Ollama today, Claude later)
 * implement this; callers never depend on a specific vendor.
 */
interface LlmProvider
{
    /**
     * Generate a reply given a system prompt (instructions + grounding facts)
     * and the user's message. Returns null on any failure so the caller can
     * fall back to a deterministic, rule-based answer.
     */
    public function chat(string $system, string $userMessage): ?string;

    /** Whether this provider is configured and reachable. */
    public function isAvailable(): bool;
}
