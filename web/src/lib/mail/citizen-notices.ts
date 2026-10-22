import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import { citizenTrackingUrl } from '@/lib/domain/document';

/**
 * The last three mailables: two that tell STAFF a citizen is waiting, and one
 * that tells the CITIZEN a requirement needs re-uploading.
 *
 * The direction matters for the anonymity rule — the citizen-facing one names
 * the handling office and never the staff member who reviewed the file.
 */

/** Staff open requests through the oversight page, not the citizen tracker. */
function staffUrl(trackingNumber: string): string {
  return absoluteUrl(`/admin/requests?open=${encodeURIComponent(trackingNumber)}`);
}

/** A citizen attached files to a request — the assignee needs to look. */
export function citizenUploadMail(args: {
  to: string;
  trackingNumber: string;
  documentType: string | null;
  citizenName: string | null;
  assigneeName: string | null;
  fileCount: number;
  citizenNote?: string | null;
}): Mailable {
  const files = `${args.fileCount} file${args.fileCount === 1 ? '' : 's'}`;

  return {
    to: args.to,
    subject: `Citizen uploaded documents — ${args.trackingNumber}`,
    html: layout({
      heading: 'New files from a citizen',
      body: `    <p>A citizen uploaded ${e(files)} for a ticket assigned to ${e(args.assigneeName ?? 'you')}.</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px">
      <tr><td style="color:#666;width:40%;padding:8px 0;border-bottom:1px solid #f0f0f0">Tracking #</td><td style="font-weight:600;padding:8px 0;border-bottom:1px solid #f0f0f0">${e(args.trackingNumber)}</td></tr>
      <tr><td style="color:#666;padding:8px 0;border-bottom:1px solid #f0f0f0">Document type</td><td style="font-weight:600;padding:8px 0;border-bottom:1px solid #f0f0f0">${e(args.documentType ?? 'N/A')}</td></tr>
      <tr><td style="color:#666;padding:8px 0;border-bottom:1px solid #f0f0f0">Citizen</td><td style="font-weight:600;padding:8px 0;border-bottom:1px solid #f0f0f0">${e(args.citizenName ?? 'N/A')}</td></tr>
    </table>
${args.citizenNote ? `    <p><strong>Note from citizen:</strong></p>\n    <div class="stage">${e(args.citizenNote)}</div>` : ''}`,
      cta: { label: 'Open document', url: staffUrl(args.trackingNumber) },
    }),
  };
}

/** A citizen posted on the request thread — staff are being waited on. */
export function citizenMessageMail(args: {
  to: string;
  recipientName: string;
  trackingNumber: string;
  citizenName: string | null;
  body: string;
}): Mailable {
  return {
    to: args.to,
    subject: `New citizen message on ${args.trackingNumber}`,
    html: layout({
      heading: 'A citizen is waiting on an answer',
      body: `    <p>Hello ${e(args.recipientName)},</p>
    <p>${e(args.citizenName ?? 'The requester')} posted a message on <strong>${e(args.trackingNumber)}</strong>:</p>
    <div class="stage">${e(args.body)}</div>
    <p>Reply from the request&rsquo;s Collaboration panel — your reply is sent straight back to them.</p>`,
      cta: { label: 'Open the request', url: staffUrl(args.trackingNumber) },
    }),
  };
}

/**
 * A reviewed requirement came back rejected or needing revision.
 *
 * Citizen-facing, so it names neither the reviewer nor their office beyond "our
 * staff" — the same anonymity the tracking page keeps.
 */
export function requirementRevisionMail(args: {
  to: string;
  trackingNumber: string;
  citizenName: string | null;
  requirementLabel: string;
  reviewStatusLabel: string;
  reviewComment?: string | null;
  /** Rejected is terminal for that file; revision invites a re-upload. */
  rejected: boolean;
}): Mailable {
  const verb = args.rejected ? 'was rejected' : 'needs revision';

  return {
    to: args.to,
    subject: `A document ${verb} — ${args.trackingNumber}`,
    html: layout({
      heading: args.rejected ? '✕ A document was rejected' : '↻ A document needs revision',
      tone: args.rejected ? 'danger' : 'warn',
      body: `    <p>Hello ${e(args.citizenName ?? 'there')},</p>
    <p>For your request <strong>${e(args.trackingNumber)}</strong>, our staff reviewed the following document:</p>
    <div class="stage">${e(args.requirementLabel)} — ${e(args.reviewStatusLabel)}</div>
${args.reviewComment ? `    <p>${e(args.reviewComment)}</p>` : ''}
    <p>${
      args.rejected
        ? 'This document was not accepted. You can view the reason above on your tracking page. Please contact the office if you have questions.'
        : 'Please open your tracking page and re-upload only this document with the correction above. You do not need to resubmit anything else.'
    }</p>`,
      cta: args.rejected
        ? { label: 'Open my request', url: absoluteUrl(citizenTrackingUrl(args.trackingNumber)) }
        : { label: 'Re-upload the document', url: absoluteUrl(citizenTrackingUrl(args.trackingNumber)) },
    }),
  };
}
