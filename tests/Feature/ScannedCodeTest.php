<?php

namespace Tests\Feature;

use App\Support\ScannedCode;
use Tests\TestCase;

/**
 * Only codes this office issued may drive an action. A QR from anywhere else is
 * rejected even when its payload mentions something tracking-number-shaped.
 */
class ScannedCodeTest extends TestCase
{
    public function test_it_accepts_a_bare_tracking_number(): void
    {
        $this->assertSame('SPD-20260728-K7M9Q2', ScannedCode::trackingNumber('SPD-20260728-K7M9Q2'));
        $this->assertSame('SPD-20260728-K7M9Q2', ScannedCode::trackingNumber(' spd-20260728-k7m9q2 '));
    }

    public function test_it_accepts_internal_request_codes(): void
    {
        // INT- codes used to be rejected, so scanning an internal request's own
        // sticker looked like "the QR won't read".
        $this->assertSame('INT-20260728-A1B2C3', ScannedCode::trackingNumber('INT-20260728-A1B2C3'));
    }

    public function test_it_accepts_our_own_tracking_and_verify_urls(): void
    {
        $host = config('app.url');

        $this->assertSame('SPD-20260728-K7M9Q2', ScannedCode::trackingNumber("{$host}/track/SPD-20260728-K7M9Q2"));
        $this->assertSame('SPD-20260728-K7M9Q2', ScannedCode::trackingNumber("{$host}/verify/SPD-20260728-K7M9Q2"));
        $this->assertSame('SPD-20260728-K7M9Q2', ScannedCode::trackingNumber('/track/SPD-20260728-K7M9Q2'));
    }

    public function test_it_rejects_a_url_on_another_host(): void
    {
        // A convincing look-alike from somebody else's site is still foreign.
        $this->assertNull(ScannedCode::trackingNumber('https://evil.example.com/track/SPD-20260728-K7M9Q2'));
    }

    public function test_it_rejects_codes_this_system_never_issued(): void
    {
        foreach ([
            'WIFI:S=OfficeGuest;T=WPA;P=hunter2;;',
            'https://example.com/pay/12345',
            'Hello world',
            'Reference SPD-20260728-K7M9Q2 please call the office',
            'SPD-2026-728-K7M9Q2',
            'SPD-20260728-K7M9',
            '',
            '   ',
        ] as $payload) {
            $this->assertNull(
                ScannedCode::trackingNumber($payload),
                "Expected [{$payload}] to be rejected as a foreign code.",
            );
        }
    }

    public function test_is_ours_mirrors_extraction(): void
    {
        $this->assertTrue(ScannedCode::isOurs('SPD-20260728-K7M9Q2'));
        $this->assertFalse(ScannedCode::isOurs('https://example.com/'));
    }
}
