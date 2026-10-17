<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Partly hides a requester's email and phone number on the public tracking
 * page, which anyone holding the tracking number or QR code can open.
 *
 * Shown in full, they were readable by whoever saw a forwarded link or a
 * photographed slip — and they are the very details CitizenThreadAccess asks
 * for before letting someone message the office as the requester, so printing
 * them on the same page made that check pointless. Masked, the requester can
 * still recognise their own details at a glance.
 *
 * The hidden part is always the same length, so the mask never gives away
 * how long the real value is.
 */
final class ContactMask
{
    private const HIDDEN = '•••';

    /** juan.cruz@gmail.com → j•••@gmail.com */
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return self::HIDDEN;
        }

        return mb_substr($email, 0, 1).self::HIDDEN.substr($email, $at);
    }

    /** 09171234567 → •••4567 */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return self::HIDDEN.(strlen($digits) > 4 ? substr($digits, -4) : '');
    }
}
