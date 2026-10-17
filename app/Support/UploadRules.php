<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One definition of what may be attached to a request, shared by every upload
 * path (public form, citizen re-upload, staff attachments, internal paper scan).
 *
 * Offices attach far more than photos: PDFs and Word files for letters and
 * clearances, spreadsheets for budgets and participant lists, plain text for
 * exports — and HEIC, which is what an iPhone camera produces by default and
 * was previously rejected as "not an allowed file".
 */
class UploadRules
{
    /** Photos and scans. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    /** Text documents. */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt'];

    /** Spreadsheets (budgets, participant lists, inventories). */
    public const SHEET_EXTENSIONS = ['xls', 'xlsx', 'csv', 'ods'];

    /** Max upload size in kilobytes (10 MB). */
    public const MAX_KILOBYTES = 10240;

    /**
     * Every accepted extension.
     *
     * @return list<string>
     */
    public static function extensions(): array
    {
        return [
            ...self::IMAGE_EXTENSIONS,
            ...self::DOCUMENT_EXTENSIONS,
            ...self::SHEET_EXTENSIONS,
        ];
    }

    /**
     * Validation rules for one uploaded file.
     *
     * @return list<string>
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::extensions()),
            'max:'.self::MAX_KILOBYTES,
        ];
    }

    /** The `accept` attribute for a file input, so the picker matches the rules. */
    public static function accept(): string
    {
        return collect(self::extensions())
            ->map(fn (string $extension): string => '.'.$extension)
            ->implode(',');
    }

    /** Whether a stored path is a browser-renderable image (thumbnails, previews). */
    public static function isImage(?string $path): bool
    {
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        // HEIC/HEIF are images but most browsers cannot render them inline, so
        // they are treated as files for preview purposes.
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    /** Human-readable summary for upload hints ("PDF, Word, Excel, or images"). */
    public static function hint(): string
    {
        return 'PDF, Word, Excel, or image files, up to '.(int) (self::MAX_KILOBYTES / 1024).' MB each';
    }
}
