import { db } from '@/lib/db';
import * as SiteFaq from '@/lib/domain/site-faq';
import type { FaqEntry } from '@/lib/domain/site-faq';
import { llm as defaultLlm, type Llm } from '@/lib/domain/ai/llm';
import * as RequirementsAnswer from '@/lib/domain/ai/requirements-answer';

/**
 * Port of `App\Support\Ai\SiteAssistant` — Speedy before a request is named.
 *
 * Grounded in two public sources only: the site FAQ and the services
 * catalogue. Deterministic first, the model last: a greeting, a requirements
 * question or an FAQ match is answered instantly from the source text. Only a
 * question none of those cover reaches the LLM, told to answer from the same
 * text and nothing else.
 */
export type Answer = { answer: string; source: 'fallback' | 'faq' | 'ai' };

export const OWN_REQUEST_HINT =
  'If you tell me your tracking number (it looks like SPD-20261016-ABC123), I can check yours.';

const INTRODUCTION =
  "Hi, I'm Speedy! I can explain how filing and tracking work, list the requirements for a service, " +
  'or check on your request if you give me its tracking number.';

function isGreeting(q: string): boolean {
  return /^(hi|hello|hey|good (morning|afternoon|evening)|kumusta|magandang (umaga|hapon|gabi))\b[\s!.,?]*$/u.test(q);
}

/** The FAQ entry whose keywords the question hits most; null when none hit. */
function bestFaq(q: string): FaqEntry | null {
  let best: FaqEntry | null = null;
  let bestScore = 0;

  for (const entry of [...SiteFaq.all(), ...SiteFaq.assistantOnly()]) {
    const score = entry.keywords.filter((keyword) => q.includes(keyword)).length;

    if (score > bestScore) {
      best = entry;
      bestScore = score;
    }
  }

  return best;
}

async function activeServiceNames(limit?: number): Promise<string[]> {
  const rows = await db.request_types.findMany({
    where: { is_active: true },
    orderBy: { name: 'asc' },
    select: { name: true },
    ...(limit ? { take: limit } : {}),
  });

  return rows.map((row) => row.name);
}

async function systemPrompt(): Promise<string> {
  const faq = [...SiteFaq.all(), ...SiteFaq.assistantOnly()]
    .map((entry) => `Q: ${entry.question}\nA: ${entry.answerText}`)
    .join('\n\n');
  const services = (await activeServiceNames()).join(', ');

  // Deliberately short: fewer prompt tokens = faster prefill on CPU.
  return `You are Speedy, the assistant of SPeED TraQR, the City Government of San Pedro, Laguna's request tracking site. Answer ONLY from the FAQ and the services list below. If the answer is not there, say you don't know and suggest emailing the office. If the citizen asks about their own request, ask for its tracking number. Never invent details, dates or fees. Reply in 1-2 short sentences.
SERVICES: ${services}
FAQ:
${faq}`;
}

export async function answer(question: string, model: Llm = defaultLlm()): Promise<Answer> {
  const q = question.trim().toLowerCase();

  if (isGreeting(q)) {
    return { answer: INTRODUCTION, source: 'fallback' };
  }

  if (q.includes('thank') || q.includes('salamat')) {
    return { answer: "You're welcome! Anything else I can help with?", source: 'fallback' };
  }

  if (RequirementsAnswer.asks(question)) {
    const requirements = await RequirementsAnswer.resolve(question);

    if (requirements) {
      return { answer: RequirementsAnswer.text(requirements), source: 'fallback' };
    }

    const examples = (await activeServiceNames(6)).join(', ') || 'a Business Permit';

    return { answer: `Which service is it for? For example: ${examples}.`, source: 'fallback' };
  }

  const faq = bestFaq(q);

  if (faq) {
    return {
      answer: faq.aboutOwnRequest ? `${faq.answerText}\n${OWN_REQUEST_HINT}` : faq.answerText,
      source: 'faq',
    };
  }

  const reply = await model.chat(await systemPrompt(), question);

  if (reply !== null) {
    return { answer: reply, source: 'ai' };
  }

  return { answer: `I'm not sure about that one. ${INTRODUCTION}`, source: 'fallback' };
}
