import type { Metadata } from 'next';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { Speedy } from '@/components/citizen/speedy';
import { PageIntro } from '@/components/citizen/page-intro';
import { RequestForm, type TypeOption } from '@/components/citizen/request-form';
import { db, toId } from '@/lib/db';
import { now, formatNaive } from '@/lib/time';

export const metadata: Metadata = {
  title: 'Submit a Request — SPeED TraQR',
  description:
    'File a request with the City Government of San Pedro, Laguna online and get a QR-coded tracking number by email.',
};

/**
 * The form reads the admin-managed catalog, so it must be rendered per request.
 *
 * Nothing on this page touches cookies or headers, so Next happily prerendered
 * it at build time — which froze the request types and the "Most requested"
 * counts into the bundle. A type added or deactivated in admin then never
 * reached the public form until the next deploy.
 */
export const dynamic = 'force-dynamic';

/**
 * Citizen self-service ticket creation (no account).
 *
 * Port of `PublicTicketController@create`. Both kinds of type are offered:
 * document types carry requirements, booking/equipment types carry a resource
 * plus date/time. The form branches on kind client-side.
 */
export default async function RequestPage() {
  // Starting a new request forgets the previous receipt, so the next person at a
  // shared counter terminal never finds it sitting there. That clearing happens
  // in the middleware — a server component cannot write cookies.
  const requestTypes = await db.request_types.findMany({
    where: {
      is_active: true,
      // A deactivated item (Resources page) is not offered either.
      OR: [{ resource_id: null }, { resource: { is_active: true } }],
    },
    include: { request_type_requirements: { orderBy: { sort_order: 'asc' } }, resource: true },
    orderBy: [{ sort_order: 'asc' }, { name: 'asc' }],
  });

  // Documents record the type by name, not by id, so popularity is counted off
  // that column rather than a relation.
  const filed = await db.documents.groupBy({
    by: ['document_type'],
    _count: { document_type: true },
  });

  const counts = new Map(
    filed.map((row) => [row.document_type, row._count.document_type]),
  );

  const types: TypeOption[] = requestTypes.map((type) => ({
    name: type.name,
    kind: type.kind,
    resource: type.resource?.name ?? null,
    requirements: type.request_type_requirements.map((requirement) => ({
      id: toId(requirement.id),
      label: requirement.label,
      mandatory: requirement.is_mandatory,
    })),
  }));

  // "Mostly Requested" shortcuts: the four types citizens actually file most, so
  // the common errands skip the dropdown entirely. Ties and a cold start fall
  // back to the curated sort order above.
  const popularTypes = [...types]
    .sort((a, b) => (counts.get(b.name) ?? 0) - (counts.get(a.name) ?? 0))
    .slice(0, 4)
    .map((type) => type.name);

  return (
    <>
      <PortalHeader />

      {/* Same container as the header, so the title lines up under the seal. */}
      <main id="main" className="mx-auto w-full max-w-[1209.5px] px-4 pb-16 pt-10 sm:px-6 sm:pt-14 lg:px-8">
        <PageIntro eyebrow="Online request" title="Submit a request">
          File your request without lining up at City Hall. You&rsquo;ll get a tracking number and QR
          code by email, so you can follow it from filing to pickup.
        </PageIntro>

        <RequestForm types={types} popularTypes={popularTypes} today={formatNaive(now(), 'Y-m-d')} />
      </main>

      <SiteFooter />
      <Speedy />
    </>
  );
}
