<?php

namespace App\Support;

class PublicStorage
{
    /** Relative URL so images work on any host/port (127.0.0.1:8000, not localhost). */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
