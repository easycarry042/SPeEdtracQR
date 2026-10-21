/**
 * The gradient stat tile the staff and supervisor rows lead with.
 *
 * Left-to-right gradients, as the design draws them: the darkest tile carries
 * the oldest work, the lightest the finished work. The supervisor's row runs to
 * four tiles so it uses the whole ramp; the staff row uses the first three.
 */
export type TileTone = 'deep' | 'mid' | 'bright' | 'brightest' | 'amber';
export type TileIcon =
  | 'doc'
  | 'hourglass'
  | 'people'
  | 'clock'
  | 'check'
  | 'stopwatch'
  | 'overdue'
  | 'none';

const TONES: Record<TileTone, string> = {
  deep: 'from-[#0a3a1e] to-[#2f7a3a]',
  mid: 'from-[#0f7233] to-[#2a9d4f]',
  bright: 'from-[#20a04a] to-[#3fc267]',
  brightest: 'from-[#0ab329] to-[#459955]',
  // Risk is the one count that must not read as more green: amber is what the
  // rest of the system uses for "watch this".
  amber: 'from-[#8a5a08] to-[#c08a1e]',
};

/**
 * Hand-drawn line illustrations exported from the design, not stroke icons —
 * the loose freehand line is what keeps these tiles from reading as generic
 * dashboard chrome.
 */
const ARTS: Record<Exclude<TileIcon, 'none'>, { file: string; className: string }> = {
  doc: { file: 'stat-total.svg', className: 'h-[63px] w-[64px]' },
  hourglass: { file: 'stat-pending.svg', className: 'h-[66.5px] w-[56px]' },
  people: { file: 'stat-unassigned.svg', className: 'h-[64px] w-[63px]' },
  clock: { file: 'stat-in-progress.svg', className: 'h-[64px] w-[64px]' },
  check: { file: 'stat-completed.svg', className: 'h-[64px] w-[64px]' },
  stopwatch: { file: 'stat-avg-time.svg', className: 'h-[64px] w-[64px]' },
  overdue: { file: 'stat-overdue.svg', className: 'h-[64px] w-[64px]' },
};

export function StaffStatTile({
  label,
  value,
  tone = 'mid',
  icon = 'clock',
  note = null,
}: {
  label: string;
  value: string | number;
  tone?: TileTone;
  icon?: TileIcon;
  /** Optional line under the number — the admin row's deltas. */
  note?: string | null;
}) {
  // `icon="none"` for a count the design has no drawing for — better an honest
  // blank than one of the three reused on two tiles in the same row.
  const art = icon === 'none' ? null : ARTS[icon];

  return (
    <div
      className={`relative isolate flex min-h-[112px] items-center overflow-hidden rounded-[13px] bg-gradient-to-r ${TONES[tone]} px-[21.5px] py-[21.5px] text-white shadow-md xl:min-h-[139px]`}
    >
      <div className="min-w-0 flex-1">
        <p className="text-[16px] font-extrabold uppercase tracking-wide xl:text-[19px]">{label}</p>
        <p className="mt-[6px] text-[42px] font-extrabold leading-none xl:text-[58px]">{value}</p>

        {note ? (
          /* White at 80% on every tone in the ramp still clears AA. */
          <p className="mt-[7px] text-[12.5px] font-semibold text-white/80">{note}</p>
        ) : null}
      </div>

      {/* Decorative only: the label and number already say everything. */}
      {art ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={`/images/staff/${art.file}`}
          alt=""
          aria-hidden="true"
          className={`pointer-events-none ml-4 shrink-0 opacity-70 ${art.className}`}
        />
      ) : null}
    </div>
  );
}
