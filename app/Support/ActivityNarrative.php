<?php

namespace App\Support;

/**
 * Turns one activity-log line into something a person can read at a glance:
 * a tone (what colour the entry takes), a headline, and the detail beneath it.
 *
 * The log is written as prose by the code that records it — "Returned for
 * revision at Municipal Engineering Office by Arnel Domingo: Please attach the
 * approved equipment list before resubmitting." Rendered whole, every entry
 * looks the same and the one that matters is lost in the column. Split at its
 * own punctuation, the first half becomes the headline and the rest the
 * explanation, and the tone says which entries deserve a second look.
 */
final class ActivityNarrative
{
    /** Trouble: work came back, was refused, or ran past its own deadline. */
    private const RED = [
        'returned for revision',
        'rejected',
        'denied',
        'overdue',
        'breach',
        'without a qr scan',
        'cancelled',
        'canceled',
    ];

    /** Watch: not wrong yet, but waiting on someone. */
    private const AMBER = [
        'needs revision',
        'on hold',
        'held',
        'nudged',
        'chased',
        're-uploaded',
        'reminder',
    ];

    /** Finished well. */
    private const GREEN = [
        'completed',
        'approved',
        'released',
        'verified',
        'signed',
    ];

    /**
     * Spatie logs a model event as the bare verb. "updated" on its own tells a
     * reader nothing, so it is given a name a person would use.
     */
    private const MODEL_EVENTS = [
        'created' => 'Record created',
        'updated' => 'Record updated',
        'deleted' => 'Record deleted',
        'restored' => 'Record restored',
    ];

    /**
     * @return array{tone: string, headline: string, detail: ?string}
     */
    public static function for(string $description): array
    {
        $text = trim($description);
        $text = self::MODEL_EVENTS[mb_strtolower($text)] ?? $text;

        [$headline, $detail] = self::split($text);

        return [
            'tone' => self::tone($text),
            'headline' => $headline,
            'detail' => $detail,
        ];
    }

    /**
     * Severity wins over outcome: "Reviewed requirement X: Rejected" is a red
     * entry even though "reviewed" on its own is routine, and a hold that has
     * run overdue is red rather than amber.
     */
    public static function tone(string $description): string
    {
        $haystack = mb_strtolower($description);

        // Checked worst-first, so a rejected review reads red rather than green.
        foreach ([['red', self::RED], ['amber', self::AMBER], ['green', self::GREEN]] as [$tone, $needles]) {
            foreach ($needles as $needle) {
                // Whole words only: "Assigned to Paolo" contains "signed", and
                // matching on the substring turned a routine handover green.
                if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $haystack) === 1) {
                    return $tone;
                }
            }
        }

        return 'blue';
    }

    /**
     * Cut the line at the first separator the log itself uses — an em dash, or
     * a colon that is not part of a quoted requirement name. Everything after
     * it is the explanation.
     *
     * @return array{0: string, 1: ?string}
     */
    private static function split(string $text): array
    {
        foreach ([' — ', ' – '] as $dash) {
            if (($at = mb_strpos($text, $dash)) !== false) {
                return self::pair(mb_substr($text, 0, $at), mb_substr($text, $at + mb_strlen($dash)));
            }
        }

        // A colon splits only after any quoted name has closed, so
        // 'Reviewed requirement "Cedula": Rejected' keeps the name in the
        // headline rather than breaking inside the quotes.
        $afterQuote = mb_strrpos($text, '"');
        $from = $afterQuote === false ? 0 : $afterQuote + 1;

        if (($at = mb_strpos($text, ': ', $from)) !== false) {
            return self::pair(mb_substr($text, 0, $at), mb_substr($text, $at + 2));
        }

        return [$text, null];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function pair(string $headline, string $detail): array
    {
        $headline = trim($headline);
        $detail = trim($detail);

        // "reason: Sticker torn off…" — the label is noise once the detail sits
        // on its own line under the headline.
        $detail = preg_replace('/^reason:\s*/i', '', $detail) ?? $detail;

        return [$headline, $detail === '' ? null : ucfirst($detail)];
    }
}
