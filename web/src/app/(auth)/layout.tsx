/**
 * The guest shell — the sign-in card and the password-reset pages. Equivalent
 * of `components/guest-layout.blade.php`: no sidebar, no account menu, just the
 * card on the canvas.
 */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return <main id="main">{children}</main>;
}
