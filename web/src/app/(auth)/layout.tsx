import './auth.css';

/**
 * The guest shell — the sign-in card and the password-reset pages.
 *
 * Its styles were inline in `layouts/guest.blade.php` rather than in the shared
 * stylesheet, so they are imported here (see `auth.css`). No sidebar, no
 * account menu: just the card on a quiet canvas.
 */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="auth-page">
      <main id="main" className="auth-main">
        {children}
      </main>
    </div>
  );
}
