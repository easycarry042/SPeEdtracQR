/**
 * Port of `App\Support\Ai\LlmProvider` / `OllamaProvider` / `NullProvider`.
 *
 * Provider-agnostic: callers get a reply or null, never an exception, so every
 * assistant falls back to its deterministic answer when the model is down,
 * slow or not configured. `AI_PROVIDER=ollama` (the default, as in
 * config/ai.php) talks to a self-hosted Ollama; `none` disables the model.
 *
 * On Vercel, OLLAMA_URL must point at a reachable host — 127.0.0.1 is the
 * function itself. Left at the default there, Speedy answers from the FAQ and
 * the request's facts only.
 */
export type Llm = { chat(system: string, userMessage: string): Promise<string | null> };

const int = (value: string | undefined, fallback: number): number => {
  const parsed = Number.parseInt(value ?? '', 10);

  return Number.isFinite(parsed) ? parsed : fallback;
};

export const nullLlm: Llm = { chat: async () => null };

export function ollama(options: {
  url: string;
  model: string;
  timeoutSeconds: number;
  keepAlive: string;
  numPredict: number;
}): Llm {
  return {
    async chat(system, userMessage) {
      try {
        const response = await fetch(`${options.url.replace(/\/$/, '')}/api/chat`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          signal: AbortSignal.timeout(options.timeoutSeconds * 1000),
          body: JSON.stringify({
            model: options.model,
            stream: false,
            // Keep the model resident so the next request stays warm.
            keep_alive: options.keepAlive,
            options: {
              // Factual lookup, not creative writing.
              temperature: 0.2,
              // Answers are 1-3 sentences; capping tokens is the big latency win.
              num_predict: options.numPredict,
              num_ctx: 1024,
            },
            messages: [
              { role: 'system', content: system },
              { role: 'user', content: userMessage },
            ],
          }),
        });

        if (!response.ok) {
          console.warn('[ai] Ollama chat failed', response.status);

          return null;
        }

        const data = (await response.json()) as { message?: { content?: string } };
        const content = (data.message?.content ?? '').trim();

        return content === '' ? null : content;
      } catch (error) {
        console.warn('[ai] Ollama unreachable:', error instanceof Error ? error.message : error);

        return null;
      }
    },
  };
}

export function llm(): Llm {
  if ((process.env.AI_PROVIDER ?? 'ollama') !== 'ollama') {
    return nullLlm;
  }

  return ollama({
    url: process.env.OLLAMA_URL ?? 'http://127.0.0.1:11434',
    model: process.env.OLLAMA_MODEL ?? 'llama3.2',
    timeoutSeconds: int(process.env.OLLAMA_TIMEOUT, 30),
    keepAlive: process.env.OLLAMA_KEEP_ALIVE ?? '30m',
    numPredict: int(process.env.OLLAMA_NUM_PREDICT, 160),
  });
}
