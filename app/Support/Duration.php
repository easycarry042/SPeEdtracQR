<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Elapsed time the way a person reads it: "45m", "5h 18m", "20d 2h".
 *
 * The command center used to print raw units — "0d" for a request finished in
 * an hour (days rounded down to nothing), "+482.4h" for one twenty days late —
 * so the smallest and the largest numbers on the page were the hardest to read.
 */
final class Duration
{
    /** A span given in hours. Negative spans read as their size. */
    public static function hours(?float $hours): string
    {
        if ($hours === null) {
            return '—';
        }

        $minutes = (int) round(abs($hours) * 60);

        if ($minutes < 60) {
            return $minutes.'m';
        }

        if ($minutes < 24 * 60) {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return $m === 0 ? "{$h}h" : "{$h}h {$m}m";
        }

        $totalHours = (int) round($minutes / 60);
        $d = intdiv($totalHours, 24);
        $h = $totalHours % 24;

        return $h === 0 ? "{$d}d" : "{$d}d {$h}h";
    }

    /** A span given in days (the unit the analytics store averages in). */
    public static function days(?float $days): string
    {
        return $days === null ? '—' : self::hours($days * 24);
    }
}
