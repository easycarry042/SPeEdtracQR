import { formatNaive, forHumans, now } from '@/lib/time';
import type { Prediction } from '@/lib/domain/completion-predictor';

/**
 * The predicted-completion card.
 *
 * `citizen` dresses it for the public tracking page: the citizen card surface
 * and a display-font date. The staff desk keeps its registry look, where the
 * monospace `.stamp` date matches the rest of the record.
 */
const CONFIDENCE: Record<string, { label: string; className: string }> = {
  high: { label: 'High confidence', className: 'bg-green-wash text-green' },
  medium: { label: 'Medium confidence', className: 'bg-status-amber-wash text-status-amber' },
  low: { label: 'Early estimate', className: 'bg-status-amber-wash text-status-amber' },
};

const FALLBACK_CONFIDENCE = { label: 'Rough estimate', className: 'bg-green-wash text-ink-soft' };

export function EtaEstimate({
  prediction,
  status,
  citizen = false,
}: {
  prediction: Prediction | null;
  status: string;
  citizen?: boolean;
}) {
  const show = prediction?.available === true && status !== 'completed' && prediction.eta !== null;

  if (!show || !prediction?.eta) {
    return null;
  }

  const eta = prediction.eta;
  const confidence = CONFIDENCE[prediction.confidence] ?? FALLBACK_CONFIDENCE;
  const basis =
    prediction.basedOn > 0
      ? `Learned from ${prediction.basedOn} similar past document${prediction.basedOn === 1 ? '' : 's'}.`
      : 'Estimated from each department’s service targets.';

  // `diffForHumans(null, true)` — the size of the span, with no "ago"/"in".
  const away = forHumans(eta, now()).replace(/^in /, '').replace(/ ago$/, '');

  return (
    <div
      className={
        citizen ? 'overflow-hidden cz-card' : 'overflow-hidden rounded-xl border border-hairline bg-paper'
      }
    >
      <div className="flex items-start gap-4 p-5 sm:p-6">
        <div
          className={
            citizen
              ? 'shrink-0 p-3 rounded-[16px] bg-[#eef8ea] text-[#01721a]'
              : 'shrink-0 p-3 rounded-lg bg-green-wash text-green-deep'
          }
        >
          <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth={1.75} viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L23 12l-6.714 2.143L14 21l-2.286-6.857L5 12l6.714-2.143L14 3z"
            />
          </svg>
        </div>
        <div className="flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p
              className={
                citizen
                  ? 'font-semibold tracking-wide text-[14px] text-[#4b5a50]'
                  : 'font-semibold tracking-wide text-xs text-ink-soft'
              }
            >
              Predicted completion
            </p>
            <span
              className={`rounded-full px-2 py-0.5 font-semibold ${confidence.className} ${
                citizen ? 'text-[12px]' : 'text-[10px]'
              }`}
            >
              {confidence.label}
            </span>
          </div>

          {citizen ? (
            <p className="mt-1 font-display text-[26px] font-black leading-tight text-[#004004] sm:text-[30px]">
              Likely ready by {formatNaive(eta, 'M d, Y')}
            </p>
          ) : (
            <p className="mt-1 text-2xl font-semibold text-ink">
              Likely ready by <span className="stamp">{formatNaive(eta, 'M d, Y')}</span>
            </p>
          )}

          <p className={citizen ? 'mt-1 text-[15px] text-[#4b5a50]' : 'mt-1 text-sm text-ink-soft'}>
            about {away} from now · {basis}
          </p>
        </div>
      </div>
    </div>
  );
}
