# Product

## Register

product

## Users

Two distinct groups, one system:

- **Citizens** — submit documents (Business Permit, Cedula, etc.) and track their status. No account, no login, no app install. Often on a basic phone browser, possibly older or unfamiliar with government digital services. Their only interaction is: submit once, then check a tracking link occasionally until it's done.
- **Staff / supervisors / admins** — desk-based government office employees. Create documents, scan them in/out or advance status, manage assignments, watch SLA timers, run reports. Trained, repeat users on a desk PC (sometimes phone for scanning) — can handle more density and fewer training wheels than citizens.

## Product Purpose

SPeEdtracQR replaces the opacity of "come back next week and ask the counter" with a QR-coded tracking number a citizen can check anytime. Internally, it gives staff a shared, auditable system of record for where every document is, who has it, and whether it's at risk of breaching its SLA. Success looks like: citizens never have to call or visit to ask "where's my permit," and staff never lose track of a document or miss an SLA breach silently.

## Brand Personality

Trustworthy, calm, official — reads like a well-run government service counter, not a startup. Reassuring over exciting; status changes and SLA warnings should inform, not alarm. The existing "Civic Record" visual system (deep municipal green, white paper backgrounds, stamped-logbook mono type for tracking codes, restrained brass accent) is the correct instinct for this: authority conveyed through material and precision, not decoration.

## Anti-references

No hard anti-references named, but two failure modes to stay clear of by default, since this sits between them:
- **Generic SaaS dashboard** — gradient hero metrics, card-grid-everything, cheerful startup illustrations. Wrong register; this isn't selling anything.
- **Old-school government portal** — dense, cold, Times-New-Roman, no visual hierarchy. The Civic Record system already exists specifically to avoid this trap; don't regress toward it in the name of "looking official."

## Design Principles

1. **Calm authority over urgency.** Status and SLA signals inform; only genuine overdue/breach states escalate visually. Everything else stays measured — this is a records office, not a trading floor.
2. **Two audiences, one system.** Citizen-facing surfaces stay radically simple — no jargon, no login, plain-language status labels, generous touch targets. Staff surfaces can be denser and faster, since they're trained repeat users.
3. **Transparency builds trust.** A citizen has no other visibility into their document's progress besides this page. Show real state plainly — actual timestamps, actual stage names, no hidden logic dressed up as a spinner.
4. **Accessible by default, not as a checklist.** Public pages must assume older devices, unreliable connections, and non-technical or elderly users. WCAG 2.1 AA is the floor.
5. **One visual language at every scale.** The same civic components (panels, pills, the routing stepper) should hold true from a single public tracking page to a dense admin registry table — consistency is itself part of what makes the system feel trustworthy.

## Accessibility & Inclusion

WCAG 2.1 AA baseline across the app. Extra weight on the public-facing pages specifically (tracking page, request form): assume a citizen may be on an older Android phone, a slow connection, and have little patience for icon-only affordances — label things in words, keep touch targets generous, and never rely on color alone to convey status (the existing `.pill` component already pairs color with a text label, which is the right pattern to keep).
