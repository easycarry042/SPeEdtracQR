'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import type { RequirementInput } from '@/lib/domain/admin-catalog';
import {
  REQUEST_KINDS,
  REQUEST_KIND_LABELS,
  RESOURCE_KINDS,
  type RequestKind,
} from '@/lib/domain/request-kinds';
import { routes } from '@/lib/routes';
import { saveRequestType } from '@/app/actions/admin';

/**
 * Add / edit a request type, with its requirements checklist.
 *
 * The kind is the field everything else follows: a facility booking reserves a
 * place for a time, an equipment borrowing lends a counted pile, and both have
 * to name the resource they draw on — point one at the other and every request
 * it files runs the wrong stock rules.
 */
export function RequestTypeForm({
  requestType,
  offices,
  resources,
}: {
  requestType: {
    id: number;
    name: string;
    kind: string;
    description: string | null;
    departmentId: number | null;
    resourceId: number | null;
    requirements: RequirementInput[];
  } | null;
  offices: Array<{ id: number; name: string; code: string }>;
  resources: Array<{ id: number; name: string; kind: string }>;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [name, setName] = useState(requestType?.name ?? '');
  const [kind, setKind] = useState(requestType?.kind ?? 'document');
  const [office, setOffice] = useState(
    requestType?.departmentId === null || requestType === null ? '' : String(requestType.departmentId),
  );
  const [resource, setResource] = useState(
    requestType?.resourceId === null || requestType === null ? '' : String(requestType.resourceId),
  );
  const [description, setDescription] = useState(requestType?.description ?? '');
  const [requirements, setRequirements] = useState<RequirementInput[]>(
    requestType?.requirements ?? [],
  );
  const [error, setError] = useState<string | null>(null);

  const needsResource = RESOURCE_KINDS.includes(kind);

  const submit = (event: React.FormEvent): void => {
    event.preventDefault();

    startTransition(async () => {
      const result = await saveRequestType(requestType?.id ?? null, {
        name,
        kind,
        departmentId: office === '' ? null : Number(office),
        resourceId: resource === '' ? null : Number(resource),
        description: description.trim() === '' ? null : description,
        requirements,
      });

      if (!result.ok) {
        setError(result.message);

        return;
      }

      router.push(
        routes.adminRequestTypesIndex({ saved: requestType === null ? 'created' : 'updated' }),
      );
    });
  };

  const editRequirement = (index: number, patch: Partial<RequirementInput>): void =>
    setRequirements((current) =>
      current.map((entry, position) => (position === index ? { ...entry, ...patch } : entry)),
    );

  return (
    <section className="panel">
      <div className="ph">
        <h2>{requestType === null ? 'New request type' : 'Request type details'}</h2>
      </div>

      <form onSubmit={submit} className="pb space-y-5 p-6">
        {error ? (
          <p className="callout p-amber" role="alert">
            {error}
          </p>
        ) : null}

        <div>
          <label className="inv-field-label" htmlFor="typeName">
            Request type name <span className="text-status-red">*</span>
          </label>
          <input
            id="typeName"
            type="text"
            className="field w-full"
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={255}
            required
            autoFocus
            placeholder="e.g. Business Permit"
          />
        </div>

        <div>
          <label className="inv-field-label" htmlFor="typeKind">
            Kind <span className="text-status-red">*</span>
          </label>
          <select
            id="typeKind"
            className="field w-full"
            value={kind}
            onChange={(event) => setKind(event.target.value)}
          >
            {REQUEST_KINDS.map((entry) => (
              <option key={entry} value={entry}>
                {REQUEST_KIND_LABELS[entry as RequestKind]}
              </option>
            ))}
          </select>

          {needsResource ? (
            <div className="mt-3">
              <label className="inv-field-label" htmlFor="typeResource">
                {kind === 'booking' ? 'Facility to reserve' : 'Item to borrow'}{' '}
                <span className="text-status-red">*</span>
              </label>
              <select
                id="typeResource"
                className="field w-full"
                value={resource}
                onChange={(event) => setResource(event.target.value)}
              >
                <option value="">Select a resource…</option>
                {resources.map((entry) => (
                  <option key={entry.id} value={entry.id}>
                    {entry.name} ({entry.kind === 'equipment' ? 'equipment' : 'facility'})
                  </option>
                ))}
              </select>
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                A facility booking needs a facility, an equipment borrowing needs equipment. Manage
                them under{' '}
                <Link href={routes.inventoryIndex()} className="cr-link">
                  Resources
                </Link>
                .
              </p>
            </div>
          ) : null}
        </div>

        <div>
          <label className="inv-field-label" htmlFor="typeOffice">
            Handling office
          </label>
          <select
            id="typeOffice"
            className="field w-full"
            value={office}
            onChange={(event) => setOffice(event.target.value)}
          >
            <option value="">Unassigned — a super admin or supervisor routes it by hand</option>
            {offices.map((entry) => (
              <option key={entry.id} value={entry.id}>
                {entry.name} ({entry.code})
              </option>
            ))}
          </select>
          <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
            Requests of this type land in this office’s queue on submission, and its supervisor
            assigns a staff member. With no office they reach nobody until someone routes them.
          </p>
        </div>

        <div>
          <label className="inv-field-label" htmlFor="typeDescription">
            Description
          </label>
          <textarea
            id="typeDescription"
            className="field w-full"
            rows={2}
            maxLength={500}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            placeholder="Shown to help staff and citizens understand this request."
          />
        </div>

        {/* ── Requirements checklist ───────────────────────────────────────── */}
        <div>
          <p className="inv-field-label">Requirements checklist</p>
          <p className="text-[length:var(--fs-caption)] text-ink-soft">
            Supporting documents the citizen must present (e.g. Cedula, Barangay Clearance). Staff
            verify each before approval. Leave empty for types that need none.
          </p>

          <div className="mt-3 space-y-2">
            {requirements.length === 0 ? (
              <p className="text-[length:var(--fs-caption)] text-ink-soft">
                No requirements — citizens just submit the request.
              </p>
            ) : (
              requirements.map((requirement, index) => (
                <div key={index} className="dm-panel flex flex-wrap items-center gap-2 py-3">
                  <div className="min-w-[220px] flex-1">
                    <label className="sr-only" htmlFor={`requirement-${index}`}>
                      Requirement {index + 1}
                    </label>
                    <input
                      id={`requirement-${index}`}
                      type="text"
                      className="field w-full"
                      maxLength={255}
                      value={requirement.label}
                      onChange={(event) => editRequirement(index, { label: event.target.value })}
                      placeholder="e.g. Barangay Business Clearance"
                    />
                  </div>

                  <label className="flex items-center gap-1.5 text-[length:var(--fs-caption)] font-medium text-ink">
                    <input
                      type="checkbox"
                      checked={requirement.isMandatory}
                      onChange={(event) =>
                        editRequirement(index, { isMandatory: event.target.checked })
                      }
                    />
                    Required
                  </label>

                  <button
                    type="button"
                    className="cr-btn cr-btn-sm"
                    onClick={() =>
                      setRequirements((current) =>
                        current.filter((_, position) => position !== index),
                      )
                    }
                  >
                    Remove
                  </button>
                </div>
              ))
            )}
          </div>

          <button
            type="button"
            className="cr-btn mt-3"
            onClick={() =>
              setRequirements((current) => [...current, { label: '', isMandatory: true }])
            }
          >
            Add requirement
          </button>
        </div>

        <div className="flex items-center justify-end gap-3 border-t border-hairline pt-4">
          <Link href={routes.adminRequestTypesIndex()} className="cr-btn">
            Cancel
          </Link>
          <button type="submit" className="cr-btn cr-btn-primary" disabled={pending}>
            {requestType === null ? 'Create request type' : 'Save changes'}
          </button>
        </div>
      </form>
    </section>
  );
}
