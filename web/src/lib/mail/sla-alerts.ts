import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import { DocumentStatus, label as statusLabel } from '@/lib/domain/document-status';

/**
 * The three sweep emails: an SLA warning, an SLA breach, and a stalled hold.
 *
 * All three go to STAFF, not the citizen — they name the assignee's own queue
 * and are sent by the scheduled sweeps, deduped through the `sla_*_notified_at`
 * / `hold_reminder_sent_at` columns so a document is chased once per stage.
 */
export type StaffAlertDocument = {
  id: number;
  tracking_number: string;
  document_type: string | null;
  citizen_name: string | null;
  status: string;
};

/** Staff open requests through the oversight page, not the citizen tracker. */
function staffUrl(document: StaffAlertDocument): string {
  return absoluteUrl(`/admin/requests?open=${encodeURIComponent(document.tracking_number)}`);
}

function detailRows(document: StaffAlertDocument, extra: Array<[string, string]> = []): string {
  const rows: Array<[string, string]> = [
    ['Tracking Number', document.tracking_number],
    ['Document Type', document.document_type ?? '—'],
    ['Citizen Name', document.citizen_name ?? '—'],
    ['Current Stage', statusLabel(document.status as DocumentStatus)],
    ...extra,
  ];

  return rows
    .map(
      ([key, value]) =>
        `      <tr><td style="color:#666;width:40%;padding:8px 0;border-bottom:1px solid #f0f0f0">${e(key)}</td><td style="font-weight:600;padding:8px 0;border-bottom:1px solid #f0f0f0">${e(value)}</td></tr>`,
    )
    .join('\n');
}

function steps(items: string[]): string {
  return `    <h3 style="font-size:14px;text-transform:uppercase;letter-spacing:.05em;color:#555;margin:24px 0 12px">What to do</h3>
    <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.8">
${items.map((item) => `      <li>${e(item)}</li>`).join('\n')}
    </ul>`;
}

/** ~75% of the stage budget used — sent once per stage, before the breach. */
export function slaWarningMail(args: { to: string; document: StaffAlertDocument }): Mailable {
  const stage = statusLabel(args.document.status as DocumentStatus);

  return {
    to: args.to,
    subject: `SLA Warning: ${args.document.tracking_number} is approaching its deadline`,
    html: layout({
      heading: '⚠ SLA Warning — Action Required Soon',
      tone: 'warn',
      body: `    <p>This document has used approximately 75% of its allowed processing time at the <strong>${e(stage)}</strong> stage. Please prioritise it to avoid an SLA breach.</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px">
${detailRows(args.document)}
    </table>
${steps([
  'Open the request and move it on if the work is done.',
  'If you are blocked, put it On Hold with a reason — that pauses the clock honestly.',
  'If it is not yours to finish, hand it back to your supervisor.',
])}`,
      cta: { label: 'Open the request', url: staffUrl(args.document) },
    }),
  };
}

/** The stage budget is spent. Logged against the request as a breach. */
export function slaBreachMail(args: { to: string; document: StaffAlertDocument }): Mailable {
  const stage = statusLabel(args.document.status as DocumentStatus);

  return {
    to: args.to,
    subject: `SLA Breach: ${args.document.tracking_number} is overdue`,
    html: layout({
      heading: '🔴 SLA Breach — Immediate Action Required',
      tone: 'danger',
      body: `    <p>The document below has exceeded its allowed SLA window at the <strong>${e(stage)}</strong> stage and is now overdue. This breach has been logged. Immediate processing is required.</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px">
${detailRows(args.document)}
    </table>
${steps([
  'Process the request now, or escalate it to your supervisor.',
  'Record why it slipped — the breach is already on the request’s history.',
])}`,
      cta: { label: 'Open the request', url: staffUrl(args.document) },
    }),
  };
}

/**
 * A hold has run past the date it was meant to resume.
 *
 * `isEscalation` swaps the copy for the supervisor: a held request's SLA clock
 * is paused by design, so nothing else will flag it — which is the whole reason
 * this email exists.
 */
export function holdOverdueMail(args: {
  to: string;
  document: StaffAlertDocument;
  daysOverdue: number;
  blockedBy?: string | null;
  isEscalation?: boolean;
}): Mailable {
  const { daysOverdue, isEscalation = false } = args;
  const days = `${daysOverdue} day${daysOverdue === 1 ? '' : 's'}`;

  const body = isEscalation
    ? `    <p>This request has been parked On Hold past the date it was due to resume, and the hold pauses its SLA clock — so it is <strong>not</strong> appearing in the at-risk list. It needs a decision from a supervisor: resume it, re-date the hold, or close it out.</p>`
    : `    <p>A request you parked On Hold has passed the date it was meant to resume. Its SLA clock is paused while it is held, so nothing else will flag it. Please resume it, set a new hold date, or close it out.</p>`;

  return {
    to: args.to,
    subject: `${isEscalation ? 'Escalation' : 'Hold expired'}: ${args.document.tracking_number} is ${days} over its hold date`,
    html: layout({
      heading: `⏸ Hold Expired — ${days} over`,
      tone: 'warn',
      body: `${body}
    <table style="width:100%;border-collapse:collapse;font-size:14px">
${detailRows(args.document, [
  ['Days Overdue', String(daysOverdue)],
  ...(args.blockedBy ? ([['Waiting On', args.blockedBy]] as Array<[string, string]>) : []),
])}
    </table>
${steps(
  isEscalation
    ? ['Resume the request, re-date the hold, or close it out.', 'Check whether the assignee is actually blocked.']
    : ['Resume it if the blocker has cleared.', 'Set a new hold date if you are still waiting.', 'Close it out if it cannot proceed.'],
)}`,
      cta: { label: 'Open the request', url: staffUrl(args.document) },
    }),
  };
}
