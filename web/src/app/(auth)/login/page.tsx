import type { Metadata } from 'next';
import Link from 'next/link';
import { routes } from '@/lib/routes';
import { LoginCard } from './login-card';

export const metadata: Metadata = { title: 'Login' };

/**
 * Two jobs on one card: staff sign in, and — for anyone, no account needed —
 * tracking an internal request by its QR or number (the client's request).
 *
 * `?tab=track` opens on the tracker, which is where the public internal tracker
 * is linked from.
 */
export default async function LoginPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; next?: string }>;
}) {
  const { tab, next } = await searchParams;

  return (
    <div className="auth-shell">
      <Link href={routes.welcome()} className="auth-home-link">
        <svg fill="none" stroke="currentColor" strokeWidth={2.2} viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 12H5m6-6-6 6 6 6" />
        </svg>
        Back to Homepage
      </Link>

      <section className="auth-card">
        {/* Brand half */}
        <div className="auth-left">
          {/* The two municipal seals, as on the staff page bar. */}
          <div className="auth-seals">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/staff/seal-1.png" alt="City of San Pedro seal" />
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/staff/seal-2.png" alt="Tourism Office seal" />
          </div>

          <div className="auth-brand">
            {/* The white mark, darting forward with speed streaks trailing
                behind it. Decorative motion only; the CSS stills it for
                reduced-motion. */}
            <span className="auth-logo-stage">
              <span className="auth-trail" aria-hidden="true">
                {TRAILS.map((trail, index) => (
                  <i key={index} style={trail} />
                ))}
              </span>
              <span className="auth-logo-fly">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src="/images/staff/logo-white.png" alt="SPeED TraQR Logo" className="auth-logo" />
              </span>
            </span>
            <h1 className="brand-title">
              SPeED <span>TraQR</span>
            </h1>
            <p className="brand-subtitle">Secure document tracking and QR verification.</p>
          </div>

          <p className="auth-foot">City Government of San Pedro, Laguna</p>
        </div>

        <LoginCard initialTab={tab === 'track' ? 'track' : 'signin'} next={next} />
      </section>
    </div>
  );
}

/** The decorative speed streaks; each sets its own offset, width, colour and timing. */
const TRAILS = [
  { '--t': '6%', '--w': '56%', '--c': '#d4ea6b', '--d': '1.5s', '--l': '0s' },
  { '--t': '17%', '--w': '83%', '--c': '#4fbf6a', '--d': '1.9s', '--l': '0.35s' },
  { '--t': '27%', '--w': '40%', '--c': '#7ddc5a', '--d': '1.3s', '--l': '0.8s' },
  { '--t': '37%', '--w': '45%', '--c': '#9fd18a', '--d': '1.7s', '--l': '0.2s' },
  { '--t': '48%', '--w': '86%', '--c': '#d4ea6b', '--d': '2.0s', '--l': '0.6s' },
  { '--t': '60%', '--w': '35%', '--c': '#2f9e4f', '--d': '1.4s', '--l': '0.1s' },
  { '--t': '71%', '--w': '64%', '--c': '#5fc36f', '--d': '1.8s', '--l': '0.9s' },
  { '--t': '83%', '--w': '40%', '--c': '#7ddc5a', '--d': '1.6s', '--l': '0.45s' },
  { '--t': '94%', '--w': '75%', '--c': '#8fd46e', '--d': '2.1s', '--l': '0.25s' },
] as unknown as React.CSSProperties[];
