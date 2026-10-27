import type { Metadata } from 'next';
import Link from 'next/link';
import QRCode from 'qrcode';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { Speedy } from '@/components/citizen/speedy';
import { HeroVideo } from '@/components/citizen/hero-video';
import { LandingMotion } from '@/components/citizen/landing-motion';
import { CITIZEN_LABELS, DocumentStatus } from '@/lib/domain/document-status';
import { all as faqs } from '@/lib/domain/site-faq';
import { routes } from '@/lib/routes';

export const metadata: Metadata = {
  title: 'SPeED TraQR — Track Your City Hall Requests | City of San Pedro',
  description:
    'File a request with the City Government of San Pedro, Laguna online and track it from filing to pickup with a QR-coded tracking number.',
};

/**
 * The landing page.
 *
 * Renders with NO database query — a feature test asserts it, because an
 * ordinary visitor should never be held up (or shown an error) by the database.
 * The FAQ comes from the static `site-faq` module for the same reason, and the
 * hero's slip is an example drawn from constants.
 *
 * The page's two audiences each get exactly one way in: citizens the two hero
 * actions (repeated once at the end), staff a quiet header link.
 *
 * Motion (entrances, the How it works line, feature cards lighting up on touch
 * screens) lives in `LandingMotion`; the styles are the `.lp-*` rules in
 * globals.css.
 */
const BTN_PRIMARY = 'cz-btn cz-btn-primary lp-press';
const BTN_SECONDARY = 'cz-btn cz-btn-secondary lp-press';

/** The example slip in the hero. Not a real request: the QR says so when scanned. */
const SAMPLE_TRACKING = 'SPD-20261027-K7Q4MX';
const SAMPLE_STAGES = [
  DocumentStatus.Pending,
  DocumentStatus.InProgress,
  DocumentStatus.InReview,
  DocumentStatus.Completed,
].map((status) => CITIZEN_LABELS[status]);
const SAMPLE_CURRENT = 1;

const ICON_PROPS = {
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 2,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  viewBox: '0 0 24 24',
  'aria-hidden': true,
} as const;

const STEPS: Array<{ title: string; body: string; icon: React.ReactNode }> = [
  {
    title: 'Submit a request',
    body: 'Fill out the online form. Your tracking number and QR code arrive by email.',
    icon: <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5ZM14 3v5h5M12 17v-6m-3 3 3-3 3 3" />,
  },
  {
    title: 'City Hall processes it',
    body: 'Staff scan your request in and out at every office it passes through.',
    icon: <path d="M3 21h18M4 10h16M12 3 4 7v3h16V7l-8-4ZM6 10v8m4-8v8m4-8v8m4-8v8" />,
  },
  {
    title: 'Track its progress',
    body: 'Check the status and current office anytime with your tracking number or QR code.',
    icon: (
      <>
        <circle cx="11" cy="11" r="7" />
        <path d="m20 20-4-4" />
      </>
    ),
  },
  {
    title: 'Claim your document',
    body: 'When the status says “Ready for pickup”, bring your QR code to City Hall.',
    icon: <path d="M9 12l2 2 4-4M12 3l2.4 1.8 3 .1.9 2.8 2.4 1.8-.9 2.8.9 2.8-2.4 1.8-.9 2.8-3 .1L12 21l-2.4-1.8-3-.1-.9-2.8L3.3 14.5l.9-2.8-.9-2.8 2.4-1.8.9-2.8 3-.1L12 3Z" />,
  },
];

/**
 * One grid in place of the old "What We Do", "Key Features" and "Security"
 * sections, which described the same five ideas three times. Each card states
 * one thing the system actually does, in a citizen's terms. Each icon comes in
 * two cuts — the outline at rest and a solid one the card swaps to when it
 * lights up — drawn on one 24px grid so they share a stroke and colour.
 */
const FEATURES: Array<{
  title: string;
  body: React.ReactNode;
  line: React.ReactNode;
  solid: React.ReactNode;
}> = [
  {
    title: 'Real-time status',
    body: 'See which office has your request and what happens next, from filing to pickup.',
    line: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </>
    ),
    solid: (
      <>
        <circle cx="12" cy="12" r="10" fill="currentColor" />
        <path d="M12 7v5l3 2" fill="none" stroke="#fff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      </>
    ),
  },
  {
    title: 'QR codes only City Hall can read',
    body: 'Your slip’s QR code is signed. Ordinary scanner apps can’t open it, so it can’t be faked or misused.',
    line: (
      <>
        <path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z" />
        <path d="m9 12 2 2 4-4" />
      </>
    ),
    solid: (
      <>
        <path d="M12 2.5 4.5 5.7V11c0 4.8 3.2 8.8 7.5 10.6 4.3-1.8 7.5-5.8 7.5-10.6V5.7L12 2.5Z" fill="currentColor" />
        <path d="m8.8 12 2.2 2.2 4.2-4.4" fill="none" stroke="#fff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      </>
    ),
  },
  {
    title: 'Routed office to office',
    body: 'Staff scan your paper in and out at every department, so its trail is always current.',
    line: <path d="M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3-3m-3 3 3 3" />,
    // Arrows have no area to fill, so the solid cut sets them on a disc.
    solid: (
      <>
        <circle cx="12" cy="12" r="10.5" fill="currentColor" />
        <path
          d="M6.5 9h10m0 0-2.6-2.6M16.5 9l-2.6 2.6M17.5 15h-10m0 0 2.6-2.6M7.5 15l2.6 2.6"
          fill="none"
          stroke="#fff"
          strokeWidth="1.9"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </>
    ),
  },
  {
    title: 'Processing-time targets',
    body: (
      <>
        Every office works to a set processing time under RA&nbsp;11032, and late requests are
        flagged to staff.
      </>
    ),
    line: (
      <>
        <path d="M10 2h4M12 14l3-3" />
        <circle cx="12" cy="14" r="8" />
      </>
    ),
    solid: (
      <>
        <rect x="9.5" y="1" width="5" height="2.6" rx="1.3" fill="currentColor" />
        <circle cx="12" cy="14" r="8.8" fill="currentColor" />
        <path d="M12 14l3-3" fill="none" stroke="#fff" strokeWidth="2" strokeLinecap="round" />
        <circle cx="12" cy="14" r="1.4" fill="#fff" />
      </>
    ),
  },
  {
    title: 'Your details stay private',
    body: (
      <>
        The public tracking page shows your request’s status and location. Contact details are
        masked and files stay hidden, per RA&nbsp;10173.
      </>
    ),
    line: (
      <path d="M3 3l18 18M10.6 5.1A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7-.4 1-1.2 2.3-2.4 3.5M6.6 6.6C4.5 8 3 10 2 12c1 2.5 5 7 10 7 1.9 0 3.6-.6 5-1.5M9.9 9.9a3 3 0 0 0 4.2 4.2" />
    ),
    solid: (
      <>
        <path d="M1.8 12C3.4 8.2 7.3 4.8 12 4.8s8.6 3.4 10.2 7.2c-1.6 3.8-5.5 7.2-10.2 7.2S3.4 15.8 1.8 12Z" fill="currentColor" />
        <circle cx="12" cy="12" r="3.3" fill="#fff" />
        <path d="M4 3.5 20 20.5" stroke="#fff" strokeWidth="4.2" strokeLinecap="round" />
        <path d="M4 3.5 20 20.5" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" />
      </>
    ),
  },
  {
    title: 'Every action on record',
    body: 'Each scan, update and release is logged with the staff member who made it.',
    line: (
      <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 12h6M9 16h4" />
    ),
    solid: (
      <>
        <rect x="4" y="4.2" width="16" height="18" rx="2.6" fill="currentColor" />
        <rect x="8.2" y="2" width="7.6" height="4.8" rx="1.6" fill="currentColor" stroke="#fff" strokeWidth="1.5" />
        <path d="M8.5 12h7M8.5 16h4.5" stroke="#fff" strokeWidth="1.9" strokeLinecap="round" />
      </>
    ),
  },
];

/** The city's own photographs (public/images/landing/city), for the landmark strip. */
const CITY_PHOTOS = [
  {
    src: '/images/landing/city/welcome-arch.webp',
    alt: 'The Welcome to San Pedro arch, with a rooster statue on each side',
    caption: 'The Welcome to San Pedro arch',
  },
  {
    src: '/images/landing/city/san-pedro-apostol-parish.webp',
    alt: 'The yellow façade and bell tower of San Pedro Apostol Parish',
    caption: 'San Pedro Apostol Parish',
  },
  {
    src: '/images/landing/city/plaza-clock-tower.webp',
    alt: 'The plaza clock tower beside two white statues',
    caption: 'The plaza clock tower',
  },
  {
    src: '/images/landing/city/laguna-boundary.webp',
    alt: 'A tricycle on the national road passing the Laguna boundary marker',
    caption: 'Entering Laguna on the national road',
  },
];

const SECTION_HEADING = 'font-display text-[36px] font-black leading-[1.05] text-[#1a1b1a] sm:text-[52px]';
const SECTION_INTRO = 'text-pretty text-[18px] leading-[1.6] text-[#4b5a50]';

export default async function LandingPage() {
  const submitUrl = routes.publicRequestCreate();
  const trackUrl = routes.citizenTrack();

  // Plain text, unsigned: a phone that scans the example learns it is one.
  const sampleQr = await QRCode.toString(`SPeED TraQR example slip — ${SAMPLE_TRACKING} is not a real request.`, {
    type: 'svg',
    margin: 0,
    color: { dark: '#1a1b1a', light: '#0000' },
  });

  return (
    <div className="min-h-screen bg-white antialiased text-ink">
      {/* The shared citizen header, in its landing-page form: section links and
          the staff sign-in. Every section it targets sets scroll-margin to
          clear it. */}
      <PortalHeader landing />
      <LandingMotion />

      <main id="main">
        {/* ── Hero ───────────────────────────────────────────────────────────
            A muted video of the city behind the whole hero, under a white veil
            so the copy stays readable (`--hero-veil` sets how faded it is). It
            fades out as the page scrolls, into the white sections below. The
            example slip on the right shows what "tracked" means. */}
        <section id="top" className="hero-with-video relative isolate overflow-hidden bg-white">
          <HeroVideo src="/videos/landing-hero.mp4" poster="/images/landing/hero-video-poster.jpg" />

          <div className="mx-auto grid max-w-[1209.5px] items-center gap-12 px-4 pb-16 pt-12 sm:px-6 sm:pt-16 lg:min-h-[min(calc(100svh/var(--ui-zoom)-72px),760px)] lg:grid-cols-[1.25fr_1fr] lg:px-8 lg:py-20">
            <div>
              <h1 className="font-display text-[38px] font-black leading-[0.95] tracking-[-0.01em] text-[#004004] sm:text-[56px] lg:text-[60px] xl:text-[64px]">
                <span className="block">Your requests,</span>
                <span className="block">tracked in real time.</span>
              </h1>

              <p className="mt-7 max-w-[500px] text-pretty text-[18px] font-medium leading-[1.6] text-[#353535]">
                File a request with the City Government of San Pedro, Laguna online, get a
                QR-coded tracking number by email, and follow it from filing to pickup. No account
                needed.
              </p>

              <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                <Link href={submitUrl} className={BTN_PRIMARY}>
                  <svg {...ICON_PROPS}>
                    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" />
                    <path d="M14 3v5h5M12 11v6M9 14h6" />
                  </svg>
                  Submit a request
                </Link>
                <Link href={trackUrl} className={BTN_SECONDARY}>
                  <svg {...ICON_PROPS}>
                    <rect x="3.5" y="3.5" width="6" height="6" rx="1" />
                    <rect x="14.5" y="3.5" width="6" height="6" rx="1" />
                    <rect x="3.5" y="14.5" width="6" height="6" rx="1" />
                    <path d="M14.5 14.5h2m4 0h-2m0 0v2m0 4h-6v-2" />
                  </svg>
                  Track a request
                </Link>
              </div>
            </div>

            {/* The example slip: the same facts a real one carries, with the
                office named and never a staff member. */}
            <figure
              aria-label="Example of a request slip"
              className="w-full max-w-[480px] rounded-[20px] bg-white p-6 sm:p-8 lg:justify-self-end"
            >
              <figcaption className="flex items-center gap-2 text-[14px] font-bold text-[#004004]">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src="/images/landing/speed-icon.png" alt="" className="h-5 w-5 object-contain" />
                Example request slip
              </figcaption>

              <p className="mt-5 text-[14px] font-semibold text-[#5b6a5f]">Tracking number</p>
              <p className="mt-0.5 text-[20px] font-extrabold tracking-[0.04em] text-[#1a1b1a] sm:text-[22px]" translate="no">
                {SAMPLE_TRACKING}
              </p>

              <div className="mt-5 flex items-start gap-5">
                <div
                  className="h-[104px] w-[104px] shrink-0 rounded-[12px] bg-[#f5faf3] p-3 [&>svg]:h-full [&>svg]:w-full"
                  role="img"
                  aria-label={`QR code for ${SAMPLE_TRACKING}`}
                  // Generated above from a fixed string, never from user input.
                  dangerouslySetInnerHTML={{ __html: sampleQr }}
                />
                <dl className="min-w-0 pt-1">
                  <dt className="text-[14px] font-semibold text-[#5b6a5f]">Now at</dt>
                  <dd className="mt-0.5 font-display text-[22px] font-bold leading-[1.1] text-[#1a1b1a] sm:text-[24px]">
                    City Tourism, Culture and Arts Office
                  </dd>
                </dl>
              </div>

              <ol className="relative mt-7 grid grid-cols-4" aria-label="Status">
                <span className="absolute left-[12.5%] right-[12.5%] top-[11px] h-[3px] bg-[#e3eee0]" aria-hidden="true" />
                <span
                  className="absolute left-[12.5%] top-[11px] h-[3px] bg-[#01721a]"
                  style={{ width: `${(SAMPLE_CURRENT / (SAMPLE_STAGES.length - 1)) * 75}%` }}
                  aria-hidden="true"
                />
                {SAMPLE_STAGES.map((stage, index) => (
                  <li
                    key={stage}
                    className="relative flex flex-col items-center px-1 text-center"
                    aria-current={index === SAMPLE_CURRENT ? 'step' : undefined}
                  >
                    {index < SAMPLE_CURRENT ? (
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[#01721a] text-white">
                        <svg {...ICON_PROPS} strokeWidth={3} className="h-3.5 w-3.5">
                          <path d="m5 12 5 5 9-10" />
                        </svg>
                      </span>
                    ) : index === SAMPLE_CURRENT ? (
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-white ring-[3px] ring-inset ring-[#01721a]">
                        <span className="h-2 w-2 rounded-full bg-[#01721a]" />
                      </span>
                    ) : (
                      <span className="h-6 w-6 rounded-full bg-[#e3eee0]" />
                    )}
                    <span
                      className={`mt-2.5 text-[13px] leading-tight ${
                        index < SAMPLE_CURRENT
                          ? 'font-bold text-[#1a1b1a]'
                          : index === SAMPLE_CURRENT
                            ? 'font-bold text-[#01721a]'
                            : 'font-semibold text-[#5b6a5f]'
                      }`}
                    >
                      {stage}
                    </span>
                  </li>
                ))}
              </ol>
            </figure>
          </div>
        </section>

        {/* ── How it works ───────────────────────────────────────────────────
            The spine of the page, so it comes straight after the hero. Four
            stages on one line that fills as the section scrolls past, lighting
            each step as it is reached (LandingMotion sets --fill and data-on). */}
        <section
          id="how-it-works"
          className="relative isolate scroll-mt-20 bg-white px-4 py-24 sm:px-6 lg:px-8 lg:py-32"
        >
          <div className="mx-auto max-w-[1145.5px]">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" data-reveal>
              <h2 className={SECTION_HEADING}>How it works</h2>
              <p className={`${SECTION_INTRO} lg:max-w-[420px] lg:text-right`}>
                Four steps from filing your request to picking it up.
              </p>
            </div>

            <div className="lp-steps relative mt-14 lg:mt-16" data-steps>
              {/* From the first dot's centre to the last's (each column is a
                  quarter of the row), so no stub pokes out past the ends. */}
              <div
                className="absolute left-[24px] top-[22px] hidden h-[4px] w-[75%] rounded-full bg-[#eef8ea] lg:block"
                aria-hidden="true"
              >
                <div className="lp-steps-fill h-full w-full rounded-full bg-[#01721a]" />
              </div>

              <ol className="relative grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0">
                {STEPS.map((step) => (
                  <li key={step.title} className="lp-step lg:pr-12" data-step>
                    <span className="lp-step-dot flex h-12 w-12 items-center justify-center rounded-full">
                      <svg {...ICON_PROPS} className="h-6 w-6">
                        {step.icon}
                      </svg>
                    </span>
                    <h3 className="lp-step-title mt-6 font-display text-[24px] font-bold leading-tight">
                      {step.title}
                    </h3>
                    <p className="lp-step-body mt-2 text-[18px] leading-[1.55]">{step.body}</p>
                  </li>
                ))}
              </ol>
            </div>

            {/* The last step happens here: the building, and when it is open. */}
            <div className="mt-20 grid items-center gap-8 lg:mt-28 lg:grid-cols-[1.2fr_1fr] lg:gap-16" data-reveal>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src="/images/landing/city/city-hall.webp"
                alt="The front of San Pedro City Hall under a blue sky"
                width={960}
                height={720}
                loading="lazy"
                decoding="async"
                className="aspect-[4/3] w-full rounded-[20px] object-cover"
              />
              <div>
                <h3 className="font-display text-[32px] font-black leading-[1.05] text-[#1a1b1a] sm:text-[40px]">
                  Claimed at City Hall
                </h3>
                <p className={`${SECTION_INTRO} mt-4 max-w-[440px]`}>
                  When your request is Ready for Pickup, bring your QR code or tracking number to the
                  office that handled it at San Pedro City Hall.
                </p>
                <p className="mt-4 text-[18px] font-bold leading-[1.5] text-[#004004]">
                  Monday to Friday, 8:00&nbsp;AM – 5:00&nbsp;PM
                  <span className="block text-[16px] font-medium text-[#4b5a50]">
                    Excluding public holidays
                  </span>
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* ── Features ───────────────────────────────────────────────────── */}
        <section
          id="features"
          className="relative isolate scroll-mt-20 bg-[#f5faf3] px-4 py-24 sm:px-6 lg:px-8 lg:py-32"
        >
          <div className="mx-auto max-w-[1145.5px]">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" data-reveal>
              <h2 className={SECTION_HEADING}>Fast, transparent &amp; private</h2>
              <p className={`${SECTION_INTRO} lg:max-w-[440px] lg:text-right`}>
                One system follows your request from the counter where it is filed to the desk that
                releases it.
              </p>
            </div>

            <ul className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:mt-14 lg:grid-cols-3">
              {FEATURES.map((feature, index) => (
                <li
                  key={feature.title}
                  className="lp-card rounded-[20px] bg-white p-7 sm:p-9"
                  data-feature-card
                  data-reveal
                  style={{ '--lp-delay': `${(index % 3) * 80}ms` } as React.CSSProperties}
                >
                  <span className="lp-icon h-10 w-10 text-[#01721a]">
                    <svg {...ICON_PROPS} strokeWidth={1.75} className="lp-icon-line h-10 w-10">
                      {feature.line}
                    </svg>
                    <svg viewBox="0 0 24 24" aria-hidden="true" className="lp-icon-solid h-10 w-10">
                      {feature.solid}
                    </svg>
                  </span>
                  <h3 className="mt-8 font-display text-[24px] font-bold leading-tight text-[#1a1b1a]">
                    {feature.title}
                  </h3>
                  <p className="mt-2 text-[18px] leading-[1.55] text-[#4b5a50]">{feature.body}</p>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* ── The city ───────────────────────────────────────────────────────
            Whose service this is, shown rather than said: four San Pedro
            landmarks (the city's own photographs). */}
        <section
          aria-labelledby="city-heading"
          className="relative isolate bg-white px-4 pt-24 sm:px-6 lg:px-8 lg:pt-32"
        >
          <div className="mx-auto max-w-[1145.5px]">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" data-reveal>
              <h2 id="city-heading" className={SECTION_HEADING}>
                Built for San Pedro, Laguna
              </h2>
              <p className={`${SECTION_INTRO} lg:max-w-[440px] lg:text-right`}>
                The city’s own system, for the requests its residents and offices file every day.
              </p>
            </div>

            <ul className="mt-12 grid grid-cols-2 gap-4 lg:mt-14 lg:grid-cols-4 lg:gap-5">
              {CITY_PHOTOS.map((photo, index) => (
                <li
                  key={photo.src}
                  data-reveal
                  style={{ '--lp-delay': `${index * 80}ms` } as React.CSSProperties}
                >
                  <figure>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={photo.src}
                      alt={photo.alt}
                      width={960}
                      height={720}
                      loading="lazy"
                      decoding="async"
                      className="aspect-[3/4] w-full rounded-[20px] object-cover"
                    />
                    <figcaption className="mt-3 text-[16px] font-semibold leading-snug text-[#1a1b1a]">
                      {photo.caption}
                    </figcaption>
                  </figure>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* ── FAQ ─────────────────────────────────────────────────────────────
            Heading and a way to reach a person on the left, the questions on
            the right. Native <details>: keyboard-operable, searchable with
            find-in-page, and the answers are in the HTML for readers without
            JavaScript. Closed by default — six open answers made the questions
            a wall to scan. No bottom padding: the footer brings its own 120px. */}
        <section
          id="faq"
          className="relative isolate scroll-mt-20 bg-white px-4 pt-24 sm:px-6 lg:px-8 lg:pt-32"
        >
          <div className="mx-auto grid max-w-[1145.5px] gap-10 lg:grid-cols-[1fr_1.75fr] lg:gap-16">
            <div className="lg:sticky lg:top-28 lg:self-start" data-reveal>
              <h2 className={SECTION_HEADING}>Frequently asked questions</h2>
              <p className={`${SECTION_INTRO} mt-5 max-w-[360px]`}>
                Still stuck? Email{' '}
                <a
                  href="mailto:speedtraqr@gmail.com"
                  className="font-bold text-[#01721a] underline underline-offset-2 hover:text-[#004004]"
                >
                  speedtraqr@gmail.com
                </a>{' '}
                with your tracking number.
              </p>
            </div>

            <div className="flex flex-col gap-2" data-reveal>
              {faqs().map((faq) => (
                <details
                  key={faq.question}
                  className="group rounded-[16px] bg-[#f5faf3] transition-colors hover:bg-[#eef8ea] open:bg-[#eef8ea]"
                >
                  <summary className="flex cursor-pointer touch-manipulation list-none items-center justify-between gap-6 rounded-[16px] px-6 py-5 focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#01721a] sm:px-7 sm:py-6 [&::-webkit-details-marker]:hidden">
                    <span className="font-display text-[20px] font-bold leading-snug text-[#004004] sm:text-[24px]">
                      {faq.question}
                    </span>
                    <span
                      className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-[#01721a] transition-colors group-open:bg-[#004004] group-open:text-white"
                      aria-hidden="true"
                    >
                      <svg {...ICON_PROPS} strokeWidth={2.25} className="h-4 w-4">
                        <path d="M5 12h14" />
                        <path d="M12 5v14" className="group-open:hidden" />
                      </svg>
                    </span>
                  </summary>
                  {/* The answers carry one trusted link (to the track page) and
                      the RA references' non-breaking spaces — authored in
                      `site-faq`, never from user input. */}
                  <p
                    className="px-6 pb-6 text-[18px] leading-[1.6] text-[#353535] sm:px-7 sm:pb-7"
                    dangerouslySetInnerHTML={{ __html: faq.answerHtml }}
                  />
                </details>
              ))}
            </div>
          </div>
        </section>
      </main>

      {/* The page ends on a next step, not on the last answer: the closing
          invitation opens the footer's dark-green field. */}
      <SiteFooter>
        <section
          aria-labelledby="closing-heading"
          className="mx-auto flex max-w-[1209.5px] flex-col gap-8 px-4 pt-20 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8 lg:pt-28"
          data-reveal
        >
          <div>
            <h2 id="closing-heading" className="font-display text-[44px] font-black leading-[0.95] text-white sm:text-[64px]">
              Ready to file a request?
            </h2>
            <p className="mt-5 max-w-[520px] text-[18px] leading-[1.6] text-white/80">
              It takes a few minutes. You’ll get your tracking number by email right away.
            </p>
          </div>
          <div className="flex shrink-0 flex-col gap-3 sm:flex-row lg:pb-2">
            <Link href={submitUrl} className="cz-btn lp-press bg-[#8dff3c] text-[#004004] hover:bg-[#a6ff66] focus-visible:outline-[#8dff3c]">
              Submit a request
            </Link>
            <Link href={trackUrl} className="cz-btn lp-press bg-[#01721a] text-white hover:bg-[#0b8226] focus-visible:outline-[#8dff3c]">
              Track a request
            </Link>
          </div>
        </section>
      </SiteFooter>
      <Speedy />
    </div>
  );
}
