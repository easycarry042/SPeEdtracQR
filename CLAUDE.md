# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SPeED TraQR is a request tracking system for the City Government of San Pedro, Laguna, currently focused on the **City Tourism, Culture and Arts Office (CTCAO)**. Citizens (and other city offices) file requests online and get a QR-coded tracking number (`SPD-YYYYMMDD-XXXXXX`, 6 unambiguous base32 characters). Office-to-office requests are `INT-` numbers. Staff process requests, supervisors confirm them, and the citizen claims when it is Ready for Claim.

It is a **Next.js (TypeScript) app in `web/`**, hosted on **Vercel with Supabase** (Postgres, Storage, Realtime). The original Laravel/PHP app was removed on 2026-10-26; it is in git history before that date if anything needs looking up.

## Working directory

Everything runs from `web/`. The repo root holds only docs (`DESIGN.md`, `PRODUCT.md`, `DATA_DICTIONARY.md`, `TESTING-INTERNAL-PROCESS.md`, `docs/`).

```bash
cd web
npm run dev                  # dev server on :3000
npx vitest run               # the test suite
npx tsc --noEmit             # typecheck
npm run build                # production build (run it before committing)
npm run db:deploy            # apply prisma/migrations (DIRECT_URL)
node scripts/seed-roles.mjs        # roles + permissions (Spatie-shaped tables)
node scripts/seed-departments.mjs  # the city offices
npm run db:seed:tourism            # CTCAO office, resources, placeholder staff, request types
```

- **Tests and the database:** `tests/setup.ts` loads `web/.env`, and `web/.env` points at **Supabase**. The integration tests (`tests/integration`) and some unit tests that only partly mock `db` then read (and the rate-limit test writes) the live database. Run tests against a local Postgres instead: `DATABASE_URL=postgresql://postgres@127.0.0.1:5432/speedtraqr_ctcao npx vitest run`. Local Postgres 18 runs on this PC (trust auth); `speedtraqr_ctcao` has the migrations and the tourism seed.
- Never print secrets from `.env` (DB password, mail password, keys).

## Architecture

- **Layers:** `src/lib/domain/*` = the business rules (they reach the database through Prisma); `src/app/actions/*` = `'use server'` write paths, each returning `{ ok, message }` rather than throwing; pages are server components wrapped in `AuthenticatedPage`. **Permissions are re-checked inside every server action** — an exported action is a public POST endpoint, so never leave an unused one exported.
- **Only types may cross into a `'use client'` component from a domain module.** Constants the browser needs live in client-safe modules (`request-kinds.ts`, `resource-kinds.ts`, `route-template.ts`). A function a server page calls must not live in a `'use client'` file.
- **Database is Postgres** (`prisma/schema.prisma`). Every schema change is a migration in `prisma/migrations` (never `prisma db push` alone — a fresh database is built only from migrations); every new table gets `ENABLE ROW LEVEL SECURITY` (no policies). Lookups that need case-insensitivity use `mode: 'insensitive'`.
- **Time:** timestamps are stored as naive Manila wall-clock (`lib/time.ts`). Compare stored times with `time.now()` / `addHours(now(), …)`, never `Date.now()` (8 hours off). Seal/QR maths that need a real epoch use `time.unixTimestamp()`.
- **Roles** live in Spatie-shaped tables (`roles`, `permissions`, `model_has_roles`, `model_type = 'App\\Models\\User'`), seeded by `scripts/seed-roles.mjs`: `staff`, `Supervisor`, `super_admin`, `Clerk` (Clerk is not yet treated as staff by the app). Department scoping: `lib/domain/scope.ts`.
- **URLs are byte-identical to the old app's** (`lib/routes.ts`) — QR stickers and emails already in people's hands encode them.
- **Files** go through `lib/storage.ts`: Supabase buckets `private` (uploads, served only by office-checked routes) and `public` (QR images, via `/storage/*`). Without `SUPABASE_URL`, files live in `web/storage/app` (gitignored). Never write to the filesystem directly — Vercel's is read-only.
- **Live updates:** `lib/realtime.ts` relays only PUBLIC `documents.{tracking}` events to Supabase Realtime (the client treats an event as "re-read", never as data). Without Supabase, an SSE route serves local dev.
- **Background work** uses `after()` (`queueMail`, `queueSms`). Mail needs `MAIL_HOST`; SMS needs `SMS_GATEWAY_URL` (`smsConfigured()`); without them nothing is sent and nothing is logged as sent.
- **Cron** (`vercel.json`, UTC; Hobby plan = once a day per job): `/api/cron/daily` at 07:00 Manila (SLA, holds, overdue loans) and `/api/cron/reclaim-unaccepted` at 10:00 Manila. Calls need `Authorization: Bearer $CRON_SECRET`.
- **Public endpoints** are throttled in Postgres (`lib/rate-limit.ts`) and refuse cross-site `Origin`s.
- **Uploads on Vercel:** server actions accept 10 MB, but Vercel refuses bodies over 4.5 MB (direct-to-Storage uploads not built yet).

## Citizen request flow (staff do the work, supervisors confirm)

Statuses (`lib/domain/document-status.ts`): **Pending → Ongoing (`in_progress`) → Pending Approval (`in_review`) → Ready for Claim (`completed`)**, plus Returned, On Hold, Denied. `approved` is retired (old rows read as Ready for Claim). Citizen surfaces use `CITIZEN_LABELS` ("Received & Queued" … "Ready for Pickup / Claiming").

1. **Intake** (`app/actions/request.ts`): the request goes to its type's office and is **auto-assigned** to an on-duty usual handler (`auto-dispatch.ts`: round-robin over `request_type_handlers` whose user is active and `is_available`). With nobody on duty it lands in the office **pool**. Supervisors are belled either way.
2. **Staff:** accept an assignment, or **claim** from the pool (`review-actions.claim`, atomic) → Ongoing. Accepting bells the supervisors (`office-progress.ts`).
3. **Submit for Supervisor Review** (`review-actions.submitForClearance`): required files must be checked → Pending Approval; supervisors are belled.
4. **Supervisor** (own office only; super admins any): **Confirm & Release** (`review-actions.approveClearance`, the ONLY path to Ready for Claim; only from Pending Approval) → stamps `reviewed_by/at`, tells the citizen by email (unless opted out), SMS (if a gateway is set) and a public thread message carrying the supervisor's note as claiming instructions; or **Return for Revision** (`returnToStaff`: back to Ongoing, the note kept in `supervisor_notes` — internal, never `remarks`, which the citizen's tracker shows); or **Deny** (terminal).
5. **Claim:** borrowed items are handed over on the Resources page only once Ready for Claim (`lending-desk.release` stamps `claimed_at`, never moves the status).

**Absent staff:**
- **On Duty / On Leave** toggle (`availability.ts`; self, own-office supervisor, or super admin). Going On Leave immediately hands that person's not-yet-accepted requests to another on-duty usual handler, else the pool. Work they already accepted stays theirs until they **Release to Office Pool** or the supervisor **reassigns** it (quick-reassign shows who is On Duty).
- **Escalation sweep** (`sweeps.reclaimUnacceptedRequests`, 10:00 Manila): requests assigned but not accepted within 2 hours move the same way.

Other rules:
- **Requirement checks:** an uploaded file must be opened (`?open=1`) before it can be marked checked (`requirement-review.ts`); nothing uploaded = checked at the counter.
- **Bookings:** approving a booking needs the request assigned and accepted (`lending.ts`, `lending-desk.ts`); Ready for Claim needs the reservation approved (`status-gate.ts`).
- **CTCAO setup:** each request type has usual handlers and may ask one extra question (`request_types.details_label` → `documents.details`); the public form has an optional Office or organization field (`documents.requester_office`), so other offices file through it too. Non-tourism request types are switched off (Request Types page turns them back on).

## Citizen-facing rules

- **Staff are anonymous to the public (legal).** Citizen surfaces (tracker, its live updates, Speedy, message bylines, emails) name the office, never the staff member.
- The public tracker masks email/phone. `INT-` requests are "not found" on every public surface.
- Speedy (`components/citizen/speedy*`, `lib/domain/ai/*`) answers from the FAQ (`site-faq.ts`) and the request; staff replies are always labelled as staff, never as the bot. The citizen thread opens only after they confirm the email/mobile on file.

## Internal requests (`INT-`, office to office)

Hard copy (a folder each office scans in with the camera) vs soft copy (a named recipient receives it by scanning its QR on screen). Anyone at the holding office may scan a paper folder in; only the office's supervisor or a soft copy's named recipient decides (approve / return / deny / complete). Returned requests are resent or withdrawn by the filing office. Logic in `internal-*.ts`, `custody.ts`, `request-step.ts`; manual test cases in `TESTING-INTERNAL-PROCESS.md`.

## Working branch

Commit only when asked. The team works on `main` (it absorbed `biddyblas`).
