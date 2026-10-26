import type { Metadata, Viewport } from 'next';
import Script from 'next/script';
import { app } from '@/lib/config';
import './globals.css';

export const metadata: Metadata = {
  title: {
    default: app.name,
    // Page titles must be literals at the call site, as the Blade `@section`
    // titles were — hence the separate wrappers for pages that share a body.
    template: `%s · ${app.name}`,
  },
  description:
    'Track documents and service requests of the City Government of San Pedro.',
  robots: { index: true, follow: true },
};

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  // The design system is light-only (see globals.css); say so, or a browser in
  // dark mode renders form controls and autofill tints dark against white cards.
  colorScheme: 'light',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="bg-[var(--page-wash-base)] font-sans text-ink antialiased">
        {/* Keyboard users land here first — the Blade shell carried the same. */}
        <a href="#main" className="skip-link">
          Skip to content
        </a>
        {children}
        {/* Sienna accessibility toolbar (contrast, text size, dyslexia font…),
            https://accessibility-widget.pages.dev. Loaded after hydration so it
            never blocks first paint or touches the server-rendered markup.
            Themed and, inside the signed-in shell, tucked away in globals.css. */}
        {app.accessibilityWidget ? (
          <Script
            src="https://cdn.jsdelivr.net/npm/sienna-accessibility@2.2.333/dist/sienna-accessibility.umd.js"
            strategy="afterInteractive"
          />
        ) : null}
      </body>
    </html>
  );
}
