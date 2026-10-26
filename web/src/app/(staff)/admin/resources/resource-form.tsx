'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { KIND_EQUIPMENT, KIND_FACILITY } from '@/lib/domain/resource-kinds';
import { routes } from '@/lib/routes';
import { useConfirm } from '@/components/confirm-dialog';
import { saveResource } from '@/app/actions/admin';

/**
 * Add / edit a catalog item.
 *
 * The counts are asked for on create only. After that they are kept on the
 * Resources page, where the checks against what is out on loan live — editing
 * "owned" here would be a second way to set it, with none of those checks.
 *
 * An item's kind is fixed once anything has been booked against it: a facility
 * and a counted pile are measured differently, and flipping one to the other
 * would reinterpret every booking already taken.
 */
export function ResourceForm({
  resource,
}: {
  resource: {
    id: number;
    name: string;
    kind: string;
    description: string | null;
    location: string | null;
    kindLocked: boolean;
  } | null;
}) {
  const router = useRouter();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [name, setName] = useState(resource?.name ?? '');
  const [kind, setKind] = useState(resource?.kind ?? KIND_EQUIPMENT);
  const [location, setLocation] = useState(resource?.location ?? '');
  const [description, setDescription] = useState(resource?.description ?? '');
  const [owned, setOwned] = useState('');
  const [capacity, setCapacity] = useState('');
  const [error, setError] = useState<string | null>(null);

  const isEquipment = kind === KIND_EQUIPMENT;

  const submit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();

    // Asked before the transition, so the button does not read "Saving…" while
    // the question is still open.
    const confirmed = await confirm({
      title: resource === null ? 'Add this item?' : 'Save changes to this item?',
      message:
        resource === null
          ? `“${name.trim() || 'This item'}” is added to the catalog and can be lent or booked.`
          : 'The catalog is updated for everyone right away.',
      confirmLabel: resource === null ? 'Add item' : 'Save changes',
    });

    if (!confirmed) {
      return;
    }

    startTransition(async () => {
      const result = await saveResource(resource?.id ?? null, {
        name,
        kind,
        description: description.trim() === '' ? null : description,
        location: location.trim() === '' ? null : location,
        // Counts are a create-time concern; on edit the page keeps them.
        totalQuantity: resource !== null || !isEquipment || owned === '' ? null : Number(owned),
        capacity: resource !== null || isEquipment || capacity === '' ? null : Number(capacity),
      });

      if (!result.ok) {
        setError(result.message);

        return;
      }

      router.push(routes.inventoryIndex());
    });
  };

  return (
    <section className="panel">
      <div className="ph">
        <h2>{resource === null ? 'New item' : 'Item details'}</h2>
      </div>

      <form onSubmit={submit} className="pb space-y-5 p-6">
        {error ? (
          <p className="callout p-amber" role="alert">
            {error}
          </p>
        ) : null}

        <div>
          <label className="inv-field-label" htmlFor="resourceName">
            Name <span className="text-status-red">*</span>
          </label>
          <input
            id="resourceName"
            type="text"
            className="field w-full"
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={255}
            required
            autoFocus
            placeholder="e.g. Monoblock Chairs, or Covered Court"
          />
        </div>

        <fieldset>
          <legend className="inv-field-label">
            What kind of thing is it? <span className="text-status-red">*</span>
          </legend>

          <div className="mt-2 grid gap-2 sm:grid-cols-2">
            {(
              [
                [
                  KIND_EQUIPMENT,
                  'Equipment',
                  'Counted and lent out — chairs, tables, tents. Several people can borrow from the pile.',
                ],
                [
                  KIND_FACILITY,
                  'Facility',
                  'One venue, reserved by time — a court, a hall, a plaza.',
                ],
              ] as const
            ).map(([value, label, hint]) => (
              <label key={value} className="dm-panel flex cursor-pointer items-start gap-2 py-3">
                <input
                  type="radio"
                  name="kind"
                  value={value}
                  className="mt-0.5"
                  checked={kind === value}
                  disabled={resource?.kindLocked ?? false}
                  onChange={() => setKind(value)}
                />
                <span>
                  <span className="block text-[length:var(--fs-small)] font-semibold text-ink">
                    {label}
                  </span>
                  <span className="block text-[length:var(--fs-caption)] text-ink-soft">{hint}</span>
                </span>
              </label>
            ))}
          </div>

          {resource?.kindLocked ? (
            <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
              This item already has bookings, so its kind is fixed.
            </p>
          ) : null}
        </fieldset>

        <div>
          <label className="inv-field-label" htmlFor="resourceLocation">
            {isEquipment ? 'Where it is kept' : 'Where it is'}
          </label>
          <input
            id="resourceLocation"
            type="text"
            className="field w-full"
            value={location}
            onChange={(event) => setLocation(event.target.value)}
            maxLength={255}
            placeholder="e.g. GSO stockroom"
          />
        </div>

        {resource === null ? (
          isEquipment ? (
            <div>
              <label className="inv-field-label" htmlFor="resourceOwned">
                How many the office owns
              </label>
              <input
                id="resourceOwned"
                type="number"
                className="field max-w-[12rem]"
                min={0}
                step={1}
                inputMode="numeric"
                value={owned}
                onChange={(event) => setOwned(event.target.value)}
                placeholder="e.g. 100"
              />
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                Leave it empty if nobody has counted yet — staff can set it on the Resources page.
                Nothing can be lent until it is set.
              </p>
            </div>
          ) : (
            <div>
              <label className="inv-field-label" htmlFor="resourceCapacity">
                How many people it holds
              </label>
              <input
                id="resourceCapacity"
                type="number"
                className="field max-w-[12rem]"
                min={0}
                step={1}
                inputMode="numeric"
                value={capacity}
                onChange={(event) => setCapacity(event.target.value)}
              />
            </div>
          )
        ) : null}

        <div>
          <label className="inv-field-label" htmlFor="resourceDescription">
            Description
          </label>
          <textarea
            id="resourceDescription"
            className="field w-full"
            rows={2}
            maxLength={500}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            placeholder="What this is / any usage notes."
          />
        </div>

        <p className="text-[length:var(--fs-caption)] text-ink-soft">
          So citizens can ask for it, add a{' '}
          <Link href={routes.adminRequestTypesIndex()} className="cr-link">
            request type
          </Link>{' '}
          pointing at this item — an <b>equipment borrowing</b> type for equipment, a{' '}
          <b>facility booking</b> type for a facility.
        </p>

        <div className="flex items-center justify-end gap-3 border-t border-hairline pt-4">
          <Link href={routes.inventoryIndex()} className="cr-btn">
            Cancel
          </Link>
          <button type="submit" className="cr-btn cr-btn-primary" disabled={pending}>
            {resource === null ? 'Add item' : 'Save changes'}
          </button>
        </div>
      </form>
    </section>
  );
}
