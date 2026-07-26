---
name: SPeEdtracQR
description: A government document-tracking system for citizens and office staff, styled as "Civic Record" — a records office you can trust.
colors:
  paper: "#ffffff"
  hairline: "#e6ece8"
  hairline-strong: "#cdd9d2"
  green-deep: "#0f4d28"
  green: "#167a3a"
  green-bright: "#2a9d4f"
  green-wash: "#eef5f0"
  ink: "#16211b"
  ink-soft: "#5b6b62"
  brass: "#c79a3e"
  amber: "#8a5a08"
  amber-wash: "#fbeeda"
  red: "#8f2a2a"
  red-wash: "#fbe9e9"
typography:
  headline:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.25rem, 2vw, 2.25rem)"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13.5px"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "normal"
  body:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "0.02em"
  mono:
    fontFamily: "ui-monospace, SF Mono, JetBrains Mono, Menlo, Consolas, monospace"
    fontSize: "13px"
    fontWeight: 500
    lineHeight: 1.3
    letterSpacing: "0.01em"
rounded:
  sm: "6px"
  md: "10px"
  lg: "14px"
  pill: "999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
components:
  panel:
    backgroundColor: "{colors.paper}"
    rounded: "{rounded.md}"
  panel-header:
    backgroundColor: "#fbfdfc"
    textColor: "{colors.green-deep}"
    padding: "11px 14px"
  pill-green:
    backgroundColor: "{colors.green-wash}"
    textColor: "#0f5c2e"
    rounded: "{rounded.pill}"
    padding: "3px 9px"
  pill-amber:
    backgroundColor: "{colors.amber-wash}"
    textColor: "{colors.amber}"
    rounded: "{rounded.pill}"
    padding: "3px 9px"
  pill-red:
    backgroundColor: "{colors.red-wash}"
    textColor: "{colors.red}"
    rounded: "{rounded.pill}"
    padding: "3px 9px"
  id-chip:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    typography: "{typography.mono}"
    padding: "2px 9px"
  button-primary:
    backgroundColor: "{colors.green}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "7px 12px"
  button-primary-hover:
    backgroundColor: "#13682f"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.green}"
    padding: "6px 8px"
---

# Design System: SPeEdtracQR — Civic Record

## 1. Overview

**Creative North Star: "The Civic Record"**

Imagine the counter of a well-run municipal records office: heavy paper, a green ledger, a rubber stamp, a clerk who knows exactly where your file is. That's the feeling this system is after — not a startup dashboard, not a 2008-era government portal with Times New Roman and a seal clip-art. White paper backgrounds and a true, saturated municipal green (never a pale mint) carry the authority; a mono-spaced "stamp" treatment on tracking numbers and timestamps carries the precision. Deliberately flat — panels sit on 1px hairline borders, not shadows, the way a printed form sits flat on a desk.

The system serves two audiences from one visual language. Citizens see a simple, generously-spaced single-column page — no login, no jargon, plain-language status. Staff see denser registry tables and multi-panel dashboards built from the exact same components, just packed tighter. Consistency across that density range is the point: a citizen and a clerk should recognize it as the same trustworthy system.

This explicitly rejects both failure modes it sits between: it is not a generic SaaS product (no gradient hero metrics, no cheerful illustrated empty states, no card-grid-everything) and it is not a legacy government portal (no dense Times New Roman tables, no ambiguous gray-on-gray hierarchy, no seal-and-ribbon clip art standing in for real design).

**Key Characteristics:**
- True municipal green + white paper, never pale mint or teal
- Flat by default — hairline borders, not shadows, at rest
- Mono "stamped" treatment for tracking numbers, timestamps, reference codes
- Status conveyed by color + text label together, never color alone
- Same component vocabulary from a single public tracking page to a dense admin registry

## 2. Colors

A two-color system at its core — deep green and white paper — with functional amber/red washes reserved strictly for state, never decoration.

### Primary
- **Municipal Green** (`#167a3a`): primary buttons, links, active states, the "you are here" marker in the routing stepper.
- **Green Deep** (`#0f4d28`): sidebar, masthead, page headings, panel section titles — the "authority" register of the palette, used for anything asserting structure rather than inviting action.
- **Green Bright** (`#2a9d4f`): accents, KPI progress bars, "live" indicator dots, the "done" fill in the routing stepper.
- **Green Wash** (`#eef5f0`): the only tint used for hover/active row backgrounds and completed-state pill backgrounds. Sparingly — it reads as "selected," not as generic background color.

### Neutral
- **Paper** (`#ffffff`): page and card background. Purity, not decoration — the palette works because everything else sits directly on true white.
- **Hairline** (`#e6ece8`): default panel borders and rules — a soft green-gray, not a pure Tailwind gray.
- **Hairline Strong** (`#cdd9d2`): emphasized dividers, button borders, table header rules.
- **Ink** (`#16211b`): primary text. A near-black tinted toward the green hue, not a pure Tailwind slate.
- **Ink Soft** (`#5b6b62`): secondary/muted text, labels, timestamps.

### Named Rules
**The Two-Color Rule.** Deep green and white paper carry the system. Every other color (brass, amber, red) is a functional accent used on ≤10% of any given screen — if you reach for a third decorative color, stop and ask whether a pill/label would do the job instead.

**The State-Never-Decoration Rule.** Amber (`#8a5a08` on `#fbeeda`) and red (`#8f2a2a` on `#fbe9e9`) washes exist only to signal a real state (watch/overdue). Never use them for visual variety, chart color-cycling, or emphasis on something that isn't actually a warning.

## 3. Typography

**Body Font:** Figtree (with `ui-sans-serif, system-ui` fallback)
**Label/Mono Font:** `ui-monospace, SF Mono, JetBrains Mono, Menlo, Consolas` — the "stamp" treatment

**Character:** Figtree is a warm, low-drama grotesque — legible and calm rather than sharp or editorial, which suits the "official but not cold" personality. The mono stack is reserved for anything that functions like a printed reference number, so it reads as a deliberate register shift, not a stylistic flourish.

### Hierarchy
- **Headline** (600 weight, `clamp(1.25rem, 2vw, 2.25rem)`, 1.2 line-height, `-0.02em` tracking): page titles in the app shell header.
- **Title** (600 weight, 13.5px, 1.3 line-height, `text-green-deep`): panel section headers (`.panel .ph h2`) — deliberately small and quiet; the panel border does the separating work, not the heading size.
- **Body** (400 weight, 14px, 1.5 line-height): default content text, capped conceptually at a single-column ~65ch reading width on citizen-facing pages.
- **Label** (600 weight, 11px, 1.2 line-height, 0.02em tracking): status pills, KPI captions, table headers (uppercase, `letter-spacing: .6px` on table headers specifically).
- **Mono/Stamp** (500 weight, 13px, tabular numerals): tracking numbers (`.id-chip`), timestamps, dates — anywhere a citizen or clerk needs to visually verify an exact code.

### Named Rules
**The Stamp Rule.** Anything that functions as a reference the user will copy, compare, or read aloud (tracking numbers, timestamps, reference codes) renders in the mono stack with `font-variant-numeric: tabular-nums`. Anything else — including numbers that are just data, like counts — stays in the body font.

## 4. Elevation

Flat by default, structural not ambient. Panels and cards sit on a single 1px hairline border at rest — no box-shadow. Shadows appear only when a surface is genuinely floating above the page: sticky navigation, dropdowns, and modals. When they do appear, they're tinted toward the deep green hue (`rgba(15,77,40,…)`) rather than neutral black, so even a "lifted" element still reads as part of the same palette rather than a generic UI-library shadow.

### Shadow Vocabulary
- **Sticky nav shadow** (`box-shadow: 0 4px 24px -8px rgba(15,77,40,.35)`): under the top navigation bar, signals it's pinned above scrolling content.
- **Dropdown/panel shadow** (`box-shadow: 0 8px 28px -8px rgba(15,77,40,.25)`): people-search dropdown and similar floating panels.
- **Modal shadow** (`box-shadow: 0 20px 50px -12px rgba(15,77,40,.4)`): confirmation/reassign modals — the heaviest shadow in the system, reserved for content that blocks the page.
- **Hover lift** (`box-shadow: 0 2px 10px -4px rgba(15,77,40,.18)`): directory cards only, on hover — the one at-rest-flat-to-hover-shadow transition in the system.

### Named Rules
**The Flat-At-Rest Rule.** If a surface isn't literally floating above other content (sticky, dropdown, modal) or responding to a hover, it gets a hairline border and zero shadow. A panel that "needs" a shadow to feel separated from the page needs a border instead.

## 5. Components

### Buttons (`.cr-btn` family)
- **Shape:** 8px radius, 1px border by default.
- **Primary** (`.cr-btn-primary`): `background: #167a3a`, white text, no border — the only button that reads as "the main action" on a page.
- **Ghost** (`.cr-btn-ghost`): transparent background, green text, no visible border until hover (`background: var(--green-wash)`).
- **Danger** (`.cr-btn-danger`): red text on transparent, red-wash on hover — reserved for destructive actions (deny, delete), never for a merely-secondary action.
- **Hover / Focus:** background shifts to a flat tint (`#f4f7f5` default, `#13682f` primary, `var(--red-wash)` danger); no scale/transform, no shadow pop.

### Status Pills (`.pill`)
- **Style:** fully rounded (999px), 11px label text, a 6px leading dot rendered via `::before` — never color alone; the text label is load-bearing.
- **State mapping:** green = completed/approved, amber = in-progress/in-review (active work), red = returned/rejected/denied, muted gray = pending (not yet started), amber-with-pause-icon = on hold (visually distinct from "returned," which is a red warning, vs. "on hold," which is a neutral pause).

### Tracking-ID Chip (`.id-chip`)
- **Style:** mono type, white background, 1px hairline border, 6px radius — reads as a small printed label stapled to the page, not an interactive element.

### Panels (`.panel` / `.reg` registry tables)
- **Corner Style:** 10px radius on panels, square corners inside registry tables.
- **Background:** white paper; header strip (`.ph`) uses `#fbfdfc`, one shade off white, to separate the title bar from the body without a visible border-in-border effect.
- **Shadow Strategy:** none at rest (see Elevation).
- **Border:** 1px hairline throughout; registry table rows use hairline dividers, header row uses the stronger hairline.
- **Internal Padding:** 11-14px header, 12-14px body.

### Routing Stepper (`.slip` / `.steps`/`.node`/`.seg`) — signature component
The system's most distinctive pattern: a horizontal row of circular "stamp" nodes connected by segments, representing a document's lifecycle stage by stage. `done` nodes render filled deep-green with a brass checkmark; `now` is a hollow white circle with a 2px brass ring (the "you are here" marker — brass is used nowhere else as a fill, only as this one accent); `todo` nodes are hollow gray with the step number. This component is deliberately reused verbatim from the smallest public tracking page to the densest staff dashboard — it is the one piece of UI a citizen and a supervisor both see in exactly the same form.

### Inputs / Fields (`.field`)
- **Style:** flat gray-green background (`#f4f7f5`), 1px hairline border, no inset shadow.
- **Focus:** border shifts to `--green-bright` tinted focus ring, no glow/blur effect.

## 6. Do's and Don'ts

### Do:
- **Do** keep panels flat with a 1px hairline border (`#e6ece8`) at rest — no `box-shadow` unless the element is genuinely floating (sticky nav, dropdown, modal).
- **Do** pair every status pill's color with a text label; never ship a color-only status dot.
- **Do** render tracking numbers, timestamps, and reference codes in the mono stack with tabular numerals.
- **Do** reuse the exact same routing-stepper component on citizen-facing and staff-facing pages — one visual language, not a simplified copy for citizens.
- **Do** keep citizen-facing copy in plain language ("In Progress," "On Hold") — never expose raw enum values like `in_transit` or `on_hold` in the UI.
- **Do** meet WCAG 2.1 AA contrast on every text/background pairing, with extra margin on public-facing pages for older devices and less-technical users.

### Don't:
- **Don't** use a gradient hero metric, big-number-plus-sparkline dashboard tile, or any other generic SaaS-dashboard cliché — this isn't selling anything.
- **Don't** default to a card grid for content that isn't genuinely a set of independent, browsable items. Registry tables (`.reg`) are almost always the right answer for staff-facing lists.
- **Don't** use pale mint/teal in place of the true municipal green (`#167a3a`/`#0f4d28`) — the legacy `emerald`/`teal` Tailwind ramps are intentionally remapped onto this exact green so old class names don't regress the palette.
- **Don't** use `border-left`/`border-right` as a colored accent stripe on cards or list items.
- **Don't** reach for amber or red on anything that isn't a genuine in-progress or overdue/error state — they are functional, not decorative.
- **Don't** let the citizen-facing pages feel colder or more bureaucratic than the staff pages in the name of "looking official" — dense, cold, Times-New-Roman-era government portals are exactly what this system was built to replace.
