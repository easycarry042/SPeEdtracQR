'use client';

/**
 * "What to do next": the steps for this person on this request, numbered,
 * with the one to do now highlighted. A step is done when its `done` is true;
 * the first step not done is the current one. `waiting` marks a step that is
 * someone else's to do (shown as current, but not as "do this now").
 *
 * The part of the card each step refers to is highlighted with the same green
 * through `stepFocus(isCurrent)` (class `ns-focus`), so the eye goes from the
 * list straight to the control.
 */
export type NextStep = {
  label: React.ReactNode;
  hint?: React.ReactNode;
  done: boolean;
  waiting?: boolean;
};

export function currentStepIndex(steps: NextStep[]): number {
  const index = steps.findIndex((step) => !step.done);

  return index === -1 ? steps.length : index;
}

/** Class for the control area of a step: highlighted while it is the current one. */
export function stepFocus(isCurrent: boolean): string {
  return isCurrent ? 'ns-focus' : '';
}

export function NextSteps({ steps, title = 'What to do next' }: { steps: NextStep[]; title?: string }) {
  const current = currentStepIndex(steps);

  return (
    <nav className="ns" aria-label={title}>
      <p className="ns-title">{title}</p>
      <ol className="ns-list">
        {steps.map((step, index) => {
          // Read top to bottom: nothing after the current step shows as done,
          // even if its own condition is already met (a pre-selected choice).
          const state = index < current ? 'done' : index === current ? 'current' : 'later';

          return (
            <li key={index} className={`ns-step is-${state}`} aria-current={state === 'current' ? 'step' : undefined}>
              <span className="ns-dot" aria-hidden="true">
                {state === 'done' ? (
                  <svg width="12" height="12" fill="none" stroke="currentColor" strokeWidth="3" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="m5 12 5 5 9-10" />
                  </svg>
                ) : (
                  index + 1
                )}
              </span>
              <span className="min-w-0">
                <span className="ns-label">
                  {step.label}
                  {state === 'current' ? (
                    <span className="ns-now">{step.waiting ? 'Waiting' : 'Do this now'}</span>
                  ) : null}
                  {state === 'done' ? <span className="sr-only"> (done)</span> : null}
                </span>
                {state === 'current' && step.hint ? <span className="ns-hint">{step.hint}</span> : null}
              </span>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
