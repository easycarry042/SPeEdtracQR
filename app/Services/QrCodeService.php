<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class QrCodeService
{
    public function generateTrackingNumber(): string
    {
        do {
            $trackingNumber = 'SPD-' . now()->format('Ymd') . '-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (\App\Models\Document::where('tracking_number', $trackingNumber)->exists());

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
