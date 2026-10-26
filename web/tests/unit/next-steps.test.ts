import { describe, expect, it } from 'vitest';
import { currentStepIndex, stepFocus } from '@/app/(staff)/requests/[document]/next-steps';

/** The internal request guide highlights the first step not yet done. */
describe('next steps guide', () => {
  it('makes the first unfinished step the current one', () => {
    expect(currentStepIndex([{ label: 'Received', done: true }, { label: 'Scan', done: false }, { label: 'Press', done: false }])).toBe(1);
  });

  it('moves on as steps are done, and runs past the end when all are', () => {
    expect(currentStepIndex([{ label: 'Received', done: true }, { label: 'Scan', done: true }, { label: 'Press', done: false }])).toBe(2);
    expect(currentStepIndex([{ label: 'Received', done: true }])).toBe(1);
  });

  it('highlights only the current step’s control', () => {
    expect(stepFocus(true)).toBe('ns-focus');
    expect(stepFocus(false)).toBe('');
  });
});
