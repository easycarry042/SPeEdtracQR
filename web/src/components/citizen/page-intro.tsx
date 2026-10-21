/**
 * The opening of a citizen page: eyebrow pill, display title and one lead
 * paragraph — the landing page hero's stack, so every page after it starts the
 * same way. Styles live in `globals.css` ("Citizen surface set").
 */
export function PageIntro({
  eyebrow,
  title,
  className = '',
  children,
}: {
  eyebrow?: string;
  title: string;
  className?: string;
  children?: React.ReactNode;
}) {
  return (
    <div className={`max-w-[760px] ${className}`.trim()}>
      {eyebrow ? <p className="cz-eyebrow">{eyebrow}</p> : null}

      <h1 className={`cz-title${eyebrow ? ' mt-4' : ''}`}>{title}</h1>

      {children ? <p className="cz-lead mt-3">{children}</p> : null}
    </div>
  );
}
