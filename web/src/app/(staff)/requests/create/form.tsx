'use client';

import { useEffect, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { FormDepartment, FormRecipient } from '@/lib/domain/internal-request-form';
import { fileRequest } from '@/app/actions/internal';

/**
 * Filing an internal request.
 *
 * The signed paper IS the request, so the scan is required: filing without it
 * leaves the receiving office nothing to act on. The one branch that changes
 * the form is whether there is a hard copy — without paper there is no folder
 * to scan in, so the filer names who receives it at the first office.
 */
export function FilingForm({
  departments,
  officeName,
}: {
  departments: FormDepartment[];
  officeName: string;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);

  const [firstDepartment, setFirstDepartment] = useState('');
  const [purpose, setPurpose] = useState('');
  const [hasHardCopy, setHasHardCopy] = useState(true);
  const [recipientId, setRecipientId] = useState('');
  const [people, setPeople] = useState<FormRecipient[]>([]);
  const [scan, setScan] = useState<File | null>(null);

  // Fetched per office rather than shipped with the form: this also opens in a
  // modal from the header on every staff page.
  useEffect(() => {
    if (hasHardCopy || firstDepartment === '') {
      setPeople([]);

      return;
    }

    let live = true;

    void fetch(`/requests/recipients/${firstDepartment}`)
      .then((response) => (response.ok ? response.json() : []))
      .then((rows: FormRecipient[]) => {
        if (live) {
          setPeople(rows);
        }
      })
      .catch(() => setPeople([]));

    return () => {
      live = false;
    };
  }, [firstDepartment, hasHardCopy]);

  const submit = (): void => {
    setError(null);

    const form = new FormData();
    form.set('first_department_id', firstDepartment);
    form.set('purpose', purpose.trim());
    form.set('has_hard_copy', hasHardCopy ? '1' : '0');

    if (!hasHardCopy) {
      form.set('recipient_id', recipientId);
    }

    if (scan) {
      form.set('paper_scan', scan);
    }

    startTransition(async () => {
      const result = await fileRequest(form);

      if (result.ok && result.url) {
        router.push(result.url);

        return;
      }

      setError(result.ok ? 'Filed, but the confirmation could not be opened.' : result.message);
    });
  };

  return (
    <section className="dm-card dm-page-card mx-auto w-full max-w-[760px]">
      <div className="dm-head">
        <div className="min-w-0">
          <h2 className="dm-section">File an internal request</h2>
          <p className="text-[length:var(--fs-caption)] text-ink-soft">
            On behalf of {officeName}
          </p>
        </div>
      </div>

      <div className="dm-body space-y-4">
        {error ? (
          <ul className="gate-errors" role="alert">
            <li>{error}</li>
          </ul>
        ) : null}

        <div>
          <label className="gate-claim-label" htmlFor="purpose">
            What is being requested?
          </label>
          <textarea
            id="purpose"
            className="field"
            rows={3}
            maxLength={255}
            value={purpose}
            onChange={(event) => setPurpose(event.target.value)}
            placeholder="e.g. Purchase of 20 monoblock chairs for the barangay hall"
          />
        </div>

        <div>
          <label className="gate-claim-label" htmlFor="first-department">
            Which office handles it first?
          </label>
          <select
            id="first-department"
            className="field"
            value={firstDepartment}
            onChange={(event) => {
              setFirstDepartment(event.target.value);
              setRecipientId('');
            }}
          >
            <option value="">Select a department…</option>
            {departments.map((office) => (
              <option key={office.id} value={office.id}>
                {office.name} ({office.code})
              </option>
            ))}
          </select>
          <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
            Further offices are added by whoever handles it there — the chain is not fixed now.
          </p>
        </div>

        <fieldset className="dm-note">
          <legend className="dm-note-label">Is there a paper copy?</legend>
          <label className="flex items-start gap-2">
            <input
              type="radio"
              name="copy"
              checked={hasHardCopy}
              onChange={() => setHasHardCopy(true)}
            />
            <span>
              <b>Hard copy</b> — a folder will be carried to the office, which scans it in on
              arrival.
            </span>
          </label>
          <label className="mt-2 flex items-start gap-2">
            <input
              type="radio"
              name="copy"
              checked={!hasHardCopy}
              onChange={() => setHasHardCopy(false)}
            />
            <span>
              <b>Soft copy only</b> — no folder, so it goes to one person by name.
            </span>
          </label>
        </fieldset>

        {!hasHardCopy ? (
          <div>
            <label className="gate-claim-label" htmlFor="recipient">
              Who in that office receives it?
            </label>
            <select
              id="recipient"
              className="field"
              value={recipientId}
              disabled={firstDepartment === ''}
              onChange={(event) => setRecipientId(event.target.value)}
            >
              <option value="">
                {firstDepartment ? 'Select a person…' : 'Choose the office first'}
              </option>
              {people.map((person) => (
                <option key={person.id} value={person.id}>
                  {person.name} · {person.role}
                </option>
              ))}
            </select>
          </div>
        ) : null}

        <div>
          <label className="gate-claim-label" htmlFor="paper-scan">
            Scan of the signed paper
          </label>
          <input
            id="paper-scan"
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,.webp"
            className="field"
            onChange={(event) => setScan(event.target.files?.[0] ?? null)}
          />
          <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
            PDF or a picture, so the QR can be stamped onto it. Word files can&apos;t be stamped —
            save or scan them as a PDF first.
          </p>
        </div>

        <button
          type="button"
          className="cr-btn cr-btn-primary w-full justify-center"
          disabled={
            pending ||
            purpose.trim() === '' ||
            firstDepartment === '' ||
            scan === null ||
            (!hasHardCopy && recipientId === '')
          }
          onClick={submit}
        >
          {pending ? 'Filing…' : 'File request'}
        </button>
      </div>
    </section>
  );
}
