import Link from 'next/link';

/**
 * The 403 page, rendered whenever a permission gate calls `forbidden()`.
 *
 * Port of the Laravel `errors/403` view. It says plainly that the page belongs
 * to another role rather than implying the site is broken, and offers the way
 * back — a denial that dead-ends is how people conclude the system is faulty
 * and stop using it.
 */
export default function Forbidden() {
  return (
    <main className="page-shell page-shell-narrow" style={{ paddingTop: 64, paddingBottom: 64 }}>
      <div className="panel" style={{ padding: 32, textAlign: 'center' }}>
        <p
          style={{
            fontSize: 12,
            fontWeight: 700,
            letterSpacing: '0.08em',
            textTransform: 'uppercase',
            color: 'var(--ink-soft)',
          }}
        >
          403 · Not yours to open
        </p>

        <h1 style={{ marginTop: 10, fontSize: 26, fontWeight: 800, color: 'var(--green-deep)' }}>
          You don&rsquo;t have access to this page
        </h1>

        <p style={{ marginTop: 12, fontSize: 15, color: 'var(--ink-soft)', lineHeight: 1.6 }}>
          This page belongs to a different role. Nothing is wrong with your account — if you think
          you should be able to open it, ask your supervisor or a system administrator to adjust
          your access.
        </p>

        <div
          style={{
            marginTop: 24,
            display: 'flex',
            gap: 10,
            justifyContent: 'center',
            flexWrap: 'wrap',
          }}
        >
          {/* `/home` resolves the right landing page for whatever role they do have. */}
          <Link href="/home" className="cr-btn cr-btn-primary">
            Go to my dashboard
          </Link>
          <Link href="/" className="cr-btn">
            Back to the public site
          </Link>
        </div>
      </div>
    </main>
  );
}
