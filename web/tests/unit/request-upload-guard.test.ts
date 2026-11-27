import { describe, expect, it } from 'vitest';
import { MAX_BYTES } from '@/lib/domain/upload-rules';
import {
  collectFormValues,
  formatFileSizeError,
  validateRequirementUploads,
} from '@/components/citizen/request-form';

describe('RequestForm upload guards', () => {
  it('flags a requirement file exceeding 10 MB without sending to the server', () => {
    const form = new FormData();
    const oversized = new File([new Uint8Array(16)], 'big-letter.pdf', {
      type: 'application/pdf',
    });
    Object.defineProperty(oversized, 'size', { value: MAX_BYTES + 1 });
    form.set('requirements[7]', oversized);

    const errors = validateRequirementUploads(form, [
      { id: 7, label: 'Letter of Request', mandatory: true },
    ]);

    expect(errors['requirements.7']).toContain('10 MB');
    expect(errors['requirements.7']).toBe(formatFileSizeError(oversized));
  });

  it('flags combined requirement uploads exceeding 10 MB', () => {
    const form = new FormData();
    const first = new File([new Uint8Array(16)], 'part1.pdf', { type: 'application/pdf' });
    const second = new File([new Uint8Array(16)], 'part2.pdf', { type: 'application/pdf' });
    Object.defineProperty(first, 'size', { value: Math.floor(MAX_BYTES * 0.6) });
    Object.defineProperty(second, 'size', { value: Math.floor(MAX_BYTES * 0.6) });
    form.set('requirements[1]', first);
    form.set('requirements[2]', second);

    const errors = validateRequirementUploads(form, [
      { id: 1, label: 'Doc 1', mandatory: true },
      { id: 2, label: 'Doc 2', mandatory: false },
    ]);

    expect(errors.uploads).toContain('10 MB');
  });

  it('preserves text field values when client-side validation refuses a submit', () => {
    const form = new FormData();
    form.set('citizen_name', '  Juan Cruz  ');
    form.set('citizen_email', 'juan@example.com');
    form.set('purpose', 'City Plaza Reservation');

    expect(collectFormValues(form)).toEqual({
      citizen_name: 'Juan Cruz',
      citizen_email: 'juan@example.com',
      purpose: 'City Plaza Reservation',
    });
  });
});
