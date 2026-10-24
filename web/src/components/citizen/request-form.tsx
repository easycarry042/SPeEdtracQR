'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { submitRequest, type SubmitResult } from '@/app/actions/request';
import * as uploadRules from '@/lib/domain/upload-rules';

/**
 * The public request form.
 *
 * One form for four kinds of request. Which panel shows — requirements,
 * reservation window, borrowing quantity, service due date — follows the chosen
 * request type, exactly as the Blade version's JS cascade did. The type catalog
 * is passed in from the server so the branch needs no round trip.
 *
 * The Blade form degraded without JavaScript (a grouped <select> carried it).
 * React cannot, so the fallback is gone; the panels are driven by state instead
 * of `hidden` toggling.
 */
export type RequirementOption = { id: number; label: string; mandatory: boolean };

export type TypeOption = {
  name: string;
  kind: string;
  resource: string | null;
  /** The type's one extra question (Venue, Size…), or null for none. */
  detailsLabel: string | null;
  requirements: RequirementOption[];
};

/** Group headings, in display order — the Blade `$groups` map. */
const GROUPS: Array<[string, string]> = [
  ['document', 'Documents & Permits'],
  ['booking', 'Facility reservations'],
  ['equipment', 'Equipment borrowing'],
  ['service', 'Services'],
];

const ERROR_TEXT = 'mt-1.5 text-[13px] font-semibold text-red-700';
const HINT_TEXT = 'mt-1.5 text-[13px] text-[#4b5a50]';

function FieldError({ message }: { message?: string }) {
  return message ? <p className={ERROR_TEXT}>{message}</p> : null;
}

export function RequestForm({
  types,
  popularTypes,
  today,
}: {
  types: TypeOption[];
  popularTypes: string[];
  /** `Y-m-d` in Manila, so `min` on the date inputs matches the server's "today". */
  today: string;
}) {
  const [selected, setSelected] = useState('');
  const [result, setResult] = useState<SubmitResult | null>(null);
  const [pending, setPending] = useState(false);

  const byName = useMemo(() => new Map(types.map((type) => [type.name, type])), [types]);
  const grouped = useMemo(
    () => GROUPS.filter(([kind]) => types.some((type) => type.kind === kind)),
    [types],
  );

  const type = selected === '' ? null : (byName.get(selected) ?? null);
  const errors = result?.errors ?? {};
  const values = result?.values ?? {};

  async function onSubmit(form: FormData) {
    setPending(true);

    try {
      // A refusal comes back as a result; success redirects, which throws past
      // this point by design.
      const outcome = await submitRequest(form);
      setResult(outcome);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setPending(false);
    }
  }

  const messages = Object.values(errors);

  return (
    <>
      {messages.length > 0 ? (
        <div
          className="mt-8 rounded-[16px] border border-red-200 bg-red-50 px-5 py-4 text-[15px] text-red-800"
          role="alert"
        >
          <p className="font-semibold">
            Please fix the highlighted {messages.length === 1 ? 'field' : 'fields'}:
          </p>
          <ul className="mt-1 list-inside list-disc space-y-1">
            {messages.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        </div>
      ) : null}

      <form action={onSubmit} className="req-shell mt-8 p-5 sm:p-8 lg:p-10" noValidate>
        {/* Honeypot: must stay empty. Hidden from real users, and kept outside
            the columns so it does not take the first slot of the stack. */}
        <div style={{ position: 'absolute', left: '-7999px' }} aria-hidden="true">
          <label>
            Website
            <input type="text" name="website" tabIndex={-1} autoComplete="off" />
          </label>
        </div>

        {/* Two columns, as drawn: what you are asking for on the left, who is
            asking on the right. They stack on narrow screens. */}
        <div className="grid grid-cols-1 gap-x-[59px] gap-y-[24px] lg:grid-cols-2">
          <div className="space-y-[24px]">
            <div>
              <label htmlFor="document_type" className="req-label">
                Request type <span className="req-star">*</span>
              </label>
              <select
                id="document_type"
                name="document_type"
                required
                value={selected}
                onChange={(event) => setSelected(event.target.value)}
                aria-invalid={Boolean(errors.document_type)}
                className="req-field req-select mt-2"
              >
                <option value="">Select a request type…</option>
                {grouped.map(([kind, label]) => (
                  <optgroup key={kind} label={label}>
                    {types
                      .filter((option) => option.kind === kind)
                      .map((option) => (
                        <option key={option.name} value={option.name}>
                          {option.name}
                        </option>
                      ))}
                  </optgroup>
                ))}
              </select>
              <FieldError message={errors.document_type} />
            </div>

            {/* Shortcuts to the handful of types citizens file most, so the
                common errands never need the dropdown. */}
            {popularTypes.length > 0 ? (
              <div>
                <p className="req-label">Most requested</p>
                <div className="mt-3 flex flex-wrap gap-2.5">
                  {popularTypes.map((name) => (
                    <button
                      key={name}
                      type="button"
                      className="req-chip"
                      onClick={() => setSelected(name)}
                    >
                      <svg
                        className="h-4 w-4 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.5"
                        aria-hidden="true"
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M7 17L17 7M9 7h8v8" />
                      </svg>
                      {name}
                    </button>
                  ))}
                </div>
              </div>
            ) : null}

            {/* Requirements for the chosen type. Citizens bring the originals to
                the counter; attaching a copy is optional. */}
            {type && type.requirements.length > 0 ? (
              <div className="req-note p-4 sm:p-5">
                <p className="req-note-title text-[16px]">Requirements for this request</p>
                <p className="req-note-body mt-1 text-[14px] leading-relaxed sm:text-[15px]">
                  Please bring the <strong>original</strong> of each to the counter — staff will
                  verify them. Attaching a copy below is optional.
                </p>
                <ul className="mt-3 space-y-3">
                  {type.requirements.map((requirement) => (
                    <li key={requirement.id}>
                      <label className="req-label text-sm">
                        {requirement.label}
                        {requirement.mandatory ? <span className="req-star"> *</span> : null}
                      </label>
                      <input
                        type="file"
                        name={`requirements[${requirement.id}]`}
                        accept={uploadRules.accept()}
                        className="req-field mt-2"
                      />
                      <FieldError message={errors[`requirements.${requirement.id}`]} />
                    </li>
                  ))}
                </ul>
                <p className={HINT_TEXT}>{uploadRules.hint()}</p>
              </div>
            ) : null}

            {/* Facility reservations: a place for a specific window on one day. */}
            {type?.kind === 'booking' ? (
              <div className="req-note p-4 sm:p-5">
                <p className="req-note-title text-[16px]">Reservation details</p>
                <p className="req-note-body mt-1 text-[14px] leading-relaxed sm:text-[15px]">
                  You&rsquo;re reserving <strong>{type.resource}</strong>. Pick the date and the time
                  you need it — staff confirm availability, and clashing times are refused.
                </p>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div className="sm:col-span-2">
                    <label htmlFor="booking_date" className="req-label text-sm">Date</label>
                    <input
                      id="booking_date"
                      type="date"
                      name="booking_date"
                      min={today}
                      defaultValue={values.booking_date ?? ''}
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.booking_date} />
                  </div>
                  <div>
                    <label htmlFor="start_time" className="req-label text-sm">Start time</label>
                    <input
                      id="start_time"
                      type="time"
                      name="start_time"
                      defaultValue={values.start_time ?? '09:00'}
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.start_time} />
                  </div>
                  <div>
                    <label htmlFor="end_time" className="req-label text-sm">End time</label>
                    <input
                      id="end_time"
                      type="time"
                      name="end_time"
                      defaultValue={values.end_time ?? '10:00'}
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.end_time} />
                  </div>
                </div>
              </div>
            ) : null}

            {/* Equipment borrowing: how many units, and the borrow-to-return dates. */}
            {type?.kind === 'equipment' ? (
              <div className="req-note p-4 sm:p-5">
                <p className="req-note-title text-[16px]">Borrowing details</p>
                <p className="req-note-body mt-1 text-[14px] leading-relaxed sm:text-[15px]">
                  You&rsquo;re borrowing <strong>{type.resource}</strong>. Tell us how many and when —
                  staff confirm availability.
                </p>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                  <div>
                    <label htmlFor="quantity" className="req-label text-sm">How many</label>
                    <input
                      id="quantity"
                      type="number"
                      name="quantity"
                      min={1}
                      step={1}
                      inputMode="numeric"
                      defaultValue={values.quantity ?? ''}
                      placeholder="e.g. 50"
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.quantity} />
                  </div>
                  <div>
                    <label htmlFor="needed_date" className="req-label text-sm">Date needed</label>
                    <input
                      id="needed_date"
                      type="date"
                      name="needed_date"
                      min={today}
                      defaultValue={values.needed_date ?? ''}
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.needed_date} />
                  </div>
                  <div>
                    <label htmlFor="return_date" className="req-label text-sm">Return by</label>
                    <input
                      id="return_date"
                      type="date"
                      name="return_date"
                      min={today}
                      defaultValue={values.return_date ?? ''}
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.return_date} />
                  </div>
                </div>
              </div>
            ) : null}

            {/* Service / production requests (e.g. lei making): how many to make,
                and the date they're needed. No resource is reserved. */}
            {type?.kind === 'service' ? (
              <div className="req-note p-4 sm:p-5">
                <p className="req-note-title text-[16px]">Service details</p>
                <p className="req-note-body mt-1 text-[14px] leading-relaxed sm:text-[15px]">
                  Tell us by when you need it, and how many if that applies.
                </p>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label htmlFor="service_quantity" className="req-label text-sm">
                      How many <span className="font-normal text-[#4b5a50]">(optional)</span>
                    </label>
                    <input
                      id="service_quantity"
                      type="number"
                      name="quantity"
                      min={1}
                      step={1}
                      inputMode="numeric"
                      defaultValue={values.quantity ?? ''}
                      placeholder="e.g. 10"
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.quantity} />
                  </div>
                  <div>
                    <label htmlFor="needed_by" className="req-label text-sm">Date needed</label>
                    <input
                      id="needed_by"
                      type="date"
                      name="needed_by"
                      min={today}
                      defaultValue={values.needed_by ?? ''}
                      className="req-field mt-2"
                    />
                    <FieldError message={errors.needed_by} />
                  </div>
                </div>
              </div>
            ) : null}

            {/* The type's one extra question — the paper form's Venue, Size or
                "Others: ___" line. Required when the type asks it. */}
            {type?.detailsLabel ? (
              <div>
                <label htmlFor="details" className="req-label">
                  {type.detailsLabel} <span className="req-star">*</span>
                </label>
                <input
                  id="details"
                  name="details"
                  key={type.name}
                  defaultValue={values.details ?? ''}
                  required
                  maxLength={1000}
                  aria-invalid={Boolean(errors.details)}
                  className="req-field mt-2"
                />
                <FieldError message={errors.details} />
              </div>
            ) : null}

            <div>
              <label htmlFor="purpose" className="req-label">Specifications / purpose</label>
              <input
                id="purpose"
                name="purpose"
                defaultValue={values.purpose ?? ''}
                maxLength={255}
                placeholder="What is this request for?"
                className="req-field mt-2"
              />
              <p className={HINT_TEXT}>
                What it is for — e.g. &ldquo;Flag ceremony on Monday&rdquo; or &ldquo;Layout for the
                Pista ng San Pedro tarpaulin.&rdquo;
              </p>
              <FieldError message={errors.purpose} />
            </div>

            <div>
              <label htmlFor="description" className="req-label">Description</label>
              <textarea
                id="description"
                name="description"
                rows={3}
                maxLength={5000}
                defaultValue={values.description ?? ''}
                placeholder="Anything else the office should know…"
                className="req-field mt-2"
              />
              <FieldError message={errors.description} />
            </div>
          </div>

          <div className="space-y-[24px]">
            <div>
              <label htmlFor="citizen_name" className="req-label">
                Name <span className="req-star">*</span>
              </label>
              <input
                id="citizen_name"
                name="citizen_name"
                defaultValue={values.citizen_name ?? ''}
                required
                autoComplete="name"
                maxLength={255}
                placeholder="e.g. Juan Cruz"
                aria-invalid={Boolean(errors.citizen_name)}
                className="req-field mt-2"
              />
              <FieldError message={errors.citizen_name} />
            </div>

            <div>
              <label htmlFor="requester_office" className="req-label">
                Office or organization{' '}
                <span className="font-normal text-[#4b5a50]">(optional)</span>
              </label>
              <input
                id="requester_office"
                name="requester_office"
                defaultValue={values.requester_office ?? ''}
                autoComplete="organization"
                maxLength={255}
                placeholder="e.g. City Health Office, or Barangay Landayan"
                aria-invalid={Boolean(errors.requester_office)}
                className="req-field mt-2"
              />
              <p className={HINT_TEXT}>Filing for a city office, barangay or group? Name it here.</p>
              <FieldError message={errors.requester_office} />
            </div>

            <div>
              <label htmlFor="citizen_email" className="req-label">
                Email <span className="req-star">*</span>
              </label>
              <input
                id="citizen_email"
                type="email"
                name="citizen_email"
                defaultValue={values.citizen_email ?? ''}
                required
                autoComplete="email"
                inputMode="email"
                maxLength={255}
                placeholder="Your email..."
                aria-invalid={Boolean(errors.citizen_email)}
                className="req-field mt-2"
              />
              <p className={HINT_TEXT}>We&rsquo;ll send your tracking link here.</p>
              <FieldError message={errors.citizen_email} />
            </div>

            <div>
              <label htmlFor="citizen_contact" className="req-label">Contact number</label>
              {/* The number is exactly 11 digits (09XXXXXXXXX), so the field
                  ENFORCES that rather than only describing it: maxLength refuses
                  a 12th digit (Norman: physical constraints beat instructions). */}
              <input
                id="citizen_contact"
                type="tel"
                name="citizen_contact"
                defaultValue={values.citizen_contact ?? ''}
                autoComplete="tel"
                inputMode="numeric"
                pattern="09[0-9]{9}"
                maxLength={11}
                placeholder="09123456789"
                aria-invalid={Boolean(errors.citizen_contact)}
                className="req-field mt-2"
              />
              <p className={HINT_TEXT}>11 digits, starting with 09 — e.g. 09123456789.</p>
              <FieldError message={errors.citizen_contact} />
            </div>

            {/* Privacy notice — RA 10173 consent, which the server requires. */}
            <div className="req-note p-5">
              <p className="req-note-title text-[16px] sm:text-[17px]">Your privacy</p>
              <label className="mt-3 flex items-start gap-3.5 text-left">
                <input type="checkbox" name="consent" value="1" className="req-check mt-0.5" />
                <span className="req-note-body text-[15px] leading-relaxed">
                  I agree that the information I provided will be collected and processed by the
                  municipality solely to handle this request, in accordance with the Data Privacy Act
                  of 2012 (RA 10173). Only the details needed to process and contact me about this
                  request are collected.{' '}
                  <Link
                    href="/privacy"
                    target="_blank"
                    rel="noopener"
                    className="font-bold underline underline-offset-2"
                  >
                    Read the full privacy notice
                  </Link>{' '}
                  (opens in a new tab).
                </span>
              </label>
              <FieldError message={errors.consent} />
            </div>

            <div className="pt-1">
              <button type="submit" disabled={pending} className="cz-btn cz-btn-primary w-full">
                {pending ? 'Submitting…' : 'Submit request'}
              </button>
            </div>
          </div>
        </div>
      </form>
    </>
  );
}
