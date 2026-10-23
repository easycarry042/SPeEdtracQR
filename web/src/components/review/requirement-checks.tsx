'use client';

import { useEffect, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { reviewRequirement } from '@/app/actions/document';
import type { ReviewRequirement } from '@/lib/domain/requirement-review';

/**
 * The Review modal's requirement list, and the check itself.
 *
 * Each upload is opened, then checked. Opening it here (View, or its
 * thumbnail) goes through the file route with `?open=1`, which the server
 * records; Looks good stays disabled until then, and Process is refused until
 * every required file is checked. A file not uploaded is checked against the
 * original at the counter, so it has nothing to open.
 */
type ReturnMode = 'needs_revision' | 'rejected';

export function RequirementChecks({
  documentId,
  requirements: initial,
  canAdvance,
}: {
  documentId: number;
  requirements: ReviewRequirement[];
  canAdvance: boolean;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [requirements, setRequirements] = useState(initial);
  const [returning, setReturning] = useState<{ id: number; mode: ReturnMode; comment: string } | null>(null);
  const [error, setError] = useState<{ id: number; message: string } | null>(null);

  // A refresh after any action hands down fresh rows.
  useEffect(() => setRequirements(initial), [initial]);

  const patch = (requirementId: number, next: Partial<ReviewRequirement>): void =>
    setRequirements((rows) => rows.map((row) => (row.id === requirementId ? { ...row, ...next } : row)));

  /** The server stamps the open; show it straight away here. */
  const markOpened = (requirement: ReviewRequirement): void => {
    if (canAdvance && !requirement.opened) {
      patch(requirement.id, { opened: true, openedLabel: 'Opened by you just now' });
    }
  };

  const review = (requirement: ReviewRequirement, status: string, comment = ''): void => {
    setError(null);

    startTransition(async () => {
      const result = await reviewRequirement(documentId, requirement.id, {
        reviewStatus: status,
        reviewComment: comment.trim() || null,
      });

      if (!result.ok || !result.requirement) {
        setError({ id: requirement.id, message: result.message });

        return;
      }

      patch(requirement.id, result.requirement);
      setReturning(null);
      router.refresh();
    });
  };

  if (requirements.length === 0) {
    return null;
  }

  const unchecked = requirements.some((requirement) => requirement.isMandatory && !requirement.verified);

  return (
    <section className="mt-7">
      <h3 className="dm-section">Requirements for this request</h3>
      {canAdvance && unchecked ? (
        <p className="dm-file-meta mt-1">
          Open each file, then mark it <strong>Looks good</strong> or ask the citizen to fix it.
        </p>
      ) : null}

      <ul className="dm-files" id="review-requirements">
        {requirements.map((requirement) => {
          const sentBack =
            requirement.reviewStatus === 'needs_revision' || requirement.reviewStatus === 'rejected';
          const isReturning = returning?.id === requirement.id;

          return (
            <li
              key={requirement.id}
              className={`dm-file !flex-wrap${requirement.verified ? ' bg-green-wash/40' : ''}`}
            >
              {requirement.hasFile && requirement.openUrl ? (
                <a
                  href={requirement.openUrl}
                  target="_blank"
                  rel="noopener"
                  className="dm-thumb"
                  onClick={() => markOpened(requirement)}
                >
                  {requirement.isImage && requirement.url ? (
                    // The plain URL: a thumbnail loading is not an open.
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={requirement.url} alt="" />
                  ) : (
                    <span className={`dm-thumb-doc${requirement.ext === 'pdf' ? ' is-pdf' : ''}`}>
                      <DocIcon />
                      <span>{requirement.ext}</span>
                    </span>
                  )}
                </a>
              ) : (
                <span className="dm-thumb is-missing" aria-hidden="true">
                  <WarnIcon />
                </span>
              )}

              <div className="min-w-0 flex-1">
                <p className="dm-file-name">{requirement.label}</p>
                <p className="dm-file-meta">
                  {requirement.isMandatory ? (
                    <span className="font-semibold text-status-red">Required</span>
                  ) : (
                    <span>Optional</span>
                  )}
                  {!requirement.hasFile ? <span> · not uploaded, check the original at the counter</span> : null}
                </p>
                <p className="dm-file-meta mt-0.5">
                  {requirement.verified ? (
                    <span className="font-semibold text-green-deep">✓ {requirement.verifiedLabel ?? 'Checked'}</span>
                  ) : sentBack ? (
                    <span className="font-semibold text-status-amber">
                      {requirement.reviewStatusLabel}
                      {requirement.reviewComment ? `: ${requirement.reviewComment}` : ''}
                    </span>
                  ) : requirement.hasFile && requirement.opened ? (
                    <span>{requirement.openedLabel ?? 'Opened'}</span>
                  ) : requirement.hasFile ? (
                    <span className="font-semibold text-status-amber">Not opened yet</span>
                  ) : null}
                </p>
              </div>

              {requirement.hasFile && requirement.openUrl ? (
                <a
                  href={requirement.openUrl}
                  target="_blank"
                  rel="noopener"
                  className="dm-file-open"
                  onClick={() => markOpened(requirement)}
                >
                  View
                </a>
              ) : null}

              {canAdvance && !requirement.verified ? (
                <div className="w-full pl-[56px]">
                  {!isReturning ? (
                    <div className="flex flex-wrap items-center gap-2">
                      <button
                        type="button"
                        className="cr-btn cr-btn-sm cr-btn-primary disabled:opacity-40"
                        disabled={pending || !requirement.opened}
                        title={requirement.opened ? '' : 'Open the file first'}
                        onClick={() => review(requirement, 'approved')}
                      >
                        <CheckIcon />
                        Looks good
                      </button>
                      <button
                        type="button"
                        className="cr-btn cr-btn-sm"
                        disabled={pending}
                        onClick={() => setReturning({ id: requirement.id, mode: 'needs_revision', comment: '' })}
                      >
                        Needs fixing
                      </button>
                      <button
                        type="button"
                        className="cr-btn cr-btn-sm cr-btn-danger"
                        disabled={pending}
                        onClick={() => setReturning({ id: requirement.id, mode: 'rejected', comment: '' })}
                      >
                        Reject
                      </button>
                      {requirement.hasFile && !requirement.opened ? (
                        <span className="dm-file-meta">Open the file to check it.</span>
                      ) : null}
                    </div>
                  ) : (
                    <div>
                      <label className="dm-file-meta font-semibold" htmlFor={`req-comment-${requirement.id}`}>
                        {returning.mode === 'rejected'
                          ? 'Why is it rejected? The citizen is emailed this.'
                          : 'What should the citizen fix? They are emailed this and can re-upload it.'}
                      </label>
                      <textarea
                        id={`req-comment-${requirement.id}`}
                        value={returning.comment}
                        onChange={(event) => setReturning({ ...returning, comment: event.target.value })}
                        rows={2}
                        maxLength={1000}
                        className="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2 text-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20"
                      />
                      <div className="mt-1 flex justify-end gap-2">
                        <button type="button" className="cr-btn cr-btn-sm" onClick={() => setReturning(null)}>
                          Cancel
                        </button>
                        <button
                          type="button"
                          className="cr-btn cr-btn-sm cr-btn-primary disabled:opacity-40"
                          disabled={pending || !returning.comment.trim()}
                          onClick={() => review(requirement, returning.mode, returning.comment)}
                        >
                          Send to citizen
                        </button>
                      </div>
                    </div>
                  )}
                  {error?.id === requirement.id ? (
                    <p className="mt-1 text-xs font-semibold text-status-red" role="alert">
                      {error.message}
                    </p>
                  ) : null}
                </div>
              ) : null}
            </li>
          );
        })}
      </ul>
    </section>
  );
}

function DocIcon() {
  return (
    <svg fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinejoin="round" d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5Z" />
      <path strokeLinejoin="round" d="M14 3.5V8h4.5" />
    </svg>
  );
}

function WarnIcon() {
  return (
    <svg fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
      />
    </svg>
  );
}

function CheckIcon() {
  return (
    <svg fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="m5 13 4 4L19 7" />
    </svg>
  );
}
