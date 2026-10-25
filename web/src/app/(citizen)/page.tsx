import type { Metadata } from 'next';
import Link from 'next/link';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { Speedy } from '@/components/citizen/speedy';
import { HeroVideo } from '@/components/citizen/hero-video';
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
 * The FAQ comes from the static `site-faq` module for the same reason.
 *
 * The page's two audiences each get exactly one way in: citizens the two hero
 * actions (repeated once at the end), staff a quiet header link.
 */
const BTN_PRIMARY = 'cz-btn cz-btn-primary';
const BTN_SECONDARY = 'cz-btn cz-btn-secondary';

const STEPS = [
  {
    icon: 'step-1.svg',
    num: 'step-num-1.svg',
    width: 'w-[36px]',
    title: 'Submit a request',
    body: 'Fill out the online form. Your tracking number and QR code arrive by email.',
  },
  {
    icon: 'step-2.svg',
    num: 'step-num-2.svg',
    width: 'w-[48px]',
    title: 'City Hall processes it',
    body: 'Staff scan your request in and out at every office it passes through.',
  },
  {
    icon: 'step-3.svg',
    num: 'step-num-3.svg',
    width: 'w-[48px]',
    title: 'Track its progress',
    body: 'Check the status and current office anytime with your tracking number or QR code.',
  },
  {
    icon: 'step-4.svg',
    num: 'step-num-4.svg',
    width: 'w-[48px]',
    title: 'Claim your document',
    body: 'When the status says “Ready for pickup”, bring your QR code to City Hall.',
  },
];

/**
 * One grid in place of the old "What We Do", "Key Features" and "Security"
 * sections, which described the same five ideas three times. Each card states
 * one thing the system actually does, in a citizen's terms; the icons are
 * inline so they share one stroke and colour.
 */
const FEATURES: Array<{ title: string; body: React.ReactNode; icon: React.ReactNode }> = [
  {
    title: 'Real-time status',
    body: 'See which office has your request and what happens next, from filing to pickup.',
    icon: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 7v5l3 2" />
      </>
    ),
  },
  {
    title: 'QR codes only City Hall can read',
    body: 'Your slip’s QR code is signed. Ordinary scanner apps can’t open it, so it can’t be faked or misused.',
    icon: (
      <>
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z"
        />
        <path strokeLinecap="round" strokeLinejoin="round" d="m9 12 2 2 4-4" />
      </>
    ),
  },
  {
    title: 'Routed office to office',
    body: 'Staff scan your paper in and out at every department, so its trail is always current.',
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3-3m-3 3 3 3"
      />
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
    icon: (
      <>
        <path strokeLinecap="round" strokeLinejoin="round" d="M10 2h4M12 14l3-3" />
        <circle cx="12" cy="14" r="8" />
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
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M3 3l18 18M10.6 5.1A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7-.4 1-1.2 2.3-2.4 3.5M6.6 6.6C4.5 8 3 10 2 12c1 2.5 5 7 10 7 1.9 0 3.6-.6 5-1.5M9.9 9.9a3 3 0 0 0 4.2 4.2"
      />
    ),
  },
  {
    title: 'Every action on record',
    body: 'Each scan, update and release is logged with the staff member who made it.',
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 12h6M9 16h4"
      />
    ),
  },
];

export default function LandingPage() {
  const submitUrl = routes.publicRequestCreate();
  const trackUrl = routes.citizenTrack();

  return (
    <div className="min-h-screen bg-white antialiased text-ink">
      {/* The shared citizen header, in its landing-page form: section links and
          the staff sign-in. Every section it targets sets scroll-margin to
          clear it. */}
      <PortalHeader landing />

      <main id="main">
        {/* ── Hero ───────────────────────────────────────────────────────────
            A muted video of the city behind the whole hero, under a white veil
            so the copy stays readable (`--hero-veil` sets how faded it is). It
            fades out as the page scrolls, into the white sections below. */}
        <section id="top" className="hero-with-video relative isolate overflow-hidden bg-white">
          <HeroVideo src="/videos/landing-hero.mp4" poster="/images/landing/hero-video-poster.jpg" />

          <div className="mx-auto flex max-w-[1209.5px] flex-col px-4 pb-14 pt-12 sm:px-6 sm:pt-16 lg:min-h-[min(calc(100svh/var(--ui-zoom)-72px),720px)] lg:justify-center lg:px-8 lg:py-20">
            <div className="max-w-[560px]">
              <p className="inline-flex items-center gap-2 rounded-full bg-[#eef8ea] px-3.5 py-1.5 text-[13px] font-bold text-[#004004]">
                <span className="h-2 w-2 rounded-full bg-[#01721a]" aria-hidden="true" />
                City Government of San Pedro, Laguna
              </p>

              <h1 className="mt-5 font-display text-[38px] font-black leading-[1.05] tracking-tight sm:text-[50px] lg:text-[58px]">
                <span className="block text-[#004004]">Your requests,</span>
                <span className="block text-[#01721a]">tracked in real time.</span>
              </h1>

              <p className="mt-5 max-w-[440px] text-pretty text-[17px] font-medium leading-relaxed text-[#353535] sm:text-[18px]">
                File a request online, get a QR-coded tracking number by email, and follow it from
                filing to pickup. No account needed.
              </p>

              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link href={submitUrl} className={BTN_PRIMARY}>
                  <svg
                    className="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"
                    />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M14 3v5h5M12 11v6M9 14h6" />
                  </svg>
                  Submit a request
                </Link>
                <Link href={trackUrl} className={BTN_SECONDARY}>
                  <svg
                    className="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <rect x="3.5" y="3.5" width="6" height="6" rx="1" />
                    <rect x="14.5" y="3.5" width="6" height="6" rx="1" />
                    <rect x="3.5" y="14.5" width="6" height="6" rx="1" />
                    <path strokeLinecap="round" d="M14.5 14.5h2m4 0h-2m0 0v2m0 4h-6v-2" />
                  </svg>
                  Track a request
                </Link>
              </div>
            </div>

          </div>
        </section>

        {/* ── How it works ───────────────────────────────────────────────────
            The spine of the page, so it comes straight after the hero. Four
            stages on one rule: a single 3px line behind the tiles rather than
            three segments, so it cannot drift when the columns resize. */}
        <section
          id="how-it-works"
          className="relative isolate scroll-mt-20 bg-white px-4 py-20 sm:px-6 lg:py-24"
        >
          <div className="mx-auto max-w-[1209.5px]">
            <div className="mx-auto max-w-[640px] text-center">
              <h2 className="font-display text-[32px] font-black leading-tight text-[#1a1b1a] sm:text-[42px]">
                How it <span className="text-[#01721a]">works</span>
              </h2>
              <p className="mt-3 text-balance text-[17px] font-medium leading-relaxed text-[#4b5a50] sm:text-[19px]">
                Four steps from filing your request to picking it up.
              </p>
            </div>

            <div className="relative mx-auto mt-14 max-w-[1140px]">
              {/* Starts and ends at the centre of the outer columns (1/8 and 7/8
                  of the row), so no stub pokes out past the end tiles. */}
              <div
                className="pointer-events-none absolute left-[12.5%] right-[12.5%] top-[57px] hidden h-[3px] bg-[#017209] lg:block"
                aria-hidden="true"
              />

              <ol className="grid grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                {STEPS.map((step, index) => (
                  <li key={step.title} className="relative flex flex-col items-center text-center">
                    {/* The pale green plate is 20px larger than the white tile on
                        every side; the number rides its corner. */}
                    <div className="relative flex h-[117px] w-[119px] shrink-0 items-center justify-center rounded-[10px] bg-[#d9eadb]">
                      <div className="flex h-[95px] w-[98px] items-center justify-center rounded-[20px] border border-[#01721a] bg-white">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={`/images/landing/${step.icon}`}
                          alt=""
                          aria-hidden="true"
                          className={`h-[48px] ${step.width} object-contain`}
                        />
                      </div>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={`/images/landing/${step.num}`}
                        alt=""
                        aria-hidden="true"
                        className="absolute -top-[6px] right-[-16px] h-[44px] w-[39px] object-contain"
                      />
                    </div>

                    <h3 className="mt-7 text-[20px] font-black leading-tight text-[#1a1b1a]">
                      <span className="sr-only">Step {index + 1}: </span>
                      {step.title}
                    </h3>
                    <p className="mt-2 max-w-[260px] text-[16px] leading-relaxed text-[#4b5a50]">
                      {step.body}
                    </p>
                  </li>
                ))}
              </ol>
            </div>
          </div>
        </section>

        {/* ── Features ───────────────────────────────────────────────────── */}
        <section
          id="features"
          className="relative isolate scroll-mt-20 overflow-hidden bg-[#f5faf3] px-4 py-20 sm:px-6 lg:py-24"
        >
          <div className="mx-auto max-w-[1209.5px]">
            <div className="mx-auto max-w-[680px] text-center">
              <h2 className="font-display text-[32px] font-black leading-tight text-[#1a1b1a] sm:text-[42px]">
                Fast, transparent &amp; <span className="text-[#01721a]">private</span>
              </h2>
              <p className="mt-3 text-balance text-[17px] font-medium leading-relaxed text-[#4b5a50] sm:text-[19px]">
                One system follows your request from the counter where it is filed to the desk that
                releases it.
              </p>
            </div>

            <ul className="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {FEATURES.map((feature) => (
                <li
                  key={feature.title}
                  className="rounded-[20px] border border-[rgba(0,64,4,0.08)] bg-white p-7 shadow-[0_1px_2px_rgba(0,64,4,0.06),0_8px_24px_-12px_rgba(0,64,4,0.18)]"
                >
                  <span className="flex h-14 w-14 items-center justify-center rounded-[16px] bg-[#eef8ea] text-[#01721a]">
                    <svg
                      className="h-7 w-7"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth={1.75}
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      {feature.icon}
                    </svg>
                  </span>
                  <h3 className="mt-5 font-display text-[21px] font-black leading-snug text-[#1a1b1a]">
                    {feature.title}
                  </h3>
                  <p className="mt-2 text-[16px] leading-relaxed text-[#4b5a50]">{feature.body}</p>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* ── FAQ ─────────────────────────────────────────────────────────────
            Native <details>: keyboard-operable, searchable with find-in-page,
            and the answers are in the HTML for readers without JavaScript.
            Closed by default — six open answers made the questions a wall to
            scan. No bottom padding: the footer brings its own 120px of air. */}
        <section
          id="faq"
          className="relative isolate scroll-mt-20 bg-white px-4 pt-20 sm:px-6 lg:pt-24"
        >
          <div className="mx-auto max-w-[820px]">
            <h2 className="text-center font-display text-[32px] font-black leading-tight text-[#1a1b1a] sm:text-[42px]">
              Frequently asked <span className="text-[#01721a]">questions</span>
            </h2>

            <div className="mt-10 flex flex-col gap-3">
              {faqs().map((faq) => (
                <details
                  key={faq.question}
                  className="group rounded-[16px] border border-[rgba(0,64,4,0.12)] bg-white shadow-[0_1px_2px_rgba(0,64,4,0.05)] open:border-[rgba(1,114,26,0.4)] open:bg-[#fbfdfa]"
                >
                  <summary className="flex cursor-pointer touch-manipulation list-none items-start justify-between gap-6 rounded-[16px] px-6 py-5 focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#01721a] [&::-webkit-details-marker]:hidden">
                    <span className="font-display text-[19px] font-bold leading-snug text-[#004004] sm:text-[21px]">
                      {faq.question}
                    </span>
                    <svg
                      className="mt-1 h-5 w-5 shrink-0 text-[#01721a] transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth={2.25}
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                  </summary>
                  {/* The answers carry one trusted link (to the track page) and
                      the RA references' non-breaking spaces — authored in
                      `site-faq`, never from user input. */}
                  <p
                    className="px-6 pb-6 text-[16px] leading-relaxed text-[#353535] sm:text-[17px]"
                    dangerouslySetInnerHTML={{ __html: faq.answerHtml }}
                  />
                </details>
              ))}
            </div>

            {/* The page ends on a next step, not on the last answer: the footer
                no longer carries navigation. */}
            <div className="mt-16 rounded-[24px] bg-[#eef8ea] px-6 py-10 text-center sm:px-10">
              <h2 className="font-display text-[26px] font-black leading-tight text-[#004004] sm:text-[30px]">
                Ready to file a request?
              </h2>
              <p className="mx-auto mt-2 max-w-[460px] text-balance text-[16px] leading-relaxed text-[#4b5a50]">
                It takes a few minutes. You’ll get your tracking number by email right away.
              </p>
              <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                <Link href={submitUrl} className={BTN_PRIMARY}>
                  Submit a request
                </Link>
                <Link href={trackUrl} className={BTN_SECONDARY}>
                  Track a request
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>

      <SiteFooter />
      <Speedy />
    </div>
  );
}
