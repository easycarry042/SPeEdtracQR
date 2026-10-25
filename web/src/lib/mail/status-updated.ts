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
  reason?: string | null;
}): Mailable {
  const stage = statusLabel(args.stage);
  const isDenied = args.stage === DocumentStatus.Denied;

  return {
    to: args.to,
    subject: isDenied
      ? `Update on your request ${args.trackingNumber}: Denied`
      : `Update on your request ${args.trackingNumber}: ${stage}`,
    html: layout({
      heading: isDenied ? 'Request Denied' : 'Status update',
      tone: isDenied ? 'danger' : undefined,
      body: isDenied
        ? `    <p>Hello ${e(args.citizenName ?? 'there')},</p>
    <p>We regret to inform you that your request <strong>${e(args.trackingNumber)}</strong> could not be approved and has been denied.</p>
${args.reason ? `    <p><strong>Official Reason:</strong></p>\n    <div class="stage" style="border-left: 3px solid #dc2626; color: #991b1b; background: #fef2f2; padding: 12px; border-radius: 6px;">${e(args.reason)}</div>` : ''}
    <p>Please open your tracking page for full details or contact the office if you need further clarification.</p>`
        : `    <p>Hello ${e(args.citizenName ?? 'there')},</p>
    <p>Your request <strong>${e(args.trackingNumber)}</strong> has a new status:</p>
    <div class="stage">${e(stage)}</div>`,
      cta: { label: isDenied ? 'View request details' : 'View details', url: absoluteUrl(citizenTrackingUrl(args.trackingNumber)) },
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
