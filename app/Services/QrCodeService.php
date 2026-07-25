<?php

namespace App\Services;

use App\Models\Document;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class QrCodeService
{
    /**
     * Unambiguous Crockford-style alphabet: no 0/1/I/L/O/U to avoid
     * transcription mistakes when a citizen types the code by hand.
     * 30 chars ^ 6 positions ≈ 729M combos/day — resists enumeration.
     */
    private const string TRACKING_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int TRACKING_SUFFIX_LENGTH = 6;

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
            if (! extension_loaded('gd')) {
                throw new \RuntimeException('GD extension is required for QR generation.');
            }

            $binaryPng = $this->renderPngWithGd($trackingUrl);

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

    /**
     * Render the QR PNG with plain GD from the raw module matrix. The
     * simple-qrcode/bacon PNG backend requires the imagick extension, which
     * many servers (including ours) don't ship — GD is the project baseline,
     * so drawing the modules ourselves keeps PNG output dependable.
     */
    private function renderPngWithGd(string $content, int $targetSize = 500, int $marginModules = 1): string
    {
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth();
        $total = $modules + ($marginModules * 2);
        $scale = max(1, intdiv($targetSize, $total));
        $size = $total * $scale;

        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $size, $size, $white);

        $rows = $matrix->getArray();
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($rows[$y][$x] === 1) {
                    $px = ($x + $marginModules) * $scale;
                    $py = ($y + $marginModules) * $scale;
                    imagefilledrectangle($image, $px, $py, $px + $scale - 1, $py + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    /**
     * Stamp the document's QR onto a scanned paper image (bottom-right, on a
     * white pad) and save the result as a PNG on the private local disk.
     * Returns the stored relative path, or null when the source isn't a
     * stampable raster image or GD is unavailable. Best-effort by design:
     * the original scan is always kept untouched as its own attachment.
     */
    /**
     * Stamp the QR onto a raster scan of the paper request. By default it lands
     * in the bottom-right corner; pass a normalized $position (['x' => 0..1,
     * 'y' => 0..1] top-left of the QR box as a fraction of the page) to honour a
     * supervisor's chosen placement. Out-of-range values are clamped so the QR
     * always stays fully on the page.
     *
     * A $sizeFraction (QR side as a fraction of the page's short edge, ~0.22 by
     * default) lets a supervisor scale the stamp up or down; it is clamped to a
     * scannable-but-not-overwhelming range.
     *
     * @param  array{x: float, y: float}|null  $position
     */
    public function stampQrOntoImage(string $scanRelativePath, string $qrRelativePath, string $trackingNumber, ?array $position = null, ?float $sizeFraction = null): ?string
    {
        try {
            if (! extension_loaded('gd')) {
                return null;
            }

            $scanBinary = Storage::disk('local')->get($scanRelativePath);
            $qrBinary = Storage::disk('public')->get($qrRelativePath);

            $scan = @imagecreatefromstring($scanBinary);
            $qr = @imagecreatefromstring($qrBinary);

            if ($scan === false || $qr === false) {
                return null;
            }

            $scanW = imagesx($scan);
            $scanH = imagesy($scan);

            // QR sized to ~22% of the page's short edge by default: readable to
            // phone cameras without covering the request's text. A supervisor's
            // chosen size is clamped to a sane 12%–40% band.
            $factor = $sizeFraction !== null ? max(0.12, min(0.40, $sizeFraction)) : 0.22;
            $stampSize = (int) max(96, round(min($scanW, $scanH) * $factor));
            $pad = (int) round($stampSize * 0.06);
            $margin = (int) round($stampSize * 0.10);

            $boxSize = $stampSize + ($pad * 2);

            if ($position !== null) {
                // Supervisor-chosen placement: normalized top-left of the box,
                // clamped so the whole box stays within the page bounds.
                $boxX = (int) round($position['x'] * $scanW);
                $boxY = (int) round($position['y'] * $scanH);
                $boxX = max(0, min($boxX, $scanW - $boxSize));
                $boxY = max(0, min($boxY, $scanH - $boxSize));
            } else {
                $boxX = $scanW - $boxSize - $margin;
                $boxY = $scanH - $boxSize - $margin;
            }

            // White pad behind the QR so it scans even on dark/busy paper.
            $white = imagecolorallocate($scan, 255, 255, 255);
            imagefilledrectangle($scan, $boxX, $boxY, $boxX + $boxSize, $boxY + $boxSize, $white);

            imagecopyresampled(
                $scan, $qr,
                $boxX + $pad, $boxY + $pad, 0, 0,
                $stampSize, $stampSize, imagesx($qr), imagesy($qr),
            );

            ob_start();
            imagepng($scan);
            $stampedBinary = ob_get_clean();

            imagedestroy($scan);
            imagedestroy($qr);

            $stampedPath = "document-attachments/{$trackingNumber}-qr-stamped.png";
            Storage::disk('local')->put($stampedPath, $stampedBinary);

            return $stampedPath;
        } catch (Throwable $e) {
            Log::warning('QR stamping failed', [
                'tracking_number' => $trackingNumber,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
