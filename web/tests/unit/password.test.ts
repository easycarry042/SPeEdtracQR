import { describe, expect, it } from 'vitest';
import { hashPassword, verifyPassword } from '@/lib/auth/password';

/**
 * The cutover promise: every existing account signs in with its current
 * password, with no reset.
 *
 * The hash below was produced by PHP's `password_hash(..., PASSWORD_BCRYPT,
 * ['cost' => 12])` — exactly what Laravel's `Hash::make` writes, `$2y$`
 * identifier and all. If this ever fails, nobody can sign in after the cutover.
 */
const LARAVEL_HASH = '$2y$12$15awFls6Ky3T2CiEgzB5Xuv7Q/gCgrOG4U2XCz.VxgYvzaNRhkvQi';
const PLAINTEXT = 'correct-horse-battery';

describe('password compatibility with Laravel', () => {
  it('verifies a password against a hash Laravel wrote', async () => {
    expect(LARAVEL_HASH.startsWith('$2y$12$')).toBe(true);
    await expect(verifyPassword(PLAINTEXT, LARAVEL_HASH)).resolves.toBe(true);
  });

  it('rejects the wrong password against that same hash', async () => {
    await expect(verifyPassword('wrong-password', LARAVEL_HASH)).resolves.toBe(false);
    await expect(verifyPassword('', LARAVEL_HASH)).resolves.toBe(false);
  });

  it('treats an empty or missing hash as a failure, never a pass', async () => {
    // A user row with no password must not authenticate anyone.
    await expect(verifyPassword(PLAINTEXT, '')).resolves.toBe(false);
  });

  it('writes hashes at Laravel’s cost, so the PHP side can still read them', async () => {
    const hash = await hashPassword(PLAINTEXT);

    expect(hash).toMatch(/^\$2[aby]\$12\$/);
    await expect(verifyPassword(PLAINTEXT, hash)).resolves.toBe(true);
  });
});
