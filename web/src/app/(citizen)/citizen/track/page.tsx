import type { Metadata } from 'next';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { Speedy } from '@/components/citizen/speedy';
import { PageIntro } from '@/components/citizen/page-intro';
import { TrackLookup } from './track-lookup';

export const metadata: Metadata = { title: 'Track a request' };

/**
 * The public track page. Three ways in — type the number, scan the QR, or drop
 * a picture of it — all resolving to the tracking page.
 *
 * This is also where a failed lookup lands (a typo'd number redirects here with
 * `?tracking=`), so it reports "not found" itself rather than showing a bare 404.
 */
export default async function CitizenTrackPage({
  searchParams,
}: {
  searchParams: Promise<{ tracking?: string }>;
}) {
  const { tracking } = await searchParams;

  return (
    <div className="flex min-h-screen flex-col bg-white antialiased text-ink">
      <PortalHeader />

      <main id="main" className="px-4 py-10 sm:px-6 lg:py-14">
        <div className="mx-auto max-w-[640px]">
          <PageIntro eyebrow="Track a request" title="Where is my request?">
            Enter your tracking number, or scan the QR code from your confirmation email or printed
            slip. No account needed.
          </PageIntro>

          <div className="mt-8">
            <TrackLookup notFound={tracking ?? null} />
          </div>
        </div>
      </main>

      <SiteFooter />
      <Speedy />
    </div>
  );
}
