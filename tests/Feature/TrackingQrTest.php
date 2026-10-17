<?php

namespace Tests\Feature;

use App\Support\ScannedCode;
use App\Support\TrackingQr;
use Tests\TestCase;

/**
 * Request QRs carry a signed, non-URL payload: a generic QR app decodes it to
 * text it cannot open, and only codes this office printed are accepted back.
 */
class TrackingQrTest extends TestCase
{
    public function test_payload_round_trips_to_the_tracking_number(): void
    {
        foreach (['SPD-20260728-K7M9Q2', 'INT-20261016-ABC234'] as $tracking) {
            $this->assertSame($tracking, TrackingQr::trackingNumber(TrackingQr::payload($tracking)));
        }
    }

    public function test_payload_is_not_a_url_and_does_not_show_the_tracking_number(): void
    {
        $payload = TrackingQr::payload('SPD-20260728-K7M9Q2');

        $this->assertStringStartsWith(TrackingQr::PREFIX, $payload);
        $this->assertStringNotContainsString('http', strtolower($payload));
        $this->assertStringNotContainsString('/track', $payload);
        $this->assertStringNotContainsString('SPD-20260728-K7M9Q2', $payload);
        // Upper-case base32 only, so the QR encoder can use compact alphanumeric mode.
        $this->assertMatchesRegularExpression('/^SPDQR1:[A-Z2-7]+$/', $payload);
    }

    public function test_a_tampered_payload_is_rejected(): void
    {
        $payload = TrackingQr::payload('SPD-20260728-K7M9Q2');

        // Every character position, including the last one's spare padding bits.
        for ($i = strlen(TrackingQr::PREFIX); $i < strlen($payload); $i++) {
            $tampered = $payload;
            $tampered[$i] = $payload[$i] === 'A' ? 'B' : 'A';

            $this->assertNull(TrackingQr::trackingNumber($tampered), "Changing character {$i} was accepted.");
        }
    }

    public function test_a_code_signed_with_another_key_is_rejected(): void
    {
        config(['app.qr_signing_key' => 'someone-elses-key']);
        $forged = TrackingQr::payload('SPD-20260728-K7M9Q2');

        config(['app.qr_signing_key' => 'this-offices-key']);

        $this->assertNull(TrackingQr::trackingNumber($forged));
    }

    public function test_malformed_payloads_are_rejected(): void
    {
        foreach (['SPDQR1:', 'SPDQR1:!!!!', 'SPDQR1:AAAA', 'SPDSTAFF:abcdefghijklmnop', 'https://example.com'] as $payload) {
            $this->assertNull(TrackingQr::trackingNumber($payload), "Expected [{$payload}] to be rejected.");
        }
    }

    public function test_scanned_code_accepts_a_signed_payload_and_rejects_a_forged_one(): void
    {
        $payload = TrackingQr::payload('SPD-20260728-K7M9Q2');

        $this->assertSame('SPD-20260728-K7M9Q2', ScannedCode::trackingNumber($payload));
        $this->assertNull(ScannedCode::trackingNumber(substr($payload, 0, -2).'AA'));
    }
}
