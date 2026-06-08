# SPeEdtracQR — System Review & Planning Notes

_Date: 2026-06-03 — full scan of design, features, security, and architecture._
_Verified claims are marked ✅ (reproduced/checked in code), others are ⚠ (needs confirmation)._

---

## 0. Meta: the docs lie about where the app lives ✅

`CLAUDE.md` says the app is in `speed-traqr/` and all commands run from there. **It isn't.**
The Laravel app is at the **repo root**; `speed-traqr/` contains only an empty `resources/` folder.
This will mislead every future contributor (and every AI session). Fix `CLAUDE.md` first.

---

## 1. Correctness bugs (fix these — they break advertised features)

### 1.1 `Document::isOverdue()` is always `false` ✅ CONFIRMED
```php
$hoursStayed = now()->diffInHours($lastScan->scanned_at); // returns NEGATIVE in Carbon 3
return $hoursStayed > $sla;                                // -5 > 48 is always false
```
Reproduced: `now()->diffInHours(past) = -5.0`. Carbon 3 returns **signed** diffs.
**Every "overdue" check silently returns false.** The whole SLA/overdue surface is non-functional.
Fix: `abs(...)` or `$lastScan->scanned_at->diffInHours(now())`. Add a test.

### 1.2 The offline scanner is wired to nothing ✅ RESOLVED
The live scan page (`resources/views/scan/index.blade.php`) queues scans in IndexedDB when offline and syncs via `POST /api/scan/sync` with session CSRF auth. The old `resources/js/offline-scanner.js` (wrong URL, Bearer token) was **deleted** — it was never imported.

### 1.3 Failing test in the suite ✅ CONFIRMED (1 failed / 30 passed)
`ExampleTest::test_root_redirects_unauthenticated_users_to_login` expects `/` to redirect to login, but `/` returns `view('welcome')` (200). Either the test or the route is stale. CI is red.

### 1.4 `CheckSlaJob` doesn't check anything ⚠
It's dispatched with a delay and sends a breach email if the doc is still at the department — it never verifies elapsed time. If the queue runs late/early, or the worker is down and catches up, emails fire incorrectly. See §4.1 for the better pattern.

---

## 2. Security & privacy (government system — this matters most)

### 2.1 Tracking numbers are enumerable; public page leaks PII ✅
Format: `SPD-YYYYMMDD-NNNNN` where `NNNNN = random_int(0, 99999)`. That's a guessable, date-scoped 5-digit space. `/track/{trackingNumber}` requires **no auth** and renders **citizen name, document type, full timeline with staff first names, departments, and attachments**. `/track/{n}/status` has no throttle either. An attacker can brute-force ~100k IDs/day and harvest citizen records. For a gov office handling permits/cedulas, this is a real privacy breach.
**Options:** longer random token (e.g. 12+ chars), separate public-token from internal tracking number, rate-limit the track routes, and show citizens less by default.

### 2.2 ~~`.env` is committed to the repo~~ — CORRECTED: it is NOT ✅ RESOLVED
On closer check, `.env` is **untracked, gitignored, and never appeared in git history** (`git log --all -- .env` is empty). My initial read was wrong. No key rotation needed. The only residual point is operational: ensure the **production** `.env` sets `APP_DEBUG=false` / `APP_ENV=production` (deploy concern, §7) — local stays `true`.

### 2.3 Citizen uploads land on the PUBLIC disk ✅
Both staff and citizen uploads `->store('document-attachments', 'public')` → served at guessable `/storage/...` URLs with no auth. Sensitive documents (IDs, certificates) become publicly fetchable by anyone who guesses the filename. Move to a private disk behind an authorized/signed controller route.

### 2.4 Anyone with a tracking number can upload to a ticket ✅
`POST /track/{n}/upload` is public (throttle 12/min). Combined with §2.1 enumeration, an attacker can attach junk to arbitrary tickets and fill storage. No file-content validation beyond `image|max:10240`. Consider a per-ticket upload token, or require the citizen contact to match.

### 2.5 Default admin `admin@speedtraqr.com` / `password123` ✅
Seeded weak credential. Fine for dev, dangerous if it reaches prod. Force a reset / env-driven password.

### 2.6 Permissions are defined but unused ✅ RESOLVED
Capability gates now use Spatie `can()` consistently (`create documents`, `scan documents`, `view reports`, `manage users`, `manage system`). Route middleware uses `permission:` aliases; roles remain only for assignment and a few UX redirects (e.g. intake-only operators → scan page). `AuthorizationTest` covers create/scan/analytics/admin gates and cross-department scan blocking.

---

## 3. Architecture & design debt

### 3.1 Two competing routing systems ✅
`RoutingRule` (global, per document-type) **and** `DocumentRouteStep` (per-document) both exist, and `getRoutingChain()` / `getNextDepartment()` fall back between them everywhere. Two sources of truth = confusing, bug-prone. Recommend: `DocumentRouteStep` is the single source of truth; `RoutingRule` only seeds the default route at creation time.

### 3.2 Dead code: legacy views and controllers ✅
Confirmed **not referenced** anywhere: `scan.blade.php`, `track.blade.php`, `track_show.blade.php`, `track_search.blade.php`, `create_document.blade.php`, `document_created.blade.php`, and `DocumentController`. There are ~6 overlapping track views and duplicate scan views. Delete the legacy set — they invite editing the wrong file.

### 3.3 `Schema::hasColumn()` / `hasTable()` guards in hot paths ✅
ScanController and DocumentWebController query the DB schema on **every request** to decide which columns to write. They exist because migrations were patched after the fact (note the `fix_missing_columns_*` migrations). This adds query overhead and hides a broken migration story. Consolidate migrations, then strip the guards.

### 3.4 SLA enforcement model is fragile ✅
One delayed job per scan (`CheckSlaWarningJob` + `CheckSlaJob`) means thousands of pending jobs, all dependent on `queue:work` being up. `console.php` has **no scheduled task**. If the worker is down, no alerts ever fire and there's no catch-up. Recommend a single scheduled sweep (`documents:check-sla` hourly via the scheduler) using a corrected `isOverdue()` — far simpler and self-healing.

---

## 4. Recommendations beyond what exists (my additions, not yours)

1. **Replace per-scan delayed jobs with a scheduled SLA sweep** (§3.4). Simpler, robust, no worker dependency for correctness.
2. **Citizen SMS/email status updates.** You already capture `citizen_contact` but never use it. A "your document moved to X / is ready" notification is the single highest-value feature for citizens and trivial on top of the scan flow.
3. **Signed, expiring public tracking links** instead of guessable numbers (fixes §2.1 + §2.3 together).
4. **A real authorization test suite.** Current tests cover happy paths; none assert that staff can't scan other departments' docs, that the public can't see what they shouldn't, or the enumeration risk. For a gov system, authorization tests are the ones that matter.
5. **Audit/analytics you can act on:** average dwell time per department, SLA breach rate per department, bottleneck ranking. The data is all in `document_scans` already.
6. **Idempotency on scans** beyond `offline_uuid` — a staffer double-tapping "IN" should be a no-op by design, not by a fragile multi-column lookup.

---

## 5. Suggested priority order (to discuss)

| # | Item | Effort | Impact |
|---|------|--------|--------|
| P0 | `.env` out of git + rotate key + debug off (§2.2) | S | High |
| P0 | Fix `isOverdue()` sign bug (§1.1) | S | High |
| P0 | Fix/triage failing test, get CI green (§1.3) | S | Med |
| P1 | Move attachments to private disk + authorized access (§2.3) | M | High |
| P1 | Tracking-number enumeration + rate limit (§2.1) | M | High |
| P1 | Decide one routing model + one auth model (§3.1, §2.6) | M | High |
| P2 | Repair OR remove offline scanner (§1.2) | M | Med |
| P2 | Scheduled SLA sweep replacing delayed jobs (§3.4) | M | Med |
| P2 | Delete dead views/controllers, fix CLAUDE.md (§0, §3.2) | S | Med |
| P3 | Citizen notifications, dwell-time analytics (§4) | M–L | High (product) |

---

## 6. UX / interface / accessibility (the admin is the daily user)

### 6.1 The live nav is a hover-to-expand icon rail — risky for your main users ✅
The active layout (`layouts/app.blade.php`) is a 4.5rem sidebar showing **only green icons**; text labels appear **on hover**. (The old Breeze top-nav `layouts/navigation.blade.php` is dead code — included nowhere.) Problems:
- **Touch devices have no hover.** On a tablet at a front desk — the realistic device — labels never appear, so the admin stares at unlabeled icons. This directly fights "easy to use."
- **Discoverability:** a brand-new admin can't tell Dashboard from Scan from History without hovering each one.
- **Recommendation:** make the sidebar expanded-by-default (icon **+** label always visible) with a manual collapse toggle that remembers state, OR always show a text label under each icon. Don't hide primary navigation behind hover.

### 6.2 IN / OUT buttons send the wrong signal ✅
Scan page: **IN = solid green-600**, **OUT = `bg-red-300`** (pale) with white text. The pale red looks *disabled*, and white-on-red-300 fails WCAG contrast. For the single most-repeated action in the system, both buttons should be equally prominent, high-contrast, with an icon (↓ IN / ↑ OUT) so it's unmistakable.

### 6.3 No real document search ✅
The dashboard "search" only filters the ~10 visible recent rows in JS. A clerk asked "where is Mrs. Cruz's permit?" has **no way to search all documents** by citizen name or tracking number from one box. This is the #1 missing everyday tool. Add a real server-side search (tracking number + citizen name + type) reachable from the top bar everywhere.

### 6.4 No mistake-recovery, no edit ✅ (by inspection)
- A typo in `citizen_name` at creation is **permanent** — there's no edit-document screen.
- A wrong scan (OUT to the wrong department, or accidental IN) has **no undo / no "send back"**. Front-desk staff *will* mis-scan; right now the only fix is editing the DB. Add: edit-document, and an "undo last scan / return to previous department" action with audit logging.

### 6.5 Onboarding / empty states are thin ✅
Empty dashboard shows "No documents yet" with no call-to-action. There's one "How to record a handoff" blurb on the scan page; nothing else guides a first-time admin. Add: first-run hints, a clear primary "New Submission" CTA on the empty dashboard, and short inline help on Scan and Create.

### 6.6 "Documents waiting on ME" view is missing ✅
There's a dept-scoped dashboard and a Movements list, but no focused **work queue**: "documents currently at my department that I need to act on," sorted by SLA urgency. That single screen is what a department clerk actually wants to open each morning. The data exists (`current_department_id` + scans); it just isn't surfaced as a task list.

### 6.7 General accessibility pass needed ✅ (spot-checked)
Lots of icon-only buttons; status conveyed by color alone in places; focus states inconsistent. Before any public/gov launch, do a pass for: visible focus rings, color-contrast (AA), `aria-label`s on icon buttons, and keyboard operability of the sidebar and dropdowns. Gov systems are often held to accessibility standards.

**UX verdict:** the visual styling is genuinely nice (clean emerald theme, rounded cards, good stat cards). The gaps are **functional usability**, not looks: hidden navigation, no global search, no edit/undo, no per-user work queue. Those are what make or break "the admin knows what to do."

---

## 7. Deployment — what I'd actually do

You asked what to do about deployment. The honest answer depends on the stage, so here's the decision and the path:

**First, the gate:** Do **not** put this in front of real citizens until the §2 security items are done (especially: `.env` out of git + key rotation, `APP_DEBUG=false`, attachments off the public disk, and tracking-number enumeration addressed). For a government system handling names and documents, shipping with those open is the kind of thing that becomes a real incident.

**If it's a school/demo project:** keep `sqlite`, deploy to a free/cheap host, but still flip `APP_DEBUG=false` and change the seeded admin password so a reviewer can't trivially break it. Low effort, looks professional.

**If it's headed for real office use, the production checklist:**
1. **Database:** move from SQLite to **MySQL or PostgreSQL**. SQLite locks under concurrent writes — multiple clerks scanning at once will hit "database is locked." This is the single most important infra change.
2. **App config:** `APP_ENV=production`, `APP_DEBUG=false`, real `APP_KEY` (rotated, not the one in git history), HTTPS enforced, a real mail provider (currently `MAIL_MAILER=log` — **no email is actually being sent**).
3. **Queue + schedule:** the SLA jobs need a **running queue worker** (`queue:work` under `supervisor` or a `systemd` service so it restarts), and once we add the scheduled SLA sweep (§3.4), the **scheduler** must be on cron (`* * * * * php artisan schedule:run`). Without these, SLA/alerts silently do nothing in prod.
4. **Storage:** `php artisan storage:link`, and move sensitive uploads to a private disk (§2.3). Plan backups for the DB **and** `storage/`.
5. **Hosting:** simplest solid option is a small VPS (DigitalOcean/Hetzner/local PHP host) with Nginx + PHP-FPM 8.3 + the worker/scheduler, or a platform like Laravel Forge if budget allows. A **staging copy** to test before pushing to the office is worth it.
6. **Deploy hygiene:** `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`, `npm run build`.

**My recommendation:** treat the current state as **pre-launch**. Sequence: fix P0 security/bugs → switch to MySQL + real mail on a **staging** box → exercise the TESTING.md flow there → then go live. Don't deploy the SQLite/`log`-mail/`debug=true` setup to anything real.

---

## 8. Consolidated roadmap (everything, in priority order)

**Phase 0 — Stop the bleeding (do before any deployment). ✅ DONE 2026-06-03**
- ~~`.env` out of git~~ — was never in git; verified safe (§2.2) ✅
- Fix `isOverdue()` sign bug + add test (§1.1) ✅ — also fixed same bug class in `MovementController`; added `DocumentOverdueTest`
- Get CI green: fix/triage the failing test (§1.3) ✅ — `/` landing test corrected; suite green (33 passed)
- Env-drive the seeded admin password via `ADMIN_PASSWORD` (§2.5) ✅ — all three seeders + `.env.example`
- _Still pending for prod:_ ensure `APP_DEBUG=false` in the production `.env` (deploy step, §7)

**Phase 1 — Security & data protection. ✅ DONE 2026-06-03 (data protection); §2.6 reclassified**
- Move attachments to a private disk behind authorized access (§2.3) ✅ — uploads now on `local` disk, served only via `AttachmentController` with per-department checks; public citizen page no longer shows internal images; `AttachmentAccessTest` added
- Address tracking-number enumeration + rate-limit public track/upload routes (§2.1, §2.4) ✅ — 6-char unambiguous suffix (~729M/day); `throttle:60,1` on public track/status
- Pick ONE authorization model (permission-based `can()`) and apply consistently (§2.6) — **reclassified to its own focused pass (see Phase 3)**; it's a maintainability/consistency refactor, not an open vulnerability (role gates + department scoping already enforce access correctly), so it was kept out of this security commit to avoid a broad risky change mixed with data-protection work

**Phase 2 — Core UX gaps that make it "easy to use." ✅ MOSTLY DONE 2026-06-03 (undo/send-back → Phase 2b)**
- Sidebar: labels always visible / expanded-by-default + collapse toggle (§6.1) ✅ — pinned-by-default, persisted in localStorage, header toggle; no longer hover-only (works on touch)
- Global document search from the top bar (§6.3) ✅ — header search box reusing the existing scoped History search
- Per-user / per-department "work queue" view sorted by SLA (§6.6) ✅ already covered — the Movements **inbox** tab lists documents currently at your department with SLA bars + overdue filter, plus the dashboard At-Risk section; no duplicate built
- Edit-document with audit (§6.4) ✅ — `documents.edit/update`, department-scoped, activity-logged; `DocumentEditTest` added
- IN/OUT button contrast + icons (§6.2) ✅ — equal-weight buttons, icons, clear selected state; empty-state CTA on dashboard (§6.5) ✅
- Undo-last-scan / send-back (§6.4) ✅ DONE (Phase 2b) — `documents.undo-scan`: reverts the last scan (undoing an OUT returns the document to its last check-in; undoing an IN removes it), department-scoped, written to the activity log; `DocumentUndoScanTest` covers OUT-reversal, only-scan→pending, and the cross-department 403. _Known limitation:_ does not cancel already-dispatched SLA jobs (those are guarded to no-op in the common path; Phase 3's scheduled-sweep replacement removes the issue entirely). Un-completing via undo only applies to scan-driven state, not the separate `complete` action.
- _Also fixed in passing:_ `npm install` (axios was declared but never installed, so `npm run build` was failing for everyone)

**Phase 3 — Architecture hardening. ✅ DONE 2026-06-08**
- Delete dead views/controllers; fix `CLAUDE.md` (§0, §3.2) ✅ 3a — removed DocumentController, 6 legacy flat views, unused navigation layout; corrected CLAUDE.md
- Remove `Schema::hasColumn` guards (§3.3) ✅ 3b — **and discovered they were masking missing columns**: `citizen_contact`, `purpose`, `description`, `qr_code_path` (documents) and `remarks`, `offline_uuid` (document_scans) were never migrated, so those fields were silently dropped on write. Added a migration creating them, removed all guards, and added tests asserting create+edit now persist contact/purpose/description.
- Replace per-scan delayed jobs with a scheduled SLA sweep (§3.4) ✅ 3e — `documents:check-sla` hourly, deduped via notified markers reset on each IN; deleted CheckSlaJob/CheckSlaWarningJob
- Collapse the two routing systems to one source of truth (§3.1) ✅ 3d — `route_steps` is now authoritative; `RoutingRule` only seeds defaults at creation. Backfilled steps for legacy documents, removed the model fallback, added a test that global rules are ignored without steps
- Unify on one authorization model — permission-based `can()` — applied consistently (§2.6) ✅ 3f — `EnsureHasPermission` middleware, `manage system` permission for org-wide admin routes, nav uses `@can`, `AuthorizationTest` expanded
- Repair offline scanner (§1.2) ✅ 3g — live scan page already wired to `/api/scan` + `/api/scan/sync`; deleted dead `offline-scanner.js`; `OfflineScanSyncTest` added

**Phase 4 — Product value-adds.**
- Citizen notifications (SMS/email) using the captured contact (§4.2)
- Dwell-time / bottleneck / SLA-breach-rate analytics (§4.5)
- Accessibility (AA) pass before public launch (§6.7)

**Phase 5 — Deployment.**
- MySQL/Postgres, real mail, worker + scheduler, staging, backups (§7)

**Remaining (Phase 4–5 — product value-adds & deployment, not blocking pre-launch fixes):**
- Citizen SMS/email notifications (§4.2)
- Dwell-time / bottleneck analytics (§4.5)
- Accessibility (AA) pass (§6.7)
- Production deployment: MySQL/Postgres, real mail, worker + scheduler cron, staging, backups (§7)
- Operational: `APP_DEBUG=false` in production `.env`
