import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import { citizenTrackingUrl } from '@/lib/domain/document';

/**
 * Tells the citizen the office has replied, and links them to the thread.
 *
 * Deliberately carries NO message body: the conversation is readable only after
 * the citizen confirms the email or mobile on file, and a mailbox is not that
 * gate. It also names the office rather than the staff member — staff are
 * anonymous to the public.
 */
export function staffMessageMail(args: {
  to: string;
  trackingNumber: string;
  citizenName: string | null;
  officeName?: string | null;
}): Mailable {
  const office = args.officeName ?? 'City Hall';

  return {
    to: args.to,
    subject: `${office} replied about your request ${args.trackingNumber}`,
    html: layout({
      heading: 'You have a new message',
      body: `    <p>Hello ${e(args.citizenName ?? 'there')},</p>
    <p><strong>${e(office)}</strong> has replied about your request <strong>${e(args.trackingNumber)}</strong>.</p>
    <p>Open your tracking page to read it. You will be asked to confirm the email or mobile number you filed with first — that is what keeps the conversation private to you.</p>`,
      cta: {
        label: 'Read the message',
        url: absoluteUrl(`${citizenTrackingUrl(args.trackingNumber)}&speedy=messages`),
      },
    }),
  };
}
