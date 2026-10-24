import Link from 'next/link';

/**
 * The breadcrumb trail (port of the `x-breadcrumbs` Blade component).
 *
 * Pass the trail in order; the last entry is the current page and is never a
 * link. The setup forms are the pages that need it: they are reached from a
 * list and have no other way back out.
 */
export type Crumb = {
  label: string;
  url?: string;
};

export function Breadcrumbs({ items }: { items: Crumb[] }) {
  return (
    <nav aria-label="Breadcrumb" className="cr-crumbs">
      <ol>
        {items.map((item, index) => {
          const last = index === items.length - 1;

          return (
            <li key={`${item.label}-${index}`}>
              {item.url && !last ? (
                <>
                  <Link href={item.url}>{item.label}</Link>
                  <svg aria-hidden="true" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                  </svg>
                </>
              ) : (
                <span aria-current="page">{item.label}</span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
