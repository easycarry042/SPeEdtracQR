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
  poweredByHeader: false,
  // Legacy storage paths: QR PNGs used to be served from /storage/qrcodes/*.
  async rewrites() {
    return [{ source: '/storage/:path*', destination: '/api/storage/:path*' }];
  },
  async headers() {
    return [{ source: '/:path*', headers: securityHeaders }];
  },
};

export default nextConfig;
