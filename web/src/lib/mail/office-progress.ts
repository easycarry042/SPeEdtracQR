import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import { routes } from '@/lib/routes';

export type ProgressStep = 'accepted' | 'finished';

/**
 * Sent to the handling office's supervisors when the assignee accepts a
 * request and again when they finish it — CTCAO's "notify the admin" steps.
 *
 * Internal mail, so it names the staff member (the anonymity rule covers
 * citizen surfaces only).
 */
export function officeProgressMail(args: {
  to: string;
  supervisorName: string;
  staffName: string;
  trackingNumber: string;
  documentType: string;
  step: ProgressStep;
}): Mailable {
  const accepted = args.step === 'accepted';

  return {
    to: args.to,
    subject: accepted
      ? `Accepted: ${args.trackingNumber} is now Ongoing`
      : `Done: ${args.trackingNumber}`,
    html: layout({
      heading: accepted ? 'A request was accepted' : 'A request is done',
      body: `    <p>Hello ${e(args.supervisorName)},</p>
    <p><strong>${e(args.staffName)}</strong> ${accepted ? 'accepted' : 'finished'} <strong>${e(args.documentType)}</strong>.</p>
    <div class="stage">${e(args.trackingNumber)}</div>
    <p>${accepted ? 'It is now Ongoing.' : 'It is now Done and listed under Completed on your dashboard.'}</p>`,
      cta: {
        label: 'Open the request',
        url: absoluteUrl(
          routes.dashboard({ tab: accepted ? 'inprogress' : 'completed', open: args.trackingNumber }),
        ),
      },
    }),
  };
}
