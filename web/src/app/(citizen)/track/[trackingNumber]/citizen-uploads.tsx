'use client';

import { useRef, useState, useTransition } from 'react';
import { reuploadRevisedRequirement, uploadSupportingFiles } from '@/app/actions/citizen';

/**
 * The tracking page's upload forms: a corrected copy of a returned
 * requirement, and supporting files for the office. Port of the two forms in
 * `track/show-citizen.blade.php`.
 */
type Notice = { ok: boolean; message: string } | null;

function NoticeLine({ notice }: { notice: Notice }) {
  if (!notice) {
    return null;
  }

  return notice.ok ? (
    <div className="rounded-lg border border-hairline bg-green-wash px-4 py-3 text-sm text-green-deep" role="status">
      {notice.message}
    </div>
  ) : (
    <div className="rounded-lg border border-status-red-wash bg-status-red-wash px-4 py-3 text-sm text-status-red" role="alert">
      {notice.message}
    </div>
  );
}

export function RequirementReupload({
  trackingNumber,
  requirementId,
  label,
  accept,
}: {
  trackingNumber: string;
  requirementId: number;
  label: string;
  accept: string;
}) {
  const [pending, startTransition] = useTransition();
  const [notice, setNotice] = useState<Notice>(null);
  const formRef = useRef<HTMLFormElement>(null);

  return (
    <form
      ref={formRef}
      className="mt-3 space-y-2"
      onSubmit={(event) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

        startTransition(async () => {
          const result = await reuploadRevisedRequirement(trackingNumber, requirementId, data);
          setNotice(result);

          if (result.ok) {
            formRef.current?.reset();
          }
        });
      }}
    >
      <div className="flex flex-wrap items-center gap-2">
        <label htmlFor={`reupload-${requirementId}`} className="sr-only">
          Re-upload {label}
        </label>
        <input
          id={`reupload-${requirementId}`}
          type="file"
          name="file"
          accept={accept}
          required
          className="block text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-green file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-green-deep"
        />
        <button type="submit" disabled={pending} className="cz-btn cz-btn-primary cz-btn-sm">
          {pending ? 'Sending…' : 'Re-upload'}
        </button>
      </div>
      <NoticeLine notice={notice} />
    </form>
  );
}

/**
 * Each chosen file is listed by name with its own Remove button, and the cap
 * is shown as it fills — a bare file input said only "3 files" and a wrong
 * pick meant starting the whole selection again.
 */
export function SupportingUpload({
  trackingNumber,
  accept,
  hint,
  maxFiles,
  maxBytes,
}: {
  trackingNumber: string;
  accept: string;
  hint: string;
  maxFiles: number;
  maxBytes: number;
}) {
  const [pending, startTransition] = useTransition();
  const [files, setFiles] = useState<File[]>([]);
  const [note, setNote] = useState('');
  const [notice, setNotice] = useState<Notice>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const add = (picked: FileList | null): void => {
    if (!picked) {
      return;
    }

    const next = [...files];
    const problems: string[] = [];

    for (const file of Array.from(picked)) {
      if (next.length >= maxFiles) {
        problems.push(`Only ${maxFiles} files can be sent at a time.`);
        break;
      }

      if (file.size > maxBytes) {
        problems.push(`${file.name} is larger than ${Math.round(maxBytes / 1024 / 1024)} MB.`);
        continue;
      }

      if (!next.some((kept) => kept.name === file.name && kept.size === file.size)) {
        next.push(file);
      }
    }

    setFiles(next);
    setNotice(problems.length > 0 ? { ok: false, message: problems.join(' ') } : null);

    if (inputRef.current) {
      inputRef.current.value = '';
    }
  };

  return (
    <form
      className="space-y-4"
      onSubmit={(event) => {
        event.preventDefault();
        const data = new FormData();
        files.forEach((file) => data.append('attachments', file));
        data.append('note', note);

        startTransition(async () => {
          const result = await uploadSupportingFiles(trackingNumber, data);
          setNotice(result);

          if (result.ok) {
            setFiles([]);
            setNote('');
          }
        });
      }}
    >
      <NoticeLine notice={notice} />

      <div>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <label htmlFor="citizen_attachments" className="req-label">
            Files (images, PDF, Word or Excel)
          </label>
          <span className="text-xs font-semibold text-ink-soft">
            {files.length} of {maxFiles} files selected
          </span>
        </div>

        {/* Visually hidden, not display:none — still reachable through its label. */}
        <input
          ref={inputRef}
          type="file"
          id="citizen_attachments"
          accept={accept}
          multiple
          className="sr-only"
          onChange={(event) => add(event.target.files)}
        />

        <button
          type="button"
          className="cz-btn cz-btn-secondary cz-btn-sm mt-2"
          disabled={files.length >= maxFiles}
          onClick={() => inputRef.current?.click()}
        >
          <svg fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 14v4a2 2 0 002 2h12a2 2 0 002-2v-4" />
          </svg>
          <span>{files.length > 0 ? 'Add more files' : 'Choose files'}</span>
        </button>

        <p className="mt-1.5 text-xs text-ink-soft">
          {hint}. Up to {maxFiles} files per upload.
        </p>

        {files.length > 0 ? (
          <ul className="mt-3 space-y-2">
            {files.map((file, index) => (
              <li
                key={`${file.name}-${file.size}`}
                className="flex items-center gap-3 rounded-[10px] border border-hairline px-3 py-2 text-sm"
              >
                <span className="min-w-0 flex-1 truncate">{file.name}</span>
                <span className="shrink-0 text-xs text-ink-soft">{Math.max(1, Math.round(file.size / 1024))} KB</span>
                <button
                  type="button"
                  className="shrink-0 font-semibold text-status-red hover:underline"
                  onClick={() => setFiles(files.filter((_, i) => i !== index))}
                  aria-label={`Remove ${file.name}`}
                >
                  Remove
                </button>
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      <div>
        <label htmlFor="citizen_note" className="req-label">
          Short note (optional)
        </label>
        <textarea
          id="citizen_note"
          rows={2}
          maxLength={1000}
          value={note}
          onChange={(event) => setNote(event.target.value)}
          placeholder="e.g. Missing ID copy attached"
          className="req-field mt-2"
        />
      </div>

      {/* Disabled until there is something to send. */}
      <button type="submit" disabled={pending || files.length === 0} className="cz-btn cz-btn-primary w-full sm:w-auto">
        {pending ? 'Sending…' : 'Send to department'}
      </button>
    </form>
  );
}
