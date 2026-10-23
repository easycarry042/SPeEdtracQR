'use server';

import { redirect } from 'next/navigation';
import { db, id, toId } from '@/lib/db';
import { DocumentStatus } from '@/lib/domain/document-status';
import { ORIGIN_EXTERNAL } from '@/lib/domain/scope';
import * as contactNumber from '@/lib/domain/contact-number';
import * as uploadRules from '@/lib/domain/upload-rules';
import * as qr from '@/lib/domain/qr-service';
import * as receipt from '@/lib/domain/citizen-receipt';
import * as lending from '@/lib/domain/lending';
import * as submission from '@/lib/domain/request-submission';
import { departmentTriagers } from '@/lib/domain/triagers';
import { notifyMany } from '@/lib/domain/notifications';
import { logOnDocument } from '@/lib/activity';
import { queueMail } from '@/lib/mail/send';
import { ticketSubmittedMail } from '@/lib/mail/ticket-submitted';
import { citizenTrackingUrl } from '@/lib/domain/document';
import { now, parseNaive } from '@/lib/time';

/**
 * Citizen self-service ticket creation (no account).
 *
 * Port of `PublicTicketController@store`. All input is treated as untrusted:
 * honeypot, validated and size/type-restricted uploads stored on the PRIVATE
 * disk, RA 10173 consent.
 *
 * Laravel's route throttle (`throttle:8,1`) has no direct equivalent here; the
 * honeypot stays, and rate limiting belongs at the edge (see the deploy notes)
 * rather than being faked in-process where a restart resets it.
 */
export type SubmitResult = {
  ok: false;
  /** Field name → message, mirroring Laravel's `withErrors` bag. */
  errors: Record<string, string>;
  /** Echoed back so the form can repopulate, as `withInput` did. */
  values: Record<string, string>;
};

const text = (form: FormData, key: string): string => String(form.get(key) ?? '').trim();

function fail(errors: Record<string, string>, form: FormData): SubmitResult {
  const values: Record<string, string> = {};

  for (const key of [
    'document_type', 'purpose', 'description', 'citizen_name', 'citizen_email',
    'citizen_contact', 'booking_date', 'start_time', 'end_time', 'quantity',
    'needed_date', 'return_date', 'needed_by',
  ]) {
    const value = text(form, key);
    if (value !== '') {
      values[key] = value;
    }
  }

  return { ok: false, errors, values };
}

export async function submitRequest(form: FormData): Promise<SubmitResult> {
  // Honeypot: bots fill hidden fields. Silently bounce without creating.
  if (text(form, 'website') !== '') {
    redirect('/request?status=received');
  }

  const documentType = text(form, 'document_type');
  const citizenName = text(form, 'citizen_name');
  const citizenEmail = text(form, 'citizen_email');
  // Store the contact number in one canonical shape (09XXXXXXXXX) whatever
  // separators or +63 prefix were typed, so follow-up calls and the contact
  // check on the citizen thread always compare like with like.
  const citizenContact = contactNumber.normalise(text(form, 'citizen_contact'));
  const purpose = text(form, 'purpose');
  const description = text(form, 'description');

  const input: submission.SubmissionInput = {
    document_type: documentType,
    citizen_name: citizenName,
    citizen_email: citizenEmail,
    citizen_contact: citizenContact,
    consent: form.get('consent') !== null,
    booking_date: text(form, 'booking_date'),
    start_time: text(form, 'start_time'),
    end_time: text(form, 'end_time'),
    quantity: text(form, 'quantity'),
    needed_date: text(form, 'needed_date'),
    return_date: text(form, 'return_date'),
    needed_by: text(form, 'needed_by'),
  };

  const identityErrors = submission.validateIdentity(input);

  if (Object.keys(identityErrors).length > 0) {
    return fail(identityErrors, form);
  }

  // Resolve the selected type up front so reservation requests can be
  // schedule-validated (and facilities conflict-checked) BEFORE anything is
  // created.
  const type = await db.request_types.findFirst({
    where: { name: documentType },
    include: { request_type_requirements: { orderBy: { sort_order: 'asc' } }, resource: true },
  });

  if (type?.resource && !type.resource.is_active) {
    return fail({ document_type: `${type.resource.name} is not available to request right now.` }, form);
  }

  const resolved = submission.resolveSchedule(type?.kind ?? '', input, type?.resource ?? null, now());

  if ('errors' in resolved) {
    return fail(resolved.errors, form);
  }

  const { quantity, neededBy, reservation } = resolved.schedule;

  // A facility cannot be double-booked, and only the database knows what else
  // is on it — so this check lives here rather than in the pure resolver.
  if (reservation && type?.kind === submission.KIND_BOOKING && type.resource) {
    const clashes = await lending.conflicts(
      toId(type.resource.id),
      reservation.starts_at,
      reservation.ends_at,
    );

    if (clashes.length > 0) {
      return fail(
        {
          booking_date: `${type.resource.name} is already booked during that time. Please choose another slot.`,
        },
        form,
      );
    }
  }

  // Validate every upload before creating anything, so a rejected file never
  // leaves a half-filed request behind.
  const uploads = new Map<number, File>();

  for (const requirement of type?.request_type_requirements ?? []) {
    const entry = form.get(`requirements[${toId(requirement.id)}]`);

    if (entry instanceof File && entry.size > 0) {
      const problem = uploadRules.check(entry);

      if (problem) {
        return fail({ [`requirements.${toId(requirement.id)}`]: problem }, form);
      }

      uploads.set(toId(requirement.id), entry);
    }
  }

  const trackingNumber = await qr.generateTrackingNumber();
  const timestamp = now();

  const document = await db.documents.create({
    data: {
      tracking_number: trackingNumber,
      document_type: documentType,
      // Auto-route to the request type's handling department (its Supervisor
      // triages and assigns a staff member). Null if the type has no dept.
      department_id: type?.department_id ?? null,
      citizen_name: citizenName,
      citizen_email: citizenEmail,
      citizen_contact: citizenContact,
      purpose: purpose === '' ? null : purpose,
      description: description === '' ? null : description,
      quantity,
      needed_by: neededBy === null ? null : parseNaive(neededBy),
      status: DocumentStatus.Pending,
      status_changed_at: timestamp,
      source: 'online',
      // Routing is automatic, never a choice on the form: anything filed through
      // the public form is an EXTERNAL (citizen) request and enters the citizen
      // flow; dept-to-dept requests are created only through the internal
      // wizard. Set explicitly rather than relying on the column default so the
      // flow is visible here.
      origin: ORIGIN_EXTERNAL,
      created_by: null, // submitted by a citizen, not a staff user
      created_at: timestamp,
      updated_at: timestamp,
    },
  });

  const documentId = toId(document.id);

  const qrResult = await qr.generateAndStore(trackingNumber);
  if (qrResult.success && qrResult.relativePath) {
    await db.documents.update({
      where: { id: document.id },
      data: { qr_code_path: qrResult.relativePath },
    });
  }

  if (reservation && type?.resource_id) {
    // Facility/equipment request: create the pending reservation (staff
    // approve/reschedule on the calendar; approval re-checks conflicts).
    await db.bookings.create({
      data: {
        document_id: document.id,
        resource_id: type.resource_id,
        starts_at: reservation.starts_at,
        ends_at: reservation.ends_at,
        status: 'pending',
        created_at: timestamp,
        updated_at: timestamp,
      },
    });
  }

  if (type) {
    // Snapshot the requirement checklist for EVERY kind — a facility or
    // equipment request can require a Letter of Request just like a document
    // request lists its supporting papers. Stores any optional citizen upload
    // per requirement (private disk).
    const { storePrivate } = await import('@/lib/storage');

    for (const requirement of type.request_type_requirements) {
      const file = uploads.get(toId(requirement.id));
      const path = file ? await storePrivate(file, 'document-requirements') : null;

      await db.document_requirements.create({
        data: {
          document_id: document.id,
          request_type_requirement_id: requirement.id,
          label: requirement.label,
          is_mandatory: requirement.is_mandatory,
          uploaded_file_path: path,
          created_at: timestamp,
          updated_at: timestamp,
        },
      });
    }
  }

  await logOnDocument(documentId, 'Citizen submitted online request');

  // Header-bell ping: the handling department's Supervisors have a new request
  // to triage and assign. Falls back per `departmentTriagers`.
  const triagers = await departmentTriagers(
    type?.department_id === null || type?.department_id === undefined ? null : toId(type.department_id),
  );

  await notifyMany(
    triagers.map((triager) => triager.id),
    {
      event: 'new_ticket',
      title: 'New request to review',
      body: [documentType || 'Document', citizenName || 'no name'].join(' — '),
      url: citizenTrackingUrl(trackingNumber),
      tracking_number: trackingNumber,
    },
  );

  queueMail(
    ticketSubmittedMail({
      to: citizenEmail,
      trackingNumber,
      citizenName,
      documentType,
      qrPng: qrResult.png,
    }),
  );

  // Post/Redirect/Get. Rendering the receipt straight from this POST left the
  // browser on a submitted form, so a refresh — or Back then Forward — re-posted
  // it and filed a second identical request. Redirecting means a refresh just
  // re-reads the receipt.
  //
  // The tracking number goes through a signed cookie rather than the URL: a
  // guessable /request/submitted/{tracking} would let anyone open someone else's
  // receipt, QR and all.
  await receipt.remember(trackingNumber);

  redirect('/request/submitted');
}
