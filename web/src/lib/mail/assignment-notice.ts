import { app } from '@/lib/config';
import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import { routes } from '@/lib/routes';

/**
 * Sent to a staff member when a supervisor assigns them a request. Gated by
 * `tracking.notifyStaffOnAssignment`.
 *
 * Unlike the citizen mail, this one names people freely — it is internal, and
 * the staff-anonymity rule is about public surfaces only.
 */
export function assignmentNoticeMail(args: {
  to: string;
  assigneeName: string;
  trackingNumber: string;
  documentType: string;
}): Mailable {
  return {
    to: args.to,
    subject: `New assignment: ${args.trackingNumber}`,
    html: layout({
      heading: 'A request was assigned to you',
      body: `    <p>Hello ${e(args.assigneeName)},</p>
    <p>You have been assigned <strong>${e(args.documentType)}</strong>.</p>
    <div class="stage">${e(args.trackingNumber)}</div>
    <p>Open it on your dashboard to accept it and start work. ${e(app.name)} will not move it on until you do.</p>`,
      cta: {
        label: 'Open my dashboard',
        url: absoluteUrl(routes.staffDashboard({ open: args.trackingNumber })),
      },
    }),
  };
}
