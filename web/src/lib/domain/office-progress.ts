import { toId } from '@/lib/db';
import { tracking } from '@/lib/config';
import { queueMail } from '@/lib/mail/send';
import { officeProgressMail, type ProgressStep } from '@/lib/mail/office-progress';
import { notifyMany } from '@/lib/domain/notifications';
import { departmentTriagers } from '@/lib/domain/triagers';
import { routes } from '@/lib/routes';

/**
 * Tell the handling office's supervisors (S/Admin) that the assignee accepted
 * a request or finished it — the two "notify the admin" boxes on CTCAO's
 * workflow. Bell always; email unless `TRACKING_NOTIFY_OFFICE_PROGRESS=false`.
 *
 * Recipients follow `departmentTriagers`: the office's supervisors, or the
 * super admins when the office has none. The actor is never told about their
 * own move.
 */
export async function notifyOfficeOfProgress(
  document: {
    tracking_number: string;
    document_type: string;
    department_id: bigint | number | null;
  },
  actor: { id: number; name: string },
  step: ProgressStep,
): Promise<number[]> {
  const departmentId = document.department_id === null ? null : toId(document.department_id);
  const supervisors = await departmentTriagers(departmentId, actor.id);

  if (supervisors.length === 0) {
    return [];
  }

  const accepted = step === 'accepted';

  await notifyMany(
    supervisors.map((supervisor) => supervisor.id),
    {
      event: accepted ? 'accepted' : 'finished',
      title: accepted
        ? `Accepted: ${document.tracking_number}`
        : `Done: ${document.tracking_number}`,
      body: accepted
        ? `${actor.name} accepted ${document.document_type}. It is now Ongoing.`
        : `${actor.name} finished ${document.document_type}.`,
      url: routes.dashboard({
        tab: accepted ? 'inprogress' : 'completed',
        open: document.tracking_number,
      }),
      tracking_number: document.tracking_number,
    },
  );

  if (tracking.notifyOfficeProgress) {
    for (const supervisor of supervisors) {
      if (!supervisor.email) {
        continue;
      }

      queueMail(
        officeProgressMail({
          to: supervisor.email,
          supervisorName: supervisor.name,
          staffName: actor.name,
          trackingNumber: document.tracking_number,
          documentType: document.document_type,
          step,
        }),
      );
    }
  }

  return supervisors.map((supervisor) => supervisor.id);
}
