/**
 * Port of `App\Support\ActivityNarrative` — one activity-log line split into a
 * tone, a headline and the detail beneath it, so the entry that matters is not
 * lost in a column of identical prose.
 */
export type Narrative = { tone: 'red' | 'amber' | 'green' | 'blue' | 'note'; headline: string; detail: string | null };

/** Trouble: work came back, was refused, or ran past its own deadline. */
const RED = ['returned for revision', 'rejected', 'denied', 'overdue', 'breach', 'without a qr scan', 'cancelled', 'canceled'];
/** Watch: not wrong yet, but waiting on someone. */
const AMBER = ['needs revision', 'on hold', 'held', 'nudged', 'chased', 're-uploaded', 'reminder'];
/** Finished well. */
const GREEN = ['completed', 'approved', 'released', 'verified', 'signed'];

/** Spatie logs a model event as the bare verb, which tells a reader nothing. */
const MODEL_EVENTS: Record<string, string> = {
  created: 'Record created',
  updated: 'Record updated',
  deleted: 'Record deleted',
  restored: 'Record restored',
};

const escape = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * Severity wins over outcome, checked worst-first, whole words only —
 * "Assigned to Paolo" contains "signed", and a substring match turned a routine
 * handover green.
 */
export function tone(description: string): Narrative['tone'] {
  const haystack = description.toLowerCase();

  for (const [name, needles] of [
    ['red', RED],
    ['amber', AMBER],
    ['green', GREEN],
  ] as const) {
    if (needles.some((needle) => new RegExp(`\\b${escape(needle)}\\b`, 'u').test(haystack))) {
      return name;
    }
  }

  return 'blue';
}

function pair(headline: string, detail: string): [string, string | null] {
  // "reason: Sticker torn off…" — the label is noise once the detail has its own line.
  const cleaned = detail.trim().replace(/^reason:\s*/i, '');

  return [headline.trim(), cleaned === '' ? null : cleaned.charAt(0).toUpperCase() + cleaned.slice(1)];
}

/**
 * Cut at the first separator the log itself uses: an em/en dash, or a colon
 * that comes after any quoted name has closed ('Reviewed requirement
 * "Cedula": Rejected' keeps the name in the headline).
 */
function split(text: string): [string, string | null] {
  for (const dash of [' — ', ' – ']) {
    const at = text.indexOf(dash);

    if (at !== -1) {
      return pair(text.slice(0, at), text.slice(at + dash.length));
    }
  }

  const afterQuote = text.lastIndexOf('"');
  const at = text.indexOf(': ', afterQuote === -1 ? 0 : afterQuote + 1);

  return at === -1 ? [text, null] : pair(text.slice(0, at), text.slice(at + 2));
}

export function narrate(description: string): Narrative {
  const trimmed = description.trim();
  const text = MODEL_EVENTS[trimmed.toLowerCase()] ?? trimmed;
  const [headline, detail] = split(text);

  return { tone: tone(text), headline, detail };
}
