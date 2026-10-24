import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import QRCode from 'qrcode';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { Speedy } from '@/components/citizen/speedy';
import { db } from '@/lib/db';
import * as receipt from '@/lib/domain/citizen-receipt';
import * as trackingQr from '@/lib/domain/tracking-qr';
import { citizenTrackingUrl } from '@/lib/domain/document';

export const metadata: Metadata = {
  title: 'Request Submitted — SPeED TraQR',
  robots: { index: false, follow: false },
};

/**
 * The receipt: QR code and tracking number, shown after filing.
 *
 * Port of `PublicTicketController@submitted`. Reached by redirect from the
 * submit action, and reopenable (refresh, or Back from the slip or tracking
 * page) until the receipt cookie expires — after a short while, or as soon as
 * anyone starts over, so on a shared counter terminal the next person never
 * finds the previous citizen's receipt sitting there.
 */
export default async function RequestSubmittedPage() {
  const tracking = await receipt.trackingNumber();

  const document = tracking
    ? await db.documents.findFirst({
        where: { tracking_number: tracking },
        select: { tracking_number: true, document_type: true, qr_code_path: true },
      })
    : null;

  if (!document) {
    redirect('/request');
  }

  // Rendered inline rather than linked, so the receipt still shows the code if
  // the stored PNG failed to write.
  const qrSvg = await QRCode.toString(trackingQr.content(document.tracking_number), {
    type: 'svg',
    errorCorrectionLevel: 'M',
    margin: 1,
    width: 240,
  });

  const qrPng = document.qr_code_path ? `/storage/${document.qr_code_path}` : null;

  return (
    <>
      <PortalHeader />

      <main id="main" className="mx-auto w-full max-w-[1209.5px] px-4 pb-16 pt-10 sm:px-6 sm:pt-14 lg:px-8">
        <div className="mx-auto max-w-[680px]">
          <div className="cz-card px-6 py-8 text-center sm:px-10 sm:py-10">
            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#eef8ea] text-[#01721a]">
              <svg
                className="h-7 w-7"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>

            <h1 className="cz-title mt-5 text-[32px] sm:text-[38px]">Request submitted</h1>
            <p className="cz-lead mx-auto mt-3 max-w-[460px] text-[16px] sm:text-[17px]">
              Save your QR code and tracking number below. To follow your request, scan the code on
              this site&rsquo;s Track a request page — your phone&rsquo;s camera app won&rsquo;t open
              it.
            </p>

            <div
              className="mx-auto mt-7 flex h-60 w-60 items-center justify-center rounded-[16px] border border-[rgba(0,64,4,0.12)] bg-white p-3 [&_svg]:h-full [&_svg]:max-h-full [&_svg]:w-full [&_svg]:max-w-full"
              // The SVG is generated here from our own signed payload — no user
              // input reaches it.
              dangerouslySetInnerHTML={{ __html: qrSvg }}
            />

            <p className="cz-key mt-7">Tracking number</p>
            <p className="mt-2">
              <span className="cz-id px-4 py-2 text-[22px] sm:text-[26px]">
                {document.tracking_number}
              </span>
            </p>
            <p className="mt-3 text-[16px] font-medium text-[#4b5a50]">{document.document_type}</p>

            <div className="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:flex-wrap">
              {qrPng ? (
                <a
                  href={qrPng}
                  download={`${document.tracking_number}.png`}
                  className="cz-btn cz-btn-primary"
                >
                  <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                  </svg>
                  Download QR Code
                </a>
              ) : null}

              <Link
                href={`/track/${document.tracking_number}/slip?from=receipt`}
                className="cz-btn cz-btn-secondary"
              >
                <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"
                  />
                </svg>
                Print slip
              </Link>

              {/* Without a QR file to download, following the request is the next
                  step, so it takes the primary slot. */}
              <Link
                href={citizenTrackingUrl(document.tracking_number)}
                className={`cz-btn ${qrPng ? 'cz-btn-secondary' : 'cz-btn-primary'}`}
              >
                Track this request
              </Link>
            </div>
          </div>

          <p className="mt-6 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-[15px] text-[#4b5a50]">
            <Link href="/request" className="font-bold text-[#01721a] underline-offset-2 hover:underline">
              Submit another request
            </Link>
            <span aria-hidden="true">·</span>
            <Link href="/citizen/track" className="font-bold text-[#01721a] underline-offset-2 hover:underline">
              Track a different request
            </Link>
            <span aria-hidden="true">·</span>
            <Link href="/" className="font-bold text-[#01721a] underline-offset-2 hover:underline">
              Back to home
            </Link>
          </p>
        </div>
      </main>

      <SiteFooter />
      <Speedy trackingNumber={document.tracking_number} />
    </>
  );
}
