/** 1…n up to six pages, then first / around-current / last with gaps. */
export function pageList(current: number, last: number): Array<number | 'gap'> {
  if (last <= 6) {
    return Array.from({ length: last }, (_, index) => index + 1);
  }

  if (current > 5) {
    return ([1, 'gap', current - 1, current, current + 1, 'gap', last] as Array<number | 'gap'>).filter(
      (entry) => entry === 'gap' || (entry >= 1 && entry <= last),
    );
  }

  return [1, 2, 3, 4, 5, 'gap', last];
}
