import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * Loads `web/.env` into `process.env` before any test module is imported, the
 * way `phpunit.xml` pulled in the Laravel environment. APP_KEY in particular
 * must be the same value Laravel signed with, or every QR-compatibility
 * assertion fails for the wrong reason.
 */
const envPath = fileURLToPath(new URL('../.env', import.meta.url));

for (const line of readFileSync(envPath, 'utf8').split('\n')) {
  const match = /^([A-Z_][A-Z0-9_]*)=(.*)$/.exec(line.trim());

  if (match && process.env[match[1]] === undefined) {
    process.env[match[1]] = match[2].replace(/^"(.*)"$/, '$1');
  }
}
