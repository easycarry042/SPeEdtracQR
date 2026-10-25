import { describe, expect, it } from 'vitest';
import type { ActivityRow } from '@/lib/activity';
import { describe as describeLine, isEmptyUpdate } from '@/lib/domain/activity-line';
import { followablePath } from '@/lib/domain/notifications';

/**
 * Port of `ActivityLineTest`: audit rows read as what happened, by tracking
 * number — not "updated (Document #19)".
 */
function row(overrides: Partial<ActivityRow>): ActivityRow {
  return {
    id: 1,
    description: 'updated',
    event: 'updated',
    subjectType: 'App\Models\Document',
    subjectId: 19,
    causerId: 5,
    causerName: 'Ana',
    properties: null,
    createdAt: null,
    ...overrides,
  };
}

const lookups = {
  userNames: new Map([[7, 'Ben Staff']]),
  subjects: new Map([['App\Models\Document#19', 'SPD-20261023-ABC234']]),
};

describe('ActivityLine', () => {
  it('words a status change with the stage and the tracking number', () => {
    expect(describeLine(row({ properties: { attributes: { status: 'in_review' } } }), lookups)).toEqual({
      action: 'Status Updated',
      detail: '→ Pending Approval',
      subject: 'SPD-20261023-ABC234',
    });
  });

  it('names the staff member an assignment went to', () => {
    expect(describeLine(row({ properties: { attributes: { assigned_to: 7 } } }), lookups).detail).toBe('→ Ben Staff');
    expect(describeLine(row({ properties: { attributes: { assigned_to: null } } }), lookups).detail).toBe('→ Unassigned');
  });

  it('lists the fields of a plain edit', () => {
    expect(describeLine(row({ properties: { attributes: { citizen_name: 'X', claim_date: 'Y' } } }), lookups)).toMatchObject({
      action: 'Details Edited',
      detail: 'citizen name, claim date',
    });
  });

  it('falls back to the description for a custom line', () => {
    expect(describeLine(row({ event: null, description: 'opened requirement "Cedula"' }), lookups).action).toBe(
      'Opened requirement "Cedula"',
    );
  });

  it('spots saves that changed nothing', () => {
    expect(isEmptyUpdate(row({ properties: { attributes: [] } }))).toBe(true);
    expect(isEmptyUpdate(row({ properties: { attributes: {} } }))).toBe(true);
    expect(isEmptyUpdate(row({ properties: { attributes: { status: 'x' } } }))).toBe(false);
    expect(isEmptyUpdate(row({ event: 'created' }))).toBe(false);
  });
});

describe('followablePath', () => {
  it('keeps the path of an old absolute URL, dropping its host', () => {
    expect(followablePath('http://127.0.0.1:8000/track/SPD-1?view=citizen')).toBe('/track/SPD-1?view=citizen');
  });

  it('cannot be turned into an open redirect', () => {
    expect(followablePath('https://evil.example/phish')).toBe('/phish');
    expect(followablePath('//evil.example/phish')).toBe('/phish');
    expect(followablePath(null)).toBeNull();
  });
});
