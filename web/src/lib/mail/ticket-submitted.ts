import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import { citizenTrackingUrl } from '@/lib/domain/document';

/**
 * Sent to the citizen right after they submit an online ticket: their tracking
 * number, a link to the public tracking page, and the QR code (if generated).
 *
 * Port of `App\Mail\TicketSubmitted`. The QR rides along as an attachment so the
 * citizen has it even offline — many arrive at the counter with only the printed
 * email, and a remote <img> would not survive an offline inbox.
 */
export function ticketSubmittedMail(args: {
  to: string;
  trackingNumber: string;
  citizenName: string | null;
  documentType: string | null;
  /** The QR PNG just rendered for the request, or null if that failed. */
  qrPng: Buffer | null;
}): Mailable {
  const trackingUrl = absoluteUrl(citizenTrackingUrl(args.trackingNumber));

  const attachments: Mailable['attachments'] = [];

  if (args.qrPng) {
    attachments.push({ filename: 'tracking-qr.png', content: args.qrPng });
  }

  return {
    to: args.to,
    subject: `We received your request — ${args.trackingNumber}`,
    attachments: attachments.length > 0 ? attachments : undefined,
    html: layout({
      heading: 'We received your request',
      body: `    <p>Hello ${e(args.citizenName ?? 'there')},</p>
    <p>Your request${args.documentType ? ` for <strong>${e(args.documentType)}</strong>` : ''} has been filed. Keep this tracking number — it is how you follow the request and claim the result:</p>
    <div class="stage">${e(args.trackingNumber)}</div>
    <p>The QR code is attached to this email. Show it at the office and staff can pull up your request without typing anything.</p>`,
      cta: { label: 'Track my request', url: trackingUrl },
    }),
  };
}
