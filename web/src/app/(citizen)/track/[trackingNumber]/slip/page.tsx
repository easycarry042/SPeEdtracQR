import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import QRCode from 'qrcode';
import { db } from '@/lib/db';
import { currentActor } from '@/lib/auth/actor';
import { citizenTrackingUrl, isInternal } from '@/lib/domain/document';
import * as trackingQr from '@/lib/domain/tracking-qr';
import { routes } from '@/lib/routes';
import { formatNaive } from '@/lib/time';
import { PrintButton } from './print-button';

/**
 * Port of `DocumentSlipController@show` — a print-ready claim slip: header,
 * the tracking QR, number, type and date, and counter instructions. Styled
 * for print, so "Save as PDF" works straight from the browser. Guarded by the
 * high-entropy tracking number, like the tracking page.
 */
export async function generateMetadata({
  params,
}: {
  params: Promise<{ trackingNumber: string }>;
}): Promise<Metadata> {
  return { title: `Claim Slip — ${decodeURIComponent((await params).trackingNumber)}` };
}

export default async function SlipPage({
  params,
  searchParams,
}: {
  params: Promise<{ trackingNumber: string }>;
  searchParams: Promise<{ from?: string }>;
}) {
  const tracking = decodeURIComponent((await params).trackingNumber).toUpperCase();
  const { from } = await searchParams;

  const document = await db.documents.findFirst({
    where: { tracking_number: tracking, deleted_at: null },
    select: { tracking_number: true, document_type: true, created_at: true, citizen_name: true, origin: true },
  });

  if (!document) {
    notFound();
  }

  // Back returns to whichever page linked here, not the browser's "previous
  // page", which on a shared counter PC could be anything.
  const signedIn = (await currentActor()) !== null;
  const backUrl =
    from === 'receipt'
      ? routes.publicRequestSubmitted()
      : from === 'tracking' || !signedIn
        ? citizenTrackingUrl(document.tracking_number)
        : routes.trackShow(document.tracking_number);

  const qrSvg = await QRCode.toString(trackingQr.payload(document.tracking_number), {
    type: 'svg',
    errorCorrectionLevel: 'M',
    margin: 1,
    width: 220,
  });
  const internal = isInternal(document);

  return (
    <div className="min-h-screen bg-[#f1f2f1] py-10 text-gray-900 antialiased print:bg-white print:py-0">
      <style>{`@page { margin: 16mm; }`}</style>

      <div className="mx-auto mb-6 flex max-w-xl items-center justify-between px-6 print:hidden">
        <a href={backUrl} className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-800 hover:underline">
          <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          Back
        </a>
        <PrintButton />
      </div>

      <div className="mx-auto max-w-xl overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm print:m-0 print:border-gray-300 print:shadow-none">
        <div className="flex items-center gap-3 border-b border-gray-200 px-8 py-5">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/images/icon.png" alt="" className="h-10 w-10 rounded-lg" />
          <div className="min-w-0">
            <p className="text-sm font-extrabold tracking-tight text-emerald-950">
              SPeED <span className="text-emerald-700">TraQR</span>
            </p>
            <p className="text-xs text-gray-500">Municipal Document Tracking · Official Claim Slip</p>
          </div>
          <span
            className={`ml-auto rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide ${internal ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`}
          >
            {internal ? 'Internal' : 'Citizen'}
          </span>
        </div>

        <div className="px-8 py-8 text-center">
          <div
            className="mx-auto flex h-56 w-56 items-center justify-center rounded-xl border border-gray-200 bg-white p-3 [&_svg]:h-full [&_svg]:w-full"
            // Rendered by `qrcode` from our own signed payload — no user input.
            dangerouslySetInnerHTML={{ __html: qrSvg }}
          />

          <p className="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">Tracking Number</p>
          <p className="mt-1 inline-block rounded-lg bg-emerald-50 px-4 py-2 font-mono text-2xl font-extrabold tracking-wide text-emerald-950">
            {document.tracking_number}
          </p>

          <dl className="mx-auto mt-6 grid max-w-sm grid-cols-2 gap-x-6 gap-y-3 text-left text-sm">
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-gray-400">Request Type</dt>
              <dd className="mt-0.5 font-medium text-gray-800">{document.document_type}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wide text-gray-400">Date Issued</dt>
              <dd className="mt-0.5 font-medium text-gray-800">
                {document.created_at ? formatNaive(document.created_at, 'M j, Y') : '—'}
              </dd>
            </div>
            {document.citizen_name ? (
              <div className="col-span-2">
                <dt className="text-xs font-semibold uppercase tracking-wide text-gray-400">Requester</dt>
                <dd className="mt-0.5 font-medium text-gray-800">{document.citizen_name}</dd>
              </div>
            ) : null}
          </dl>
        </div>

        <div className="border-t border-gray-200 bg-[#f8faf8] px-8 py-5 text-center">
          <p className="text-xs text-gray-600">
            Present this slip at the counter. To follow this request, go to{' '}
            <span className="font-semibold text-emerald-800">{routes.citizenTrack()}</span> and scan the QR code or enter
            the tracking number there.
          </p>
        </div>
      </div>
    </div>
  );
}
