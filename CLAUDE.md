# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SPeEdtracQR is a Laravel 13 / PHP 8.3 document tracking system for government offices. Citizens submit documents (e.g. Business Permit, Cedula) and receive a QR-coded tracking number (`SPD-YYYYMMDD-XXXXXX`, where the suffix is 6 unambiguous base32 characters — high entropy to resist enumeration). Staff scan documents IN/OUT at each department; the system records each move, enforces SLA timers, and exposes a public tracking page for citizens.

## Working Directory

The Laravel app lives at the **repository root** (`artisan`, `composer.json`, `app/`, `routes/`, `resources/`, `tests/` are all at the top level). Run all commands from the repo root. The `speed-traqr/` subdirectory is a near-empty leftover and is **not** the app root.

## Commands

```bash
# Full dev stack (server + queue + logs + Vite in parallel)
composer dev

# Run tests
composer test

# One-off test file
php artisan test --filter=DocumentTest

# PHP linting / formatting
./vendor/bin/pint

# Asset build
npm run build          # production
npm run dev            # watch mode only (use composer dev for full stack)

# Production deploy (see DEPLOYMENT.md)
./scripts/deploy.sh --first-time   # initial server setup
./scripts/deploy.sh                # routine update after git pull

# Queue worker (if running without composer dev)
php artisan queue:work

# First-time setup
composer setup
php artisan storage:link
php artisan db:seed --class=RolesAndPermissionsSeeder
# Set ADMIN_PASSWORD in .env before seeding in any shared/production environment;
# it falls back to a weak dev default ("password123") if unset.
```

## Architecture

### Request flow

1. **Document creation** — `DocumentWebController@store` calls `QrCodeService` to generate a unique tracking number and save a PNG QR to `storage/app/public/qrcodes/`. The QR encodes the public tracking URL. The document is auto-checked-in at the first routing department.
2. **Scanning** — `ScanController@store` (POST `/scan`, route name `api.scan.store`, web+auth middleware) records a `DocumentScan` row. On `action=in` it sets `documents.current_department_id` and dispatches the SLA jobs. On `action=out` it resolves the next department via the document's `route_steps` (falling back to `routing_rules`); if there is no next step it returns 422 asking the user to pick a destination or mark the document complete (it does **not** auto-complete).
3. **SLA enforcement** — a single scheduled command `documents:check-sla` (hourly, see `routes/console.php`) sweeps in-transit documents and emails `SlaWarningMail` / `SlaBreachMail` once each per stay, deduped via `sla_warning_notified_at` / `sla_breach_notified_at` (reset on each IN scan). Requires `php artisan schedule:run` on cron in production. `Document::isOverdue()` and the dashboard/movements SLA bars compute elapsed time from the latest IN scan.
4. **Attachments** — citizen/staff document uploads are stored on the **private** `local` disk and served only through `AttachmentController` (auth + per-department check). QR images stay on the public disk. Mistakes are recoverable: `documents.edit/update` corrects details and `documents.undo-scan` reverts the last scan.

### Key models and relationships

| Model | Table | Notes |
|---|---|---|
| `Document` | `documents` | SoftDeletes + Spatie ActivityLog. Status: `pending`, `in_transit`, `completed`. |
| `DocumentScan` | `document_scans` | `action` = `in` or `out`. Has `offline_uuid` for dedup on sync. |
| `Department` | `departments` | `internal_step_hours` = turnaround for internal requests (default 72, `internalStepHours()`). No citizen SLA column — citizen stage clocks are `DocumentStatus::slaHours()`. |
| `RoutingRule` | `routing_rules` | `document_type + from_department_id → to_department_id + step_order`. Drives the routing on OUT scans. |

### Roles and permissions (Spatie)

Roles seeded by `RolesAndPermissionsSeeder`:

- `staff` — create documents, scan documents, view reports
- `receiving_staff` — scan documents (intake)
- `department_admin` — manage users (own dept), view reports, view all documents
- `super_admin` — all permissions; org-wide (not scoped to one department)

Department scoping is centralized in `App\Support\AssignmentScope`; `super_admin` is org-wide, everyone else is limited to their `department_id`.

Default admin: `admin@speedtraqr.com` (password from `ADMIN_PASSWORD`, dev fallback `password123`).

### Frontend stack

Blade + Tailwind CSS 3 + Alpine.js + Vite. No separate SPA; all views are server-rendered Blade. The `html5-qrcode` npm package provides camera-based QR scanning on the `/scan` page.

### Notable patterns

- **Active layout**: `resources/views/layouts/app.blade.php` is the live shell (collapsible icon+label sidebar). Track/scan views live under their subdirectories (`resources/views/track/`, `resources/views/scan/`).
- `QrCodeService` requires the PHP `gd` extension. Check with `php -m | grep gd`; install via `sudo apt-get install php-gd` if missing.
- **QR codes are signed** (`App\Support\TrackingQr`, payload `SPDQR1:…`) so only this site can read them; `window.SpeedQr` (`resources/views/partials/qr-scan-helpers.blade.php`) is the one scanner helper. A square camera box must use `.qr-viewport` + `.qr-surface` (`@include('partials.qr-camera', ['id' => …])`) — never `object-fit: cover` on the video, which distorts what html5-qrcode decodes.

### Citizen-facing pages

- **One look.** Landing, request form, receipt, track lookup, tracking result and privacy notice share the "Citizen surface set" in `resources/css/app.css` (`cz-*` classes: `cz-card`, `cz-btn cz-btn-primary/secondary`, `cz-title`, …), the header `layouts/partials/portal-header.blade.php` (landing passes `landing: true`) and `<x-page-intro>`. Use these, not new one-off styles.
- `/citizen` (the old portal page) only redirects to the landing page (`CitizenView::homeUrl()`). Citizen links carry `?view=citizen` so a staff login on a shared counter PC can't divert them.
- **Staff are anonymous to the public (legal).** Citizen surfaces (tracking page, its status poll and the public `.moved` broadcast, Speedy answers, message bylines, the reply email) name the handling office (`documents.department_id`), never the staff member. Staff-only pages may still name them.
- The public tracking page masks email/phone (`App\Support\ContactMask`). Internal (`INT-`) requests are "not found" on every public surface, including Speedy.
- The site FAQ lives in `App\Support\SiteFaq` — the landing page and Speedy both read it.

### Speedy (citizen assistant + office messaging)

- `<x-speedy>` (`resources/views/components/speedy.blade.php`) is on every citizen page; pages about one request pass it via `<x-citizen-layout :speedy-document="$document">`. Endpoint `POST /speedy/ask` (`SpeedyController`): `SiteAssistant` (FAQ + requirements, LLM last) until a tracking number is typed/scanned, then `DocumentAssistant`. Shared helpers in `app/Support/Ai/`.
- Two tabs: "Speedy" (automated, always labelled so) and "Office messages" (real staff, named with office + STAFF mark). Never present staff replies as the bot.
- The citizen↔staff thread is read **and** written only after the citizen confirms the email/mobile on file (`CitizenThreadAccess`). Logic in `App\Support\CitizenThread`; JSON endpoints `track.messages.index/verify/store`, unread badge from `GET /speedy/inbox`. The public channel `documents.{tracking}` only carries the content-free `CitizenThreadChanged` ping; full messages go to the private staff channel only. The reply email links to `?speedy=messages`.

### Super admin command center (`/admin/dashboard`) and supervisor Reports (`/reports`)

- One page, two audiences: `App\Support\CommandCenter::data($request, $office)` builds it (`$office = null` → city-wide for `AdminController`; the supervisor's department → `ReportsController`), drawn by `admin/partials/command-center` inside `admin/dashboard` / `reports/index` (separate wrappers because page titles must be literals). Office mode swaps "Requests by Office" for "Bookings by Resource", links requests to `track.show` instead of `/admin/requests`, nudges via `reports.nudge` (own office only) and hides the audit-log link. `AdminAnalytics` / `InternalAnalytics` / `ActivityLine::recentOnRequests` take an optional department id; an office's internal side is `Document::involvingOffice()` (filed by it or routed through it).
- `/reports` replaced the supervisor's Analytics page and Services report (`/analytics`, `/reports/services` redirect to it; super admins are sent on to the command center). Staff have no `view reports`.
- Two sides, never added together (`?view=citizen|internal`): citizen numbers come from `App\Support\AdminAnalytics` (scoped with `Document::citizenFacing()`), internal ones from `App\Support\InternalAnalytics` (`Document::internal()`; overdue = held at an office longer than that office's `internal_step_hours`; returned = waiting on the filing office, no deadline).
- Citizen side: six tiles read one cohort (requests filed in the selected range) plus Avg. Processing (with its sample size) and Overdue; overdue/at-risk is decided only by `slaState()`. "Reached no office" alert lists open requests whose type has no office. Durations via `App\Support\Duration`. On-time rate, "Fastest staff" and the deadline donut were removed on purpose. All panels use `cc-panel`.
- `/admin/requests` (`RequestOverviewController`): every citizen request, read-only, in the shared Review modal with `mode: 'oversight'` (no assign/advance; a Notify button). Super admin request links land here with `?open=SPD-…` — `track.show` redirects them here, as do the command center's lists and the unrouted alert. Notify is one dialog, `admin/partials/notify-dialog` (`$dispatch('admin-notify', {...})`); an unassigned request notifies its office's supervisors only.
- Super admin sidebar: Dashboard, Requests, Users (absorbed the Staff directory's workload via `App\Support\StaffLoad`), Internal Requests, Resources, Audit Log, then a "Setup" group (Departments, Route Templates, Request Types). The Services report is gone (checklist → dashboard/Reports, booking use → Resources and supervisor Reports).
- Audit lines are worded by `App\Support\ActivityLine` (tracking numbers, not "Document #19"; empty "updated" rows hidden). Login/logout listeners are auto-discovered — don't also `Event::listen` them (that logged everything twice).
- Super admins are not belled for every new citizen request: `DocumentEvent::departmentTriagers` goes to that office's supervisors, and only falls back to super admins when the office has no active supervisor (no office at all → every triager, as before).

### Supervisor dashboard (`/dashboard`)

- Sidebar: Dashboard, Internal, Resources, Reports (staff: Dashboard, Internal, Booking, Resources). Resources is `inventory.index` behind `manage inventory` (granted by migration `2026_10_20_012646_…`, so no reseed is needed); it is due to grow into inventory management. The Assignments page is gone: the dashboard's Unassigned / In Progress / Completed (last 90 days) tabs list the office's citizen requests, and the Review modal assigns and reassigns (`admin.assignments.assign`, JSON for the modal, own office only). Old `/admin/assignments` links redirect to `dashboard?tab=…&q=…`. The dashboard carries a one-line "Last 30 days" glance strip (`AdminAnalytics::kpis()` for the office) linking to Reports; the full numbers live there, not on the dashboard.
- Department scoping (`AssignmentScope`): a supervisor sees their office's citizen requests, unrouted citizen intake, and internal requests their office filed or that route through it — not every office's internal requests (they carry no `department_id`).
- No SLA routing slip (the staff member advances the work; at-risk lives on the staff dashboard). Internal requests live on the dedicated Internal tab (`/requests`, queues from `App\Support\InternalInbox`, drawn by `requests/partials/inbox`) for supervisors and staff alike — not on the dashboard.
- Sidebar badges (`App\Support\NavBadges`): Dashboard = new citizen requests waiting on you (supervisor: unassigned in your office or unrouted; staff: assignments not yet accepted); Internal = internal requests at your office (supervisor) or sent to you / paper not yet scanned in (staff). Re-rendered with every page (soft-nav swaps the sidebar) and polled from `nav.badges` every minute. Super admins get none.

### Resources tab: lending desk + stock (`/inventory`)

- A borrowing is a `Booking` that runs pending → approved (staff set `bookings.quantity`, whole numbers; the citizen's ask stays on `documents.quantity`) → handed over (`released_at`) → returned (`returned_at`, set once every unit is back or written off). Rules live in `App\Support\Lending` (approve/refusal/release/receive/finish/cancel/remind), shared by the Resources page (`LendingController`, routes `inventory.loans.*`, permission `manage inventory`) and the Booking calendar (`BookingController::approve` takes `quantity` too).
- The booking and its request are one flow: approving a booking needs the request assigned **and** accepted (`Lending::requestNotReady`; desk shows why, badge/tile count only approvable ones); the request can't reach Approved while its booking is pending or cancelled (`StatusGate` check `reservation_approved`); hand-over needs the request Approved/Completed and completes it + stamps `claimed_at`/`released_by` (`Lending::markRequestReleased`); a counter release (`documents.release`) of a borrowing hands the items over (stock-checked); denying cancels the booking quietly. The Review modal shows a Reservation card (`RequestReview::bookingPayload`, approve with a count in place via JSON from `LendingController`), and the citizen tracking page shows `track/partials/citizen-booking` (no staff names).
- A request's claiming date (`documents.claim_date`) and its booking's pickup are one day: `ReviewController::setClaimDate` and `DocumentStatusController::advance` call `Lending::followClaimDate` (booking shifts by the same days, keeping its length; refused with nothing saved if an approved booking's stock/venue can't cover the new dates; a handed-over booking stays put), and calendar Reschedule (`Lending::reschedule`) sets the claiming date.
- One page for every role: super admins get `inventory.index` too (`admin.resources.index` redirects there) plus catalog controls (`inventory/partials/catalog-actions`, `Admin\ResourceController`: add with kind + count, edit, deactivate, delete). Kind is fixed once an item has bookings; delete is refused while bookings are live; a deactivated item's request types are hidden from the public form and refused on submit; a request type must point at the matching kind (booking → facility, equipment → equipment).
- Return date: "Change return date" on Overdue / On loan / For pickup rows (`inventory.loans.extend`, `Lending::extend`) — pickup stays, extra days stock-checked against only what is still out, clears overdue, emails `BookingUpdated` outcome `extended`.
- Nothing rejoins stock on the return date: a handed-over loan holds its outstanding units until someone records the return (`Booking::unitsHeldDuring`), and past `ends_at` it is overdue. Returns can be partial; damaged units move to `resources.damaged_quantity`, missing ones come off `total_quantity`. A facility is "Done" via the same return route (frees the rest of its slot).
- Stock columns: owned, out of service, on loan (`unitsOnLoan`), promised (`unitsReserved`, approved but not collected), available = owned − out of service − on loan − promised. Approval checks the request's own dates (`unitsAvailable`); hand-over checks the shelf (`unitsOnHand`). Owned can't be set below on loan + out of service.
- `inventory:check-overdue` (hourly, `config('tracking.loans')`) emails the borrower (`BookingUpdated` outcome `overdue`; `returned` when a loan closes) and bells the assignee + office supervisors (`DocumentEvent::loanOverdue`), re-reminding every `remind_every_days`. Sidebar badge `resources` = requests to approve + overdue loans. Item history = activity log on the `Resource`.

### Internal requests: hard copy vs soft copy

- `documents.has_hard_copy` (default true). Hard copy = a folder each office scans in (custody before any decision, QR scan to confirm). Soft copy (`Document::isSoftCopy()`) = no folder: the filer names a recipient at the first office (`request_steps.recipient_id`), forwarding names one at the next office. A soft copy must still be **received** before anyone acts: the recipient or that office's supervisor scans its QR — usually with the snipping tool on the Receive card (`partials/custody-scan`): "Select the QR on screen" opens the request's QR viewer (`show-request-qr` event — use `x-on:`, Blade eats `@show`) and `window.SpeedQr.scanFromScreen('page')` dims the page for a box drag, reading the page's own images with no permission prompt; "QR open in another window?" is mode `'any'` (screen-capture a window, box it on a still) → custody `capture_method = 'screen'` ('image' = a saved picture, 'scan' = camera); no second scan to decide. Screen/picture receipts are refused for paper requests (camera only). Until received, the status reads "Awaiting receipt"; the receipt bells the filer (`DocumentEvent::internalReceived`).
- INT- QR images encode a link to the public tracker carrying the signed payload (`TrackingQr::content()`), so a phone camera opens it; `ScannedCode` and `SpeedQr.extractTracking` read the payload back out of that link. Existing INT QR images keep the old bare payload until `php artisan qr:regenerate`. Names on the public tracker are switchable: `TRACKING_PUBLIC_INTERNAL_NAMES=false` → offices only.
- Public internal tracker (`internal.track`, `InternalTrackerController` + `App\Support\InternalTracking`), on the sign-in page's "Track a request" tab: anyone with an INT- QR/number sees status, the office holding it, who is handling it, and the offices it passed — never the purpose, remarks or files. SPD- numbers are sent to the citizen tracker. The named recipient may act on their step even as plain staff (`canActOnCurrentStep`), so the step routes are gated in the controller, not by `permission:act on internal requests`. The filing form fetches recipients per office from `requests.recipients`.
- Receiving vs deciding: `Document::canReceiveCurrentStep()` — anyone at the holding office may scan a **paper** folder in (custody), but only `canActOnCurrentStep()` people (the office's supervisor, or a soft copy's named recipient) approve/return/deny/complete. A soft copy is still received only by its recipient or their supervisor. The request page shows the Receive card to receivers, the decision card only once received.
- One scan receives: the header's Scan QR Code and the Look Up page call `SpeedQr.openScanned()`, which for an INT- code posts to `documents.custody.scan` (`DocumentCustodyController::receiveByScan`): if the scanner may receive it and nobody at the office has, the same scan records the receipt (camera; a QR picture only for soft copies), then opens the request. Custody writes go through `DocumentCustodyController::record()`.
- Returned requests: the filing office (`Document::canBeRevisedBy` — the filer, or anyone there with `create internal requests`) **Resends** (`requests.resend`: note required, optional revised scan; a new current step at the office that returned it, which must receive it again; a soft copy goes back to the same person) or **Withdraws** (`requests.withdraw`). `DocumentStatus::Withdrawn` is terminal and internal-only — citizen pages and citizen analytics use `DocumentStatus::citizenCases()`; internal analytics count it neither as moving nor as denied. The Internal badge also counts returned requests (filer; the filing office's supervisors).

### Local dev notes (this machine)

- **MySQL 8 on port 3306** (`DB_HOST=127.0.0.1`, database `speedtraqr`; see `.env`, and never print `DB_PASSWORD`). This file used to say MariaDB on 3307, which cost a session a failed connection. Vite dev server writes `public/hot`; the PHP tests run on in-memory SQLite.
- The landing page must render without a database query for ordinary visitors (`tests/Feature/ExampleTest.php` has no DB).
- Working branch is `biddyblas` (the team merges it into `riskofrain`; fetch that with `git fetch origin riskofrain:refs/remotes/origin/riskofrain`). Leave `SKILL.md` and the local `package-lock.json` name change uncommitted.
- The Next.js port is on branch **`riskofrain2`** (see below).

## The Next.js port (`web/`, branch `riskofrain2`)

A parallel rewrite of this system in Next.js, **sharing the same MySQL database** through Prisma (`web/prisma/schema.prisma` is introspected from Laravel's migrations — Laravel's migrations stay the source of truth). Laravel is still the working system; the port is incomplete and is being filled in screen by screen.

```bash
cd web
npm run dev            # dev server on :3000
npx vitest run         # the TypeScript suite
npx tsc --noEmit       # typecheck
npm run build          # production build (run it before committing a slice)
```

- **URLs are byte-identical to the Laravel ones** and are named in `web/src/lib/routes.ts` — QR stickers, receipt emails and bookmarks already in people's hands encode them.
- **Layers**: `lib/domain/*` are the ports of `app/Support` + model logic (they reach the database); `app/actions/*` are the `'use server'` write paths, each returning `{ ok, message }` rather than throwing; pages are server components wrapped in `AuthenticatedPage`.
- **Only types may cross into a `'use client'` component from a domain module.** Importing a runtime value from one drags Prisma into the client bundle and the build fails. Constants the browser needs live in their own client-safe module (`request-kinds.ts`, `resource-kinds.ts`, `route-template.ts`).
- **Permissions are re-checked inside every server action**, not only on the page: an action is a POST endpoint of its own.
- Roles in the port read from Spatie's tables, so the Laravel seeder stays the one source of truth for who can do what.

## Manual Testing Checklist

See `TESTING.md` (repo root) for a 10-step UI flow covering: document creation, QR file check, IN/OUT scans, public tracking, dashboard, history, analytics, offline mode, and a full 3-department routing flow.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- laravel-echo (ECHO) - v2
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
