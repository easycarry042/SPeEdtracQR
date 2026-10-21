import nodemailer, { type Transporter } from 'nodemailer';
import { app } from '@/lib/config';

/**
 * Outbound mail, replacing Laravel's Mailable + queue worker.
 *
 * Laravel's mailables were `ShouldQueue`, so a slow SMTP handshake never held
 * up a request. There is no queue worker here, so `queueMail()` hands the send
 * off with `after()` — it runs once the response has been streamed, and a
 * failure is logged rather than thrown into the user's request.
 */
let transporter: Transporter | null = null;

function transport(): Transporter {
  if (transporter) {
    return transporter;
  }

  const host = process.env.MAIL_HOST;

  if (!host) {
    throw new MailNotConfiguredError();
  }

  const port = Number.parseInt(process.env.MAIL_PORT ?? '587', 10);

  transporter = nodemailer.createTransport({
    host,
    port,
    // Port 587 is STARTTLS (upgrade after connecting), 465 is implicit TLS.
    secure: port === 465,
    auth: process.env.MAIL_USERNAME
      ? { user: process.env.MAIL_USERNAME, pass: process.env.MAIL_PASSWORD }
      : undefined,
  });

  return transporter;
}

export class MailNotConfiguredError extends Error {
  constructor() {
    super('No MAIL_HOST configured — refusing to pretend a message was sent.');
    this.name = 'MailNotConfiguredError';
  }
}

export type Mailable = {
  to: string;
  subject: string;
  html: string;
  attachments?: Array<{ filename: string; path?: string; content?: Buffer }>;
};

export async function sendMail(message: Mailable): Promise<void> {
  const from = process.env.MAIL_FROM_ADDRESS
    ? `"${process.env.MAIL_FROM_NAME ?? app.name}" <${process.env.MAIL_FROM_ADDRESS}>`
    : app.name;

  await transport().sendMail({
    from,
    to: message.to,
    subject: message.subject,
    html: message.html,
    attachments: message.attachments,
  });
}

/**
 * Send without making the caller wait, the way `ShouldQueue` did. Errors are
 * logged: a citizen's status email failing must not roll back the status change
 * that triggered it.
 */
export function queueMail(message: Mailable): void {
  void sendMail(message).catch((error: unknown) => {
    console.error(`[mail] failed to send "${message.subject}" to ${message.to}:`, error);
  });
}

/* ── Shared layout ──────────────────────────────────────────────────────────── */

/** Escapes interpolated values, which Blade's `{{ }}` did automatically. */
export function e(value: string | number | null | undefined): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * The chrome every `resources/views/emails/*.blade.php` repeated inline. Kept
 * visually identical to those templates so the office's mail looks unchanged.
 */
export function layout(options: {
  heading: string;
  body: string;
  /** Header background — approved/returned green, warn amber, danger red. */
  tone?: 'green' | 'warn' | 'danger';
  cta?: { label: string; url: string };
}): string {
  const headerBackground = {
    green: '#0f4d28',
    warn: '#8a5a00',
    danger: '#8a1f1f',
  }[options.tone ?? 'green'];

  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; }
.wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
.header { background: ${headerBackground}; padding: 24px 32px; }
.header h1 { color: #fff; margin: 0; font-size: 20px; }
.body { padding: 28px 32px; font-size: 14px; line-height: 1.6; }
.stage, .slot { display: inline-block; font-size: 15px; font-weight: 700; color: #0f4d28; background: #eef5f0; border: 1px solid #cfe3d6; border-radius: 6px; padding: 8px 16px; margin: 8px 0 16px; }
.cancelled-note { color: #8a1f1f; font-weight: 600; }
.cta { margin-top: 24px; text-align: center; }
.cta a { display: inline-block; background: #167a3a; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
.footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header"><h1>${e(options.heading)}</h1></div>
  <div class="body">
${options.body}
${options.cta ? `    <div class="cta"><a href="${e(options.cta.url)}">${e(options.cta.label)}</a></div>` : ''}
  </div>
  <div class="footer">${e(app.name)} · This is an automated message — please do not reply.</div>
</div>
</body>
</html>`;
}

/** Absolute URL for a citizen-facing link in an email. */
export function absoluteUrl(path: string): string {
  return path.startsWith('http') ? path : `${app.url}${path.startsWith('/') ? path : `/${path}`}`;
}
