import Link from 'next/link';
import { routes } from '@/lib/routes';

/**
 * The one header every citizen-facing page wears: the landing page, the request
 * form and its receipt, the track lookup, the tracking result and the privacy
 * notice. It used to be three different bars, so moving from the landing page
 * into the form felt like leaving the site.
 *
 * Owner first: the City seal leads, so a visitor knows whose service this is
 * before they read the product name. Sticky on a frosted white surface, so it
 * stays legible over anything scrolling beneath it.
 *
 * The landing page passes `landing` for its section links and the staff
 * sign-in; every other page gets one way home instead.
 */
const NAV_LINK =
  'inline-flex h-11 touch-manipulation items-center rounded-full px-3.5 text-[15px] font-semibold text-[#3a4a3f] transition-colors hover:bg-[#eef8ea] hover:text-[#004004] focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#01721a]';

export function PortalHeader({ landing = false }: { landing?: boolean }) {
  const homeUrl = routes.welcome(undefined);

  return (
    <header className="sticky top-0 z-50 border-b border-[rgba(0,64,4,0.08)] bg-white/90 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-[1209.5px] items-center justify-between gap-4 px-4 sm:h-[72px] sm:px-6 lg:px-8">
        <a
          href={landing ? '#top' : homeUrl}
          className="flex min-w-0 items-center gap-2.5 rounded-xl focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-4 focus-visible:outline-[#01721a] sm:gap-3"
        >
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src="/images/staff/seal-2.png"
            alt="City of San Pedro seal"
            width={44}
            height={44}
            className="h-10 w-10 shrink-0 sm:h-11 sm:w-11"
          />
          <span className="hidden h-9 w-px shrink-0 bg-[rgba(0,64,4,0.15)] sm:block" aria-hidden="true" />
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src="/images/landing/speed-icon.png"
            alt=""
            width={36}
            height={36}
            className="hidden h-9 w-9 shrink-0 object-contain sm:block"
          />
          <span className="flex min-w-0 flex-col">
            <span className="text-[19px] leading-none sm:text-[22px]" translate="no">
              <span className="font-display font-black text-[#004004]">SPeED</span>{' '}
              <span className="font-sans font-bold text-[#017209]">TraQR</span>
            </span>
            <span className="mt-1 truncate text-[12px] font-semibold leading-none text-[#4b5a50] sm:text-[13px]">
              City of San Pedro, Laguna
            </span>
          </span>
        </a>

        {landing ? (
          <nav aria-label="Primary" className="flex shrink-0 items-center gap-1">
            {/* Section links only fit from tablet up; below that the page is one
                short scroll and the hero already holds both actions. */}
            <ul className="hidden items-center gap-0.5 md:flex">
              <li>
                <a href="#how-it-works" className={NAV_LINK}>
                  How it works
                </a>
              </li>
              <li>
                <a href="#features" className={NAV_LINK}>
                  Features
                </a>
              </li>
              <li>
                <a href="#faq" className={NAV_LINK}>
                  FAQ
                </a>
              </li>
            </ul>
            <span className="mx-2 hidden h-6 w-px bg-[rgba(0,64,4,0.15)] md:block" aria-hidden="true" />
            {/* Staff are the minority audience: a quiet outlined link, never a
                filled button competing with the citizen actions. */}
            <Link href={routes.login()} className="cz-btn cz-btn-secondary cz-btn-sm">
              Staff sign in
            </Link>
          </nav>
        ) : (
          /* One way out, to the landing page — the citizen's home. Never
             history.back(): browser history could return the visitor anywhere
             (a search engine, the staff login, an unrelated tab). Citizen-
             flagged, so a staff login on a shared counter PC cannot divert it
             to the staff desk. */
          <a href={homeUrl} className="cz-btn cz-btn-secondary cz-btn-sm shrink-0">
            <svg fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l9-8 9 8M5 10v10h14V10" />
            </svg>
            <span className="hidden sm:inline">Back to Homepage</span>
            <span className="sm:hidden">Home</span>
          </a>
        )}
      </div>
    </header>
  );
}
