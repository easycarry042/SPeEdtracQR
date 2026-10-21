import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { PageIntro } from '@/components/citizen/page-intro';
import { PublicTrackForm } from '@/components/citizen/public-track-form';
import { routes } from '@/lib/routes';
import { trackingNumber as readSignedPayload, URL_PARAMETER } from '@/lib/domain/tracking-qr';
import { summary } from '@/lib/domain/internal-tracking';

export const metadata: Metadata = { title: 'Track an internal request' };

/**
 * The public internal-request tracker — what an INT- QR opens on a phone.
 *
 * No sign-in: anyone holding the folder can see where it is. What they get is
 * deliberately thin — status, the office holding it, who there is handling it,
 * and the offices it passed through. Never the purpose, the remarks or the
 * files, because a tracking number is printed on a folder anyone can pick up.
 *
 * An SPD- number typed in here belongs to the citizen tracker, so it is sent
 * there rather than reported as not found.
 */
export default async function InternalTrackPage({
  searchParams,
}: {
  searchParams: Promise<{ number?: string; qr?: string }>;
}) {
  const params = await searchParams;

  // The QR encodes a signed payload inside this page's URL, so a phone camera
  // can open it. The signature is verified here, not in the browser.
  const fromQr = params[URL_PARAMETER] ? readSignedPayload(params[URL_PARAMETER]) : null;
  const typed = (params.number ?? '').trim().toUpperCase();
  const tracking = fromQr ?? typed;

  if (tracking.startsWith('SPD-')) {
    redirect(routes.trackShow(tracking));
  }

  const result = tracking === '' ? null : await summary(tracking);

  return (
    <div className="flex min-h-screen flex-col bg-white antialiased text-ink">
      <PortalHeader />

      <main id="main" className="px-4 py-10 sm:px-6 lg:py-14">
        <div className="mx-auto max-w-[720px]">
          <PageIntro eyebrow="Internal request" title="Where is this request?">
            Scan the QR on the folder, or type its number, to see which office has it and who is
            handling it. No account needed.
          </PageIntro>

          <div className="mt-8">
            <PublicTrackForm number={typed} />
          </div>

          {tracking !== '' && !result ? (
            <div className="it-result mt-6" role="status">
              <p className="it-result-head">No record found.</p>
              <p className="mt-1 text-[15px] text-[#4b5a50]">
                We couldn&apos;t find an internal request with that number. Check it and try again.
              </p>
            </div>
          ) : null}

          {result ? (
            <section className="cz-card mt-6">
              <div className="cz-card-head">
                <div>
                  <h2 className="cz-card-title">{result.tracking}</h2>
                  <p className="cz-card-sub">
                    Filed by {result.filedBy ?? 'an office'} · {result.filedOn}
                  </p>
                </div>
                <span className={`pill p-${result.band === 'returned' ? 'amber' : result.band}`}>
                  {result.status}
                </span>
              </div>

              <div className="cz-card-body">
                <div className="it-facts it-facts-wide">
                  <div>
                    <dt className="cz-key">With</dt>
                    <dd className="mt-1 text-[16px] text-[#1a1b1a]">
                      {result.now.office ?? '—'}
                    </dd>
                  </div>
                  {/* Names are the client's ask here — unlike the citizen
                      tracker, where staff stay anonymous. The config switch
                      turns them off for offices-only. */}
                  {result.showsNames ? (
                    <div>
                      <dt className="cz-key">Handled by</dt>
                      <dd className="mt-1 text-[16px] text-[#1a1b1a]">
                        {result.now.person ?? 'Not received yet'}
                      </dd>
                    </div>
                  ) : null}
                </div>

                <p className="it-note mt-3">{result.now.note}</p>

                <h3 className="dm-section mt-6">Offices it passed through</h3>
                <ol className="it-steps">
                  {result.steps.map((step, index) => (
                    <li key={index} className={step.current ? 'is-current' : undefined}>
                      <span className="it-dot" aria-hidden="true" />
                      <span className="it-subhead">
                        {step.office ?? 'Office'}
                        {step.code ? <span className="chip">{step.code}</span> : null}
                      </span>
                      <span className="dir-sub">
                        {step.state}
                        {step.when ? ` · ${step.when}` : ''}
                      </span>
                    </li>
                  ))}
                </ol>
              </div>
            </section>
          ) : null}
        </div>
      </main>

      <SiteFooter />
    </div>
  );
}
