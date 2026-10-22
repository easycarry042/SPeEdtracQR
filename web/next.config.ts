import type { NextConfig } from 'next';

/**
 * Security headers mirror the Laravel `SecurityHeaders` middleware, and the
 * `no-store` Cache-Control that `PreventBackHistory` applied to authenticated
 * pages (SecurityHeadersTest / PreventBackHistoryTest guarded both).
 */
const securityHeaders = [
  { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
  { key: 'X-Content-Type-Options', value: 'nosniff' },
  { key: 'Referrer-Policy', value: 'same-origin' },
  { key: 'X-XSS-Protection', value: '0' },
  { key: 'Permissions-Policy', value: 'camera=(self), geolocation=(), microphone=()' },
];

const nextConfig: NextConfig = {
  reactStrictMode: true,
  experimental: {
    // Lets the permission gates call `forbidden()`, so a denial renders the 403
    // page with a real 403 status — Laravel's `permission:` middleware aborted
    // with 403 and the app rendered an error page for it. Without this a denial
    // fell through to an unhandled error and answered 500.
    authInterrupts: true,
  },
  poweredByHeader: false,
  // The Laravel app at the repo root has its own package-lock.json, so Next
  // guesses the wrong workspace root without this.
  outputFileTracingRoot: import.meta.dirname,
  // Legacy storage paths: QR PNGs used to be served from /storage/qrcodes/*.
  async rewrites() {
    return [{ source: '/storage/:path*', destination: '/api/storage/:path*' }];
  },
  async headers() {
    return [{ source: '/:path*', headers: securityHeaders }];
  },
};

export default nextConfig;
