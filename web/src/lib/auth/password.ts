import bcrypt from 'bcryptjs';

/**
 * Laravel's `Hash::make` writes bcrypt hashes with the `$2y$` identifier.
 * bcryptjs accepts `$2y$` alongside `$2a$`/`$2b$` (they differ only in the
 * prefix, not the algorithm), so stored passwords verify unchanged and nobody
 * has to reset anything at cutover.
 */
export function verifyPassword(plain: string, hash: string): Promise<boolean> {
  if (!hash) {
    return Promise.resolve(false);
  }

  return bcrypt.compare(plain, hash);
}

/** Laravel's default bcrypt cost is 12; new hashes stay readable by Laravel. */
export function hashPassword(plain: string): Promise<string> {
  return bcrypt.hash(plain, 12);
}
