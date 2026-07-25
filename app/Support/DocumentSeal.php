<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;

/**
 * Digital seal for issued documents: an HMAC over the document's immutable
 * identity, keyed by APP_KEY. The signature rides in the verification QR/URL
 * so anyone (a bank, another office) can confirm a permit is genuine without
 * an account — and a forged or altered code fails verification.
 */
class DocumentSeal
{
    public static function signature(Document $document): string
    {
        $payload = implode('|', [
            $document->tracking_number,
            $document->document_type,
            (string) $document->created_at?->timestamp,
        ]);

        // 20 hex chars keeps the QR payload compact while leaving ~80 bits of
        // signature strength — far beyond guessable.
        return substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 20);
    }

    public static function verify(Document $document, ?string $signature): bool
    {
        return is_string($signature)
            && $signature !== ''
            && hash_equals(self::signature($document), $signature);
    }

    /** Absolute public verification URL (this is what the seal QR encodes). */
    public static function url(Document $document): string
    {
        return route('verify.show', [
            'trackingNumber' => $document->tracking_number,
            'sig' => self::signature($document),
        ]);
    }
}
