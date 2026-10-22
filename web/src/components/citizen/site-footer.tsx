import Link from 'next/link';
import { app } from '@/lib/config';
import { routes } from '@/lib/routes';

/**
 * Shared footer — the last thing on every page that scrolls.
 *
 * Who runs this, the privacy notice, how to reach them, and the legal line. No
 * navigation columns: every page already carries its own way forward (the
 * landing page's hero and closing actions, the citizen header's way home, the
 * staff sidebar), so a doormat here only repeated those links a second time.
 *
 * The plate is the brand's dark green so it reads as the end of the page on
 * every backdrop it sits on.
 *
 * The office hours below are the ordinary LGU schedule, not a value read from
 * the system — correct them here if San Pedro's windows differ.
 */
export function SiteFooter() {
  return (
    /* The plate needs air above it — the page's content should finish and
       settle before the dark green starts. `mt-auto` keeps a short page's
       footer at the bottom. */
    <div className="mt-auto pt-[120px]">
      <footer className="bg-[#004004] text-white">
        <div className="mx-auto w-full max-w-[1512px] px-6 py-[48px] sm:px-10 lg:py-[56px]">
          <div className="grid gap-x-12 gap-y-[40px] md:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr]">
            {/* Brand: what this is and who it belongs to, for anyone who landed
                deep in the site from a QR code or an emailed link. */}
            <div className="max-w-[520px]">
              <div className="flex items-center gap-[13px]">
                {/* The white mark, not the landing page's green one: the green
                    icon all but disappears on this plate. */}
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src="/images/staff/logo-white.png"
                  alt=""
                  width={46}
                  height={46}
                  className="h-[46px] w-[46px] shrink-0 object-contain"
                />
                <span className="text-[26px] leading-none" translate="no">
                  <span className="font-display font-black text-white">SPeED</span>{' '}
                  <span className="font-sans font-bold text-[#8dff3c]">TraQR</span>
                </span>
              </div>

              <p className="mt-[18px] text-[15px] leading-relaxed text-white/75">
                The document tracking system of the City Government of San Pedro, Laguna. Every
                request carries a QR-coded tracking number from the counter where it is filed to
                the desk that releases it.
              </p>

              {/* The owner's seal, on a white disc so its rim reads on the dark plate. */}
              <p className="mt-[18px] flex items-center gap-[10px] text-[14px] font-semibold text-[#8dff3c]">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src="/images/staff/seal-2.png"
                  alt=""
                  width={32}
                  height={32}
                  className="h-8 w-8 shrink-0 rounded-full bg-white"
                />
                San Pedro City Hall, Laguna
              </p>
            </div>

            {/* Privacy: the one page every form's consent box points to, so it
                has to be reachable from anywhere, not only from the form. */}
            <div>
              <h2 className="text-[13px] font-black uppercase tracking-[0.18em] text-[#8dff3c]">
                Your Privacy
              </h2>
              <p className="mt-[18px] max-w-[300px] text-[15px] leading-relaxed text-white/75">
                Your personal details are used only to process your request, and are masked on the
                public tracking page.
              </p>
              <Link
                href={routes.privacy()}
                className="footer-link mt-[12px] inline-flex items-center gap-[6px] text-[15px] font-semibold"
              >
                Read the privacy notice
                <svg
                  className="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth={2}
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                </svg>
              </Link>
            </div>

            {/* Contact. The email is a real mailbox, so it is a mailto rather
                than plain text — on a phone that is the whole interaction. */}
            <div>
              <h2 className="text-[13px] font-black uppercase tracking-[0.18em] text-[#8dff3c]">
                Get in Touch
              </h2>

              <ul className="mt-[18px] space-y-[14px] text-[15px] text-white/75">
                <li className="flex items-start gap-[10px]">
                  <svg
                    className="mt-[3px] h-[17px] w-[17px] shrink-0 text-[#8dff3c]"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M3 7.5 12 13l9-5.5M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"
                    />
                  </svg>
                  <a href="mailto:speedtraqr@gmail.com" className="footer-link break-all">
                    speedtraqr@gmail.com
                  </a>
                </li>

                <li className="flex items-start gap-[10px]">
                  <svg
                    className="mt-[3px] h-[17px] w-[17px] shrink-0 text-[#8dff3c]"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <circle cx="12" cy="12" r="9" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 7v5l3 2" />
                  </svg>
                  <span>
                    Monday to Friday, 8:00&nbsp;AM – 5:00&nbsp;PM
                    <span className="mt-[3px] block text-[13px] text-white/60">
                      Excluding public holidays
                    </span>
                  </span>
                </li>
              </ul>
            </div>
          </div>

          {/* Legal bar: the copyright line, and the two statutes the system's
              behaviour is actually built on — the SLA clock, and what the public
              tracking page is allowed to show. */}
          <div className="mt-[40px] flex flex-col gap-[14px] border-t border-white/15 pt-[24px] text-[13.5px] text-white/60 lg:flex-row lg:items-center lg:justify-between">
            <p>
              &copy; {new Date().getFullYear()} {app.name} — Document Tracking System. All rights
              reserved.
            </p>
            <p className="lg:text-right">
              Processing times follow RA&nbsp;11032 (Ease of Doing Business Act). Personal data is
              handled under RA&nbsp;10173 (Data Privacy Act of 2012).
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}
