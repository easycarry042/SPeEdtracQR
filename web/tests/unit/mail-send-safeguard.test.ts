import { afterEach, describe, expect, it, vi } from 'vitest';
import { shouldSkipRecipient, sendMail } from '@/lib/mail/send';

describe('outbound mail placeholder-domain safeguard', () => {
  const originalSkip = process.env.MAIL_SKIP_DOMAINS;
  const originalHost = process.env.MAIL_HOST;

  afterEach(() => {
    if (originalSkip === undefined) {
      delete process.env.MAIL_SKIP_DOMAINS;
    } else {
      process.env.MAIL_SKIP_DOMAINS = originalSkip;
    }

    if (originalHost === undefined) {
      delete process.env.MAIL_HOST;
    } else {
      process.env.MAIL_HOST = originalHost;
    }

    vi.restoreAllMocks();
  });

  it('skips seeded @speedtraqr.com and reserved placeholder domains by default', () => {
    delete process.env.MAIL_SKIP_DOMAINS;

    expect(shouldSkipRecipient('carmela.torres@speedtraqr.com')).toBe(true);
    expect(shouldSkipRecipient('ANA.CRUZ@SPEEDTRAQR.COM')).toBe(true);
    expect(shouldSkipRecipient('"Maria Santos" <maria.santos@speedtraqr.com>')).toBe(true);
    expect(shouldSkipRecipient('sub@mail.speedtraqr.com')).toBe(true);
    expect(shouldSkipRecipient('user@example.com')).toBe(true);
    expect(shouldSkipRecipient('not-an-email')).toBe(true);
  });

  it('allows real citizen and office email domains through', () => {
    delete process.env.MAIL_SKIP_DOMAINS;

    expect(shouldSkipRecipient('citizen@gmail.com')).toBe(false);
    expect(shouldSkipRecipient('juan.delacruz@yahoo.com')).toBe(false);
    expect(shouldSkipRecipient('ctcao@sanpedrocity.gov.ph')).toBe(false);
  });

  it('respects MAIL_SKIP_DOMAINS=none override when domain becomes routable', () => {
    process.env.MAIL_SKIP_DOMAINS = 'none';

    expect(shouldSkipRecipient('carmela.torres@speedtraqr.com')).toBe(false);
  });

  it('returns cleanly without touching SMTP transport for placeholder recipients', async () => {
    delete process.env.MAIL_SKIP_DOMAINS;
    delete process.env.MAIL_HOST;
    const infoSpy = vi.spyOn(console, 'info').mockImplementation(() => {});

    // Would throw MailNotConfiguredError if it attempted to open transport().
    await expect(
      sendMail({
        to: 'carmela.torres@speedtraqr.com',
        subject: 'Accepted: SPD-20261128-ABC123 is now Ongoing',
        html: '<p>Test</p>',
      }),
    ).resolves.toBeUndefined();

    expect(infoSpy).toHaveBeenCalledTimes(1);
  });
});
