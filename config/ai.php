<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI provider
    |--------------------------------------------------------------------------
    |
    | Which LLM backend powers the document assistant. The system is
    | provider-agnostic: "ollama" runs a fully self-hosted model on-premise
    | (no data leaves the server — important for government records), and
    | "none" disables the LLM entirely (the assistant then answers from a
    | deterministic, rule-based grounding instead). A "claude" provider can be
    | added later without touching callers.
    |
    */

    'provider' => env('AI_PROVIDER', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        // Seconds to wait for a generation before falling back to rule-based.
        'timeout' => (int) env('OLLAMA_TIMEOUT', 30),
    ],

];
