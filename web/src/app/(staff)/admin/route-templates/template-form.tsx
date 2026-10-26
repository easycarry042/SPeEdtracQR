'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import {
  STEP_CONDITIONS,
  STEP_CONDITION_LABELS,
  type StepCondition,
} from '@/lib/domain/route-template';
import { routes } from '@/lib/routes';
import { useConfirm } from '@/components/confirm-dialog';
import { saveRouteTemplate } from '@/app/actions/admin';

/**
 * Add / edit an endorsement chain.
 *
 * The branch mechanic is the one piece of expert logic on this form, so it gets
 * a callout rather than a hint: two steps sharing a step number with opposite
 * amount conditions are a fork, and only the one matching the request's peso
 * figure ends up in the chain.
 */
type StepDraft = {
  stepOrder: string;
  departmentId: string;
  action: string;
  condition: string;
};

const blankStep = (stepOrder: number): StepDraft => ({
  stepOrder: String(stepOrder),
  departmentId: '',
  action: '',
  condition: '',
});

export function TemplateForm({
  template,
  offices,
}: {
  template: {
    id: number;
    name: string;
    description: string | null;
    steps: Array<{ stepOrder: number; departmentId: number; action: string; condition: string | null }>;
  } | null;
  offices: Array<{ id: number; name: string; code: string }>;
}) {
  const router = useRouter();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [name, setName] = useState(template?.name ?? '');
  const [description, setDescription] = useState(template?.description ?? '');
  const [steps, setSteps] = useState<StepDraft[]>(
    template && template.steps.length > 0
      ? template.steps.map((step) => ({
          stepOrder: String(step.stepOrder),
          departmentId: String(step.departmentId),
          action: step.action,
          condition: step.condition ?? '',
        }))
      : [blankStep(1)],
  );
  const [error, setError] = useState<string | null>(null);

  const editStep = (index: number, patch: Partial<StepDraft>): void =>
    setSteps((current) =>
      current.map((step, position) => (position === index ? { ...step, ...patch } : step)),
    );

  const submit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();

    // Asked before the transition, so the button does not read "Saving…" while
    // the question is still open.
    const confirmed = await confirm({
      title: template === null ? 'Add this route template?' : 'Save changes to this route?',
      confirmLabel: template === null ? 'Add template' : 'Save changes',
    });

    if (!confirmed) {
      return;
    }

    startTransition(async () => {
      const result = await saveRouteTemplate(template?.id ?? null, {
        name,
        description: description.trim() === '' ? null : description,
        steps: steps.map((step) => ({
          stepOrder: Number(step.stepOrder),
          departmentId: step.departmentId === '' ? 0 : Number(step.departmentId),
          action: step.action,
          condition: step.condition === '' ? null : step.condition,
        })),
      });

      if (!result.ok) {
        setError(result.message);

        return;
      }

      router.push(
        routes.adminRouteTemplatesIndex({ saved: template === null ? 'created' : 'updated' }),
      );
    });
  };

  return (
    <section className="panel">
      <div className="ph">
        <h2>{template === null ? 'New route template' : 'Template details'}</h2>
      </div>

      <form onSubmit={submit} className="pb space-y-5 p-6">
        {error ? (
          <p className="callout p-amber" role="alert">
            {error}
          </p>
        ) : null}

        <div>
          <label className="inv-field-label" htmlFor="templateName">
            Template name <span className="text-status-red">*</span>
          </label>
          <input
            id="templateName"
            type="text"
            className="field w-full"
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={255}
            required
            autoFocus
            placeholder="e.g. Procurement Request"
          />
        </div>

        <div>
          <label className="inv-field-label" htmlFor="templateDescription">
            Description
          </label>
          <textarea
            id="templateDescription"
            className="field w-full"
            rows={2}
            maxLength={500}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            placeholder="When should an office pick this route?"
          />
        </div>

        <div>
          <p className="inv-field-label">
            Endorsement steps <span className="text-status-red">*</span>
          </p>

          <p className="callout">
            To <b>branch</b> a route (e.g. Small Value Procurement vs Public Bidding), give two
            steps the <b>same step number</b> with opposite amount conditions. Only the one matching
            the request’s amount is used.
          </p>

          <div className="mt-3 space-y-2">
            {steps.map((step, index) => (
              <div key={index} className="dm-panel flex flex-wrap items-start gap-2 py-3">
                <div className="w-16">
                  <label className="inv-field-label" htmlFor={`stepOrder-${index}`}>
                    Step
                  </label>
                  <input
                    id={`stepOrder-${index}`}
                    type="number"
                    className="field w-full"
                    min={1}
                    max={50}
                    required
                    value={step.stepOrder}
                    onChange={(event) => editStep(index, { stepOrder: event.target.value })}
                  />
                </div>

                <div className="min-w-[180px] flex-1">
                  <label className="inv-field-label" htmlFor={`stepOffice-${index}`}>
                    Office
                  </label>
                  <select
                    id={`stepOffice-${index}`}
                    className="field w-full"
                    required
                    value={step.departmentId}
                    onChange={(event) => editStep(index, { departmentId: event.target.value })}
                  >
                    <option value="">Select office…</option>
                    {offices.map((office) => (
                      <option key={office.id} value={office.id}>
                        {office.name} ({office.code})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="min-w-[160px] flex-1">
                  <label className="inv-field-label" htmlFor={`stepAction-${index}`}>
                    Action
                  </label>
                  <input
                    id={`stepAction-${index}`}
                    type="text"
                    className="field w-full"
                    maxLength={100}
                    required
                    value={step.action}
                    onChange={(event) => editStep(index, { action: event.target.value })}
                    placeholder="e.g. Approve request"
                  />
                </div>

                <div className="min-w-[200px] flex-1">
                  <label className="inv-field-label" htmlFor={`stepCondition-${index}`}>
                    Condition
                  </label>
                  <select
                    id={`stepCondition-${index}`}
                    className="field w-full"
                    value={step.condition}
                    onChange={(event) => editStep(index, { condition: event.target.value })}
                  >
                    <option value="">Always</option>
                    {STEP_CONDITIONS.map((condition) => (
                      <option key={condition} value={condition}>
                        {STEP_CONDITION_LABELS[condition as StepCondition]}
                      </option>
                    ))}
                  </select>
                </div>

                {steps.length > 1 ? (
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm mt-5"
                    onClick={() =>
                      setSteps((current) => current.filter((_, position) => position !== index))
                    }
                  >
                    Remove
                  </button>
                ) : null}
              </div>
            ))}
          </div>

          <button
            type="button"
            className="cr-btn mt-3"
            onClick={() =>
              setSteps((current) => [
                ...current,
                blankStep(Number(current[current.length - 1]?.stepOrder ?? 0) + 1),
              ])
            }
          >
            Add step
          </button>
        </div>

        <div className="flex items-center justify-end gap-3 border-t border-hairline pt-4">
          <Link href={routes.adminRouteTemplatesIndex()} className="cr-btn">
            Cancel
          </Link>
          <button type="submit" className="cr-btn cr-btn-primary" disabled={pending}>
            {template === null ? 'Create template' : 'Save changes'}
          </button>
        </div>
      </form>
    </section>
  );
}
