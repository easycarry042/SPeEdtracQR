<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class QrCodeService
{
    /**
     * Unambiguous Crockford-style alphabet: no 0/1/I/L/O/U to avoid
     * transcription mistakes when a citizen types the code by hand.
     * 30 chars ^ 6 positions ≈ 729M combos/day — resists enumeration.
     */
    private const TRACKING_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const TRACKING_SUFFIX_LENGTH = 6;

    public function generateTrackingNumber(): string
    {
        $alphabetMax = strlen(self::TRACKING_ALPHABET) - 1;

        do {
            $suffix = '';
            for ($i = 0; $i < self::TRACKING_SUFFIX_LENGTH; $i++) {
                $suffix .= self::TRACKING_ALPHABET[random_int(0, $alphabetMax)];
            }
            $trackingNumber = 'SPD-'.now()->format('Ymd').'-'.$suffix;
        } while (Document::where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    public function generateAndStore(string $trackingNumber, string $trackingUrl): array
    {
        try {
            if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
                throw new \RuntimeException('GD or Imagick extension is required for QR generation.');
            }

            $binaryPng = QrCode::format('png')
                ->size(500)
                ->margin(1)
                ->generate($trackingUrl);

            $relativePath = "qrcodes/{$trackingNumber}.png";
            Storage::disk('public')->put($relativePath, $binaryPng);

            return [
                'success' => true,
                'relative_path' => $relativePath,
                'public_url' => Storage::url($relativePath),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('QR generation failed', [
                'tracking_number' => $trackingNumber,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'relative_path' => null,
                'public_url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
