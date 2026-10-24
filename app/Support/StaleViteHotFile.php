<?php

namespace App\Support;

/**
 * Detects a `public/hot` file left behind by a Vite dev server that is no
 * longer running.
 *
 * `npm run dev` writes the hot file on start and deletes it on a clean exit,
 * but closing the terminal or killing the process on Windows skips that
 * cleanup. Laravel then keeps pointing every page at a dead dev server, and
 * the whole site renders as unstyled HTML until someone deletes the file by
 * hand. Callers use this to fall back to the built assets instead.
 */
class StaleViteHotFile
{
    /**
     * Whether the hot file at the given path names a dev server that is not
     * accepting connections. A missing or unreadable file is not "stale".
     */
    public static function isStale(string $hotFile, float $timeoutSeconds = 0.25): bool
    {
        if (! is_file($hotFile)) {
            return false;
        }

        $url = parse_url(trim((string) file_get_contents($hotFile)));

        if (! isset($url['host'])) {
            return true;
        }

        $port = $url['port'] ?? (($url['scheme'] ?? 'http') === 'https' ? 443 : 80);

        // parse_url keeps IPv6 hosts bracketed ("[::1]"), which is exactly the
        // form a tcp:// address needs.
        // A refused connection is the expected answer here, not an error, so
        // keep its warning out of the error handler and the logs.
        set_error_handler(fn (): bool => true);

        try {
            $socket = stream_socket_client("tcp://{$url['host']}:{$port}", $errno, $errstr, $timeoutSeconds);
        } finally {
            restore_error_handler();
        }

        if ($socket === false) {
            return true;
        }

        fclose($socket);

        return false;
    }
}
