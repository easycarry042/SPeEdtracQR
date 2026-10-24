<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\QrCodeService;
use Illuminate\Console\Command;

/**
 * Re-render every stored request QR PNG with the signed TrackingQr payload.
 *
 * Requests created before signed QRs have a PNG that encodes the public
 * tracking URL, so any phone camera opens it. Those PNGs are what emails attach
 * and what staff reprint from; this swaps each one for a signed code that only
 * this application's scanners accept. Stickers already printed on paper are
 * not affected and keep scanning through the site.
 */
class RegenerateQrCodes extends Command
{
    protected $signature = 'qr:regenerate';

    protected $description = 'Re-render stored request QR images with signed, site-only payloads';

    public function handle(QrCodeService $qrCodeService): int
    {
        $done = 0;
        $failed = 0;

        Document::withTrashed()->whereNotNull('tracking_number')->chunkById(200, function ($documents) use ($qrCodeService, &$done, &$failed) {
            foreach ($documents as $document) {
                $result = $qrCodeService->generateAndStore($document->tracking_number);

                if (! $result['success']) {
                    $failed++;
                    $this->warn("{$document->tracking_number}: {$result['error']}");

                    continue;
                }

                if ($document->qr_code_path !== $result['relative_path']) {
                    $document->forceFill(['qr_code_path' => $result['relative_path']])->saveQuietly();
                }

                $done++;
            }
        });

        $this->info("Regenerated {$done} QR code(s)".($failed ? ", {$failed} failed." : '.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
