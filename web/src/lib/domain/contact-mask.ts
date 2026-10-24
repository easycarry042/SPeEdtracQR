/**
 * Partly hides a requester's email and phone number on the public tracking
 * page, which anyone holding the tracking number or QR code can open.
 *
 * Shown in full they were readable by whoever saw a forwarded link or a
 * photographed slip — and they are the very details `CitizenThreadAccess` asks
 * for before letting someone message the office as the requester, so printing
 * them on the same page made that check pointless. Masked, the requester can
 * still recognise their own details at a glance.
 *
 * The hidden part is always the same length, so the mask never gives away how
 * long the real value is.
 */
const HIDDEN = '•••';

/** `juan.cruz@gmail.com` → `j•••@gmail.com` */
export function maskEmail(email: string | null | undefined): string | null {
  const trimmed = (email ?? '').trim();

  if (trimmed === '') {
    return null;
  }

  const at = trimmed.lastIndexOf('@');

  if (at <= 0) {
    return HIDDEN;
  }

  return trimmed.slice(0, 1) + HIDDEN + trimmed.slice(at);
}

/** `09171234567` → `•••4567` */
export function maskPhone(phone: string | null | undefined): string | null {
  const digits = (phone ?? '').replace(/\D+/g, '');

  if (digits === '') {
    return null;
  }

  return HIDDEN + (digits.length > 4 ? digits.slice(-4) : '');
}
