import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import { citizenTrackingUrl } from '@/lib/domain/document';
import { DocumentStatus, label as statusLabel } from '@/lib/domain/document-status';

/**
 * Sent to the citizen when their request moves to a new stage, and when a hold
 * is blocked on them.
 *
 * Both are gated by `config/tracking` plus the per-ticket `notify_citizen`
 * flag — see the callers in `status-actions`.
 */
export function statusUpdatedMail(args: {
  to: string;
  trackingNumber: string;
  citizenName: string | null;
  stage: DocumentStatus;
}): Mailable {
  const stage = statusLabel(args.stage);

  return {
    to: args.to,
    subject: `Update on your request ${args.trackingNumber}: ${stage}`,
    html: layout({
      heading: 'Status update',
      body: `    <p>Hello ${e(args.citizenName ?? 'there')},</p>
    <p>Your request <strong>${e(args.trackingNumber)}</strong> has a new status:</p>
    <div class="stage">${e(stage)}</div>`,
      cta: { label: 'View details', url: absoluteUrl(citizenTrackingUrl(args.trackingNumber)) },
    }),
  };
}

/**
 * The hold is waiting on the citizen, so the email has to say what they need to
 * do — a status email alone would just tell them their request stopped.
 */
export function actionNeededMail(args: {
  to: string;
  trackingNumber: string;
  citizenName: string | null;
  reason?: string | null;
}): Mailable {
  return {
    to: args.to,
    subject: `Action needed on your request ${args.trackingNumber}`,
    html: layout({
      heading: 'We need something from you',
      tone: 'warn',
      body: `    <p>Hello ${e(args.citizenName ?? 'there')},</p>
    <p>Your request <strong>${e(args.trackingNumber)}</strong> is paused until we hear back from you.</p>
${args.reason ? `    <div class="stage">${e(args.reason)}</div>` : ''}
    <p>Open your tracking page for the details, or reply to the office through the messages there.</p>`,
      cta: { label: 'Open my request', url: absoluteUrl(citizenTrackingUrl(args.trackingNumber)) },
    }),
  };
}
