/**
 * The receipt cookie's name, on its own so the Edge middleware can clear it.
 *
 * `citizen-receipt.ts` imports `next/headers`, which the Edge runtime cannot
 * load — importing the constant from there would drag that in and break the
 * middleware bundle.
 */
export const RECEIPT_COOKIE_NAME = 'speedtraqr_receipt';

export const RECEIPT_LIFETIME_MINUTES = 15;
