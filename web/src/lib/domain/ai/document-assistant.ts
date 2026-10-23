import { db } from '@/lib/db';
import { DocumentStatus, FLOW, label as statusLabel, position } from '@/lib/domain/document-status';
import { statusOf } from '@/lib/domain/document';
import { predict } from '@/lib/domain/completion-predictor';
import { llm as defaultLlm, type Llm } from '@/lib/domain/ai/llm';
import * as RequirementsAnswer from '@/lib/domain/ai/requirements-answer';
import type { Answer } from '@/lib/domain/ai/site-assistant';
import { formatNaive, now } from '@/lib/time';

/**
 * Port of `App\Support\Ai\DocumentAssistant` — "where is my document?".
 *
 * A tightly scoped RAG over two public-safe sources: the facts of the ONE
 * request in view (status, progress, handling OFFICE, predicted completion)
 * and the services catalogue. It never sees another citizen's record. Common
 * questions (status / when / who / where) and finished requests are answered
 * instantly from the facts; only open-ended ones reach the LLM, which is told
 * to use the facts and nothing else.
 */
type Facts = {
  trackingNumber: string;
  documentType: string;
  applicant: string | null;
  statusLabel: string;
  /** The office, never the staff member: staff stay anonymous to the public. */
  handler: string | null;
  submitted: string | null;
  lastUpdate: string | null;
  completedOn: string | null;
  etaReadyBy: string | null;
  etaDays: number | null;
  requirements: string[];
  flow: Array<{ name: string; stage: 'done' | 'current' | 'upcoming' }>;
  isCompleted: boolean;
};

const WHEN_WORDS = ['when', 'how long', 'ready', 'finish', 'done', 'complete', 'eta', 'days', 'time', 'wait', 'soon'];
const WHO_WORDS = ['who', 'handler', 'handling', 'assigned', 'staff'];
const WHERE_WORDS = ['where', 'status', 'location', 'update', 'progress', 'stage', 'stand'];

const has = (q: string, words: string[]): boolean => words.some((word) => q.includes(word));

async function factsFor(trackingNumber: string): Promise<Facts | null> {
  const document = await db.documents.findFirst({
    where: { tracking_number: trackingNumber, deleted_at: null },
    select: {
      tracking_number: true,
      document_type: true,
      citizen_name: true,
      status: true,
      created_at: true,
      updated_at: true,
      status_changed_at: true,
      completed_at: true,
      department: { select: { name: true } },
    },
  });

  if (!document) {
    return null;
  }

  const stage = statusOf(document);
  const current = position(stage);
  const flow = FLOW.map((status) => {
    const at = position(status);
    const state: Facts['flow'][number]['stage'] =
      stage === DocumentStatus.Completed
        ? 'done'
        : current === 0
          ? 'upcoming'
          : at < current
            ? 'done'
            : at === current
              ? 'current'
              : 'upcoming';

    return { name: statusLabel(status), stage: state };
  });

  const prediction = await predict(document);
  const showEta = prediction.available && stage !== DocumentStatus.Completed && prediction.eta !== null;

  const type = await db.request_types.findFirst({
    where: { name: { equals: document.document_type, mode: 'insensitive' } },
    select: {
      request_type_requirements: { select: { label: true }, orderBy: [{ sort_order: 'asc' }, { id: 'asc' }] },
    },
  });

  const lastUpdate = document.status_changed_at ?? document.updated_at;

  return {
    trackingNumber: document.tracking_number,
    documentType: document.document_type,
    applicant: document.citizen_name,
    statusLabel: statusLabel(stage),
    handler: document.department?.name ?? null,
    submitted: document.created_at ? formatNaive(document.created_at, 'M d, Y') : null,
    lastUpdate: lastUpdate ? formatNaive(lastUpdate, 'M d, Y') : null,
    completedOn: document.completed_at ? formatNaive(document.completed_at, 'M d, Y') : null,
    etaReadyBy: showEta ? formatNaive(prediction.eta as Date, 'M d, Y') : null,
    etaDays: showEta ? Math.max(1, Math.ceil(prediction.remainingHours / 24)) : null,
    requirements: type?.request_type_requirements.map((item) => item.label) ?? [],
    flow,
    isCompleted: stage === DocumentStatus.Completed,
  };
}

function factsToText(d: Facts): string {
  const lines = [
    `Tracking number: ${d.trackingNumber}`,
    `Document type: ${d.documentType}`,
    `Applicant: ${d.applicant ?? 'N/A'}`,
    `Submitted: ${d.submitted}`,
    `Current status: ${d.statusLabel}`,
    `Handled by (office): ${d.handler ?? 'Not yet routed to an office'}`,
    `Last updated: ${d.lastUpdate ?? 'N/A'}`,
  ];

  if (d.requirements.length > 0) {
    lines.push(`Requirements for ${d.documentType}: ${d.requirements.join('; ')}`);
  }

  lines.push(`Progress: ${d.flow.map((step) => `${step.name} (${step.stage})`).join(' > ')}`);

  if (d.etaReadyBy) {
    lines.push(
      `Estimated ready by: ${d.etaReadyBy} (about ${d.etaDays} day(s) from now, estimated from how long similar past documents took)`,
    );
  }

  if (d.completedOn) {
    lines.push(`Completed on: ${d.completedOn}`);
  }

  lines.push(`Today's date: ${formatNaive(now(), 'M d, Y')}`);

  return lines.join('\n');
}

function systemPrompt(facts: string): string {
  return `You are a warm, concise assistant for the SPeEdtracQR government document tracker. Answer the citizen using ONLY the facts below. If it is not in the facts, say you don't have that info and suggest contacting the handling office. Never invent details. Reply in 1-2 short sentences.
FACTS:
${facts}`;
}

function ruleBasedAnswer(d: Facts, question: string): string {
  const tn = d.trackingNumber;
  const q = question.toLowerCase();
  const stage = d.statusLabel.toLowerCase();

  if (d.isCompleted) {
    return `Good news — document ${tn} is already completed and ready. To claim it, bring your QR code (from your confirmation email or printed slip) to the City Hall counter; staff scan it to release your document.`;
  }

  const handler = d.handler ? `It is being handled by the ${d.handler}.` : 'It has not been routed to an office yet.';

  if (has(q, WHEN_WORDS)) {
    if (d.etaReadyBy) {
      return `Based on how long similar ${d.documentType} requests have taken, document ${tn} is likely ready by around ${d.etaReadyBy} — about ${d.etaDays} day(s) from now. It is currently ${stage}. This is an estimate and may change.`;
    }

    return `Document ${tn} (${d.documentType}) is currently ${stage}, but I don't have enough history yet to estimate a completion date. ${handler}`;
  }

  return `Document ${tn} (${d.documentType}) is ${has(q, WHO_WORDS) ? '' : 'currently '}${stage}. ${handler}`;
}

export async function answer(
  document: { tracking_number: string; document_type: string },
  question: string,
  model: Llm = defaultLlm(),
): Promise<Answer> {
  // Requirements first, so it wins even for a completed request.
  if (RequirementsAnswer.asks(question)) {
    const requirements = await RequirementsAnswer.resolve(question, document.document_type);

    if (requirements) {
      return { answer: RequirementsAnswer.text(requirements), source: 'fallback' };
    }
  }

  const facts = await factsFor(document.tracking_number);

  if (!facts) {
    return { answer: "I can't find that request any more.", source: 'fallback' };
  }

  const q = question.toLowerCase();

  if (facts.isCompleted || has(q, [...WHEN_WORDS, ...WHO_WORDS, ...WHERE_WORDS])) {
    return { answer: ruleBasedAnswer(facts, question), source: 'fallback' };
  }

  const reply = await model.chat(systemPrompt(factsToText(facts)), question);

  if (reply !== null) {
    return { answer: reply, source: 'ai' };
  }

  return { answer: ruleBasedAnswer(facts, question), source: 'fallback' };
}
