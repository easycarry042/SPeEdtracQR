import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requirePermission } from '@/lib/auth/actor';
import { calendar } from '@/lib/domain/booking-calendar';
import { BookingCalendar } from './booking-calendar';

export const metadata: Metadata = { title: 'Booking' };

export const dynamic = 'force-dynamic';

/**
 * Port of `bookings/index.blade.php` — a month calendar beside two side
 * cards: what is booked on the selected day, and what is coming up. Approve,
 * reschedule and cancel run the Resources page's lending rules.
 */
export default async function BookingsPage() {
  await requirePermission('manage bookings');

  return (
    <AuthenticatedPage title="Booking">
      <BookingCalendar data={await calendar()} />
    </AuthenticatedPage>
  );
}
