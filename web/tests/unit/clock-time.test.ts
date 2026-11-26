import { describe, expect, it } from 'vitest';
import {
  addMinutesClamped,
  degreesToHour12,
  degreesToMinute,
  formatTime12,
  parseTime24,
  pointerToClockDegrees,
  timeToMinutes,
  toTime24,
} from '@/lib/client/clock-time';

describe('clock-time helpers', () => {
  it('parses 24-hour HH:MM into 12-hour clock components', () => {
    expect(parseTime24('00:00')).toEqual({ hour12: 12, minute: 0, meridiem: 'AM' });
    expect(parseTime24('09:05')).toEqual({ hour12: 9, minute: 5, meridiem: 'AM' });
    expect(parseTime24('12:00')).toEqual({ hour12: 12, minute: 0, meridiem: 'PM' });
    expect(parseTime24('13:45')).toEqual({ hour12: 1, minute: 45, meridiem: 'PM' });
    expect(parseTime24('23:59')).toEqual({ hour12: 11, minute: 59, meridiem: 'PM' });
    expect(parseTime24('25:00')).toBeNull();
    expect(parseTime24('')).toBeNull();
  });

  it('converts 12-hour components back to canonical 24-hour HH:MM', () => {
    expect(toTime24(12, 0, 'AM')).toBe('00:00');
    expect(toTime24(9, 0, 'AM')).toBe('09:00');
    expect(toTime24(12, 30, 'PM')).toBe('12:30');
    expect(toTime24(2, 15, 'PM')).toBe('14:15');
    expect(toTime24(11, 55, 'PM')).toBe('23:55');
  });

  it('formats 24-hour HH:MM into readable 12-hour labels', () => {
    expect(formatTime12('00:00')).toBe('12:00 AM');
    expect(formatTime12('09:00')).toBe('9:00 AM');
    expect(formatTime12('12:05')).toBe('12:05 PM');
    expect(formatTime12('17:30')).toBe('5:30 PM');
    expect(formatTime12('')).toBe('');
  });

  it('advances minutes safely and clamps at 23:59', () => {
    expect(timeToMinutes('09:30')).toBe(570);
    expect(addMinutesClamped('09:00', 60)).toBe('10:00');
    expect(addMinutesClamped('23:30', 60)).toBe('23:59');
  });

  it('maps pointer offsets on the dial to clock angles, hours, and minutes', () => {
    // 12 o'clock (dx=0, dy=-100)
    expect(pointerToClockDegrees(0, -100)).toBe(0);
    expect(degreesToHour12(0)).toBe(12);
    expect(degreesToMinute(0)).toBe(0);

    // 3 o'clock (dx=100, dy=0)
    expect(pointerToClockDegrees(100, 0)).toBe(90);
    expect(degreesToHour12(90)).toBe(3);
    expect(degreesToMinute(90)).toBe(15);

    // 6 o'clock (dx=0, dy=100)
    expect(pointerToClockDegrees(0, 100)).toBe(180);
    expect(degreesToHour12(180)).toBe(6);
    expect(degreesToMinute(180)).toBe(30);

    // 9 o'clock (dx=-100, dy=0)
    expect(pointerToClockDegrees(-100, 0)).toBe(270);
    expect(degreesToHour12(270)).toBe(9);
    expect(degreesToMinute(270)).toBe(45);
  });
});
