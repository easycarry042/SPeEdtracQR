import {
  DocumentStatus,
  RAIL_STAGES,
  CITIZEN_RAIL_STAGES,
  fromLoose,
  railStage,
} from '@/lib/domain/document-status';

/**
 * The four-stage rail, as the Review modal on the dashboards draws it.
 *
 * That modal computes its own states client-side because its rows arrive as a
 * JSON payload; every server-rendered surface comes through this component
 * instead, so none of them can drift into a different-looking bar or a
 * different stage count. A citizen told "stage 3 of 4" at the counter must find
 * the same bar, drawn the same way, on their tracking link.
 */
export function StageBar({
  status,
  statusBeforeHold = null,
  size = 'md',
  surface = 'staff',
  sideState = true,
  className = '',
}: {
  status: string;
  statusBeforeHold?: string | null;
  /** 'md' for cards and pages · 'sm' for table rows */
  size?: 'md' | 'sm';
  /** which palette re-skins the rail */
  surface?: 'staff' | 'citizen';
  /** name the off-the-line states under the bar */
  sideState?: boolean;
  className?: string;
}) {
  const stage = fromLoose(status);

  // `railStage()` places the side states too: Returned reads as back in review,
  // a hold or a denial sits where the request stopped.
  const current = railStage(stage, statusBeforeHold ? fromLoose(statusBeforeHold) : null);
  const isCompleted = stage === DocumentStatus.Completed;

  // Off-the-line states, named under the bar rather than drawn on it.
  const side =
    stage === DocumentStatus.Returned
      ? 'Returned for revision'
      : stage === DocumentStatus.OnHold
        ? 'On hold'
        : stage === DocumentStatus.Denied
          ? 'Denied'
          : stage === DocumentStatus.Withdrawn
            ? 'Withdrawn'
            : null;

  const classes = [
    'dm-steps',
    size === 'sm' ? 'dm-steps-sm' : '',
    surface === 'citizen' ? 'dm-steps-cz' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  const steps = surface === 'citizen' ? CITIZEN_RAIL_STAGES : RAIL_STAGES;

  return (
    <>
      <ol className={classes} aria-label="Progress">
        {steps.map((railStep, index) => {
          const position = index + 1;

          // A completed request has nothing left in progress, so its final
          // stage reads done rather than current.
          const state =
            position < current || (position === current && isCompleted)
              ? 'done'
              : position === current
                ? 'current'
                : 'todo';

          return (
            <li
              key={railStep.label}
              className={`dm-step is-${state}`}
              aria-current={state === 'current' ? 'step' : undefined}
            >
              {/* The segment is drawn leading into each dot, so the first stage
                  has none. */}
              {index > 0 ? <span className="dm-step-line" aria-hidden="true" /> : null}

              <span className="dm-step-dot" aria-hidden="true">
                {state === 'done' ? (
                  <svg fill="none" stroke="currentColor" strokeWidth={3} viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="m6 12.5 4 4 8-9" />
                  </svg>
                ) : null}
              </span>

              <span className="dm-step-label">{railStep.label}</span>
            </li>
          );
        })}
      </ol>

      {sideState && side ? <p className="dm-side-state">{side}</p> : null}
    </>
  );
}
