import { db } from '@/lib/db';

/**
 * Port of `App\Support\Ai\RequirementsAnswer`: "What do I need for a Business
 * Permit?" answered from the public services catalogue.
 *
 * Shared by both of Speedy's modes: the general one needs the service named in
 * the question; the one scoped to a request falls back to that request's type.
 */
export type Requirements = {
  service: string;
  isCurrent: boolean;
  items: Array<{ label: string; mandatory: boolean }>;
};

/**
 * Whether the question asks what to prepare. Not when it is about collecting a
 * finished document: "what do I bring for pickup?" is answered with the QR code,
 * not the filing checklist.
 */
export function asks(question: string): boolean {
  const q = question.toLowerCase();

  if (['pickup', 'pick up', 'pick-up', 'claim', 'collect', 'release'].some((word) => q.includes(word))) {
    return false;
  }

  return [
    'requirement',
    'require',
    'what do i need',
    'need to bring',
    'documents needed',
    'what to bring',
    'bring',
    'checklist',
    'prepare',
    'documents for',
  ].some((phrase) => q.includes(phrase));
}

/**
 * The checklist the question is about: a service named in it (longest name
 * first, so "Mayor's Permit" beats a bare "Permit"), else `currentType`.
 */
export async function resolve(question: string, currentType: string | null = null): Promise<Requirements | null> {
  const types = await db.request_types.findMany({
    select: {
      name: true,
      request_type_requirements: {
        select: { label: true, is_mandatory: true },
        orderBy: [{ sort_order: 'asc' }, { id: 'asc' }],
      },
    },
  });

  if (types.length === 0) {
    return null;
  }

  const q = question.toLowerCase();
  const byLength = [...types].sort((a, b) => b.name.length - a.name.length);
  const match =
    byLength.find((type) => q.includes(type.name.toLowerCase())) ??
    (currentType === null
      ? undefined
      : types.find((type) => type.name.toLowerCase() === currentType.toLowerCase()));

  if (!match) {
    return null;
  }

  return {
    service: match.name,
    isCurrent: currentType !== null && match.name.toLowerCase() === currentType.toLowerCase(),
    items: match.request_type_requirements.map((item) => ({ label: item.label, mandatory: item.is_mandatory })),
  };
}

export function text(requirements: Requirements): string {
  if (requirements.items.length === 0) {
    return `I don't have a requirements checklist on file for ${requirements.service}. Please contact the handling office to confirm what to prepare.`;
  }

  const mandatory = requirements.items.filter((item) => item.mandatory);
  const optional = requirements.items.filter((item) => !item.mandatory);
  const lead = requirements.isCurrent
    ? `For your ${requirements.service}, you'll typically need:`
    : `For a ${requirements.service}, you'll typically need:`;
  const bullet = (item: { label: string }): string => `• ${item.label}`;

  let answer = `${lead}\n${(mandatory.length > 0 ? mandatory : requirements.items).map(bullet).join('\n')}`;

  if (mandatory.length > 0 && optional.length > 0) {
    answer += `\nOptional / if applicable:\n${optional.map(bullet).join('\n')}`;
  }

  return `${answer}\nRequirements can vary by case — the handling office confirms the final list.`;
}
