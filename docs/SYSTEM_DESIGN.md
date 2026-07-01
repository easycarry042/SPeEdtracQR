# SPeEdtracQR — System Design & Feature Guide

A complete, plain-language explanation of what SPeEdtracQR is, who uses it, how a
request moves through it, and how the whole thing is built. This is the "read this
first" companion to the more focused docs (`SYSTEM_REQUIREMENTS.md`, `DEPLOYMENT.md`,
`SETUP.md`, `TESTING.md`). It is grounded in the code as it actually stands today —
after the June 2026 model overhaul described below.

---

## 1. What the system is

**SPeEdtracQR** is a document-tracking system for a government / local-government
office. A citizen submits a request (Business Permit, Cedula, clearance, etc.) and
immediately receives a **QR-coded tracking number** in the form:

```
SPD-YYYYMMDD-XXXXXX
```

The six-character suffix is drawn from an unambiguous base-32 alphabet with high
entropy, so tracking numbers cannot be casually guessed or enumerated. From that
point on:

- The **citizen** can watch the request move through clear, human-readable stages
  on a public page — no account, no app, no login. They can also ask a small
  on-page AI assistant "where is my document?" in plain language.
- A **supervisor** reviews incoming requests and assigns each one to a responsible
  staff member (or denies it).
- The **assigned staff member** accepts the work and manually advances it through
  its lifecycle: *In Progress → In Review → Approved → Completed* — or returns it
  for revision, or puts it on hold.
- A **super admin** oversees the whole organization through an analytics command
  center and an immutable audit log.

The design goal is **transparency and accountability**: at any moment, everyone
who is allowed to know can see *where a request is, who has it, and how long it
has been sitting there* — with automatic SLA (service-level agreement) alerts when
something overstays its stage.

---

## 2. The big design decision — from "scanning route" to "assignment + manual stages"

> This is the single most important thing to understand about the current design,
> because older docs (and parts of `CLAUDE.md`) still describe the *previous*
> model.

**Original model (deprecated).** The first version modeled a physical paper trail:
departments, routing rules, and IN/OUT *scans*. A document was scanned OUT of one
department and IN to the next; routing rules decided the next department; SLA
timers were anchored to the last IN scan. This mirrored how a folder physically
walks from desk to desk.

**Current model (June 2026 overhaul).** That machinery was **dropped**. The
`departments`, `routing_rules`, and department foreign keys were removed by
migration. Tracking is now **people-centric and status-centric**, not
department-centric:

| Old concept | Replaced by |
|---|---|
| Route a document between **departments** | **Assign** a document to a **staff member** |
| IN / OUT **scans** move it along | The assignee **manually advances the status stage** |
| `routing_rules` decide the next hop | A single linear **status flow** (`DocumentStatus` enum) |
| SLA anchored to last **IN scan** | SLA anchored to `status_changed_at` (time in current stage) |
| `department_admin` role | Renamed to **`Supervisor`** |

**Why the change?** For a single office adopting this, "who is responsible right
now" is a more honest and enforceable question than "which department is the folder
in." It removes a whole layer of routing configuration, makes accountability
personal, and makes the citizen-facing story simpler ("your request is *In Review*
and it's with *Maria Santos*"). Adding or renaming a stage is now a one-line edit to
an enum with **no database migration**, because `documents.status` is a plain
varchar.

Some legacy artifacts remain by design for backward compatibility: the `ScanController`
and `/scan` page still exist, and `DocumentStatus::fromLoose()` still tolerates the
old `in_transit` value so old rows keep rendering.

---

## 3. The people (roles & permissions)

Authorization uses **Spatie Laravel-Permission**. Roles are seeded by
`RolesAndPermissionsSeeder`:

| Role | What they can do |
|---|---|
| **`staff`** | Create documents, scan, **advance** their assigned documents, **accept** assignments |
| **`receiving_staff`** | Intake only — scan and accept documents |
| **`Supervisor`** | View all documents, **assign documents** to staff, view reports (department-scoped) |
| **`super_admin`** | Everything, org-wide — `manage system`, audit log, user management, analytics command center |

Permissions are fine-grained strings (`create documents`, `assign documents`,
`accept documents`, `advance documents`, `view reports`, `manage users`,
`view all documents`, `manage system`, …) that roles bundle. Controllers gate on
these via `permission:` middleware.

**Scoping.** `super_admin` is **org-wide**; everyone else is limited to their own
scope. This is centralized so it can't drift: `App\Support\AssignmentScope`
(with `canViewAll()`) governs who may act on any document, and `ScopesByDepartment`
narrows list queries. Landing after login is role-aware (`routes/web.php` `/`):
super admins → analytics command center, supervisors → operational Requests
dashboard, staff → their own profile/desk.

Default seeded admin: `admin@speedtraqr.com`, password from `ADMIN_PASSWORD`
(dev fallback `password123` — **must** be set in any shared/production env).

---

## 4. The lifecycle — how one request travels

The lifecycle is defined in one place: the **`App\Enums\DocumentStatus`** enum. It
is the single source of truth for labels, ordering, SLA budgets, and colors.

### 4.1 The stages

**Forward line** (the stepper the citizen and staff see):

```
Pending → In Progress → In Review → Approved → Completed
```

**Side states** (off the forward line):

- **Returned / For Revision** — sent back to the citizen for corrections.
- **On Hold** — blocked or waiting; the **SLA clock is paused**.
- **Denied** — supervisor rejected the request (terminal).

`Completed` and `Denied` are **terminal** (the SLA clock stops, the document drops
into History).

### 4.2 The journey, step by step

1. **Submission.** A citizen submits online (`PublicTicketController@store`,
   `source='online'`) or a staff member creates it at the desk
   (`DocumentWebController@store`). A tracking number and QR PNG are generated by
   `QrCodeService`; the document starts at **Pending**. A confirmation email
   (`TicketSubmitted`) goes to the citizen, and the QR/tracking screen is shown
   first so they can save it before navigating away.

2. **Supervisor review.** On the Requests dashboard a supervisor either:
   - **Approves = assigns** it to a staff member
     (`ReviewController@assignApprove`): sets `assigned_to/by/at`, emails the
     assignee an `AssignmentNotice`, logs a system comment. Status stays Pending —
     *awaiting staff acceptance*.
   - **Denies** it (`ReviewController@deny`): status → **Denied** (terminal),
     optional reason recorded in `remarks`.

3. **Staff triage.** The assigned staff member sees it on their Requests page and
   chooses one of three actions (`ReviewController`):
   - **Accept** → Pending → **In Progress**, stamps `accepted_at`.
   - **Decline** → clears the assignment and returns it to the supervisor queue
     (back to Pending).
   - **Request revision** → status → **Returned**, with a required reason (sent to
     the citizen).

4. **Doing the work.** The assignee manually advances the stage on the status
   stepper (`DocumentStatusController@advance` / `revert` / `set`), or via the
   review shortcuts (`open` = In Progress → In Review; `complete` = Approved →
   Completed). Guardrails enforce order: you cannot mark Completed until the
   document is **Approved**, and the review controller never regresses a later
   stage.

5. **Holds.** Anyone with authority can put a document **On Hold**
   (`DocumentStatusController@hold`) with a reason, an optional `hold_until` date,
   and a `blocked_by` tag (`citizen`, `internal`, or `external`). Hold **pauses the
   SLA** (its `slaHours()` is null). `unhold` restores the prior stage from
   `status_before_hold`.

6. **Completion.** When the work is done the staff member completes it; it moves to
   **Completed**, `completed_at` is stamped, and it appears in **History**
   (`HistoryController`, with CSV export).

Every transition is (a) written to the **Spatie activity log**, (b) posted to the
document's **unified comment/timeline feed** as a system note, and (c) broadcast
live over WebSockets (see §6).

---

## 5. Data model

Post-overhaul the schema is deliberately small. Core tables:

| Model / table | Purpose | Notable columns |
|---|---|---|
| `Document` / `documents` | The request itself. SoftDeletes + activity log. | `tracking_number`, `document_type`, `citizen_*`, `source` (`online`/desk), `status`, `assigned_to/by/at`, `accepted_at`, `status_changed_at`, `hold_*`, `blocked_by`, `sla_*_notified_at`, `sla_breached_at`, `qr_code_path`, `completed_at` |
| `User` / `users` | Staff & admins. SoftDeletes, `is_active`. | roles via Spatie |
| `DocumentAttachment` / `document_attachments` | Files (citizen or staff uploads). | stored on the **private** disk, ordered by `sort_order` |
| `DocumentComment` / `document_comments` | Unified per-document feed: staff notes, citizen-visible replies, and author-less **system** events. | `author_type`, `visibility` (`public`/`internal`), `parent_id` |
| `StaffHighlight` / `staff_highlights` | Pinned achievements on a staff profile. | |

Key relationships on `Document`: `creator` (`created_by`), `assignedTo`
(`assigned_to`), `assignedBy`, `heldBy`, `attachments`, `comments` /
`publicComments`.

`status` is stored as a **string** on purpose so the enum can evolve without
migrations, but `statusEnum()` / `applyStatus()` keep all business logic flowing
through the typed enum. `applyStatus()` also resets the SLA-notified markers and
keeps `completed_at` coherent, so every stage change starts a clean SLA stay.

---

## 6. Feature tour

### 6.1 Citizen self-service (no account)
- **Submit a request** (`/request`) — public form with honeypot + rate-limit
  (`throttle:8,1`) anti-abuse, RA 10173 (Data Privacy Act) consent checkbox, and
  validated uploads (images/PDF/DOCX, ≤10 MB each, ≤5 files) stored on the
  **private** disk.
- **Public tracking page** (`/track/{trackingNumber}`) — shows the stage stepper,
  handler, and timeline. Status auto-polls every ~30s via `/track/.../status`.
- **Upload more documents** to an existing request (`/track/.../upload`).
- **Ask the AI assistant** (`/track/.../ask`) — see §6.5.
- Rate-limited (60/min) as defense-in-depth on top of the high-entropy number.

### 6.2 Supervisor "Requests" dashboard
Operational queue of incoming requests: review, **assign to staff**, or deny.
Department-scoped. This is the supervisor's landing page.

### 6.3 Staff desk
- **My Dashboard** (`/my-dashboard`, `StaffDashboardController`) — the requests
  assigned to *me*, with accept/decline/revision triage and the status stepper.
- **Create / edit documents**, print QR **stickers**, **undo the last scan**
  (mistakes are recoverable).
- **Staff profiles & directory** (`/staff`) — identity rail, activity feed,
  pinned highlights, quick search.
- **Per-document collaboration feed** — threaded comments with public/internal
  visibility, so internal notes never leak to the citizen view.

### 6.4 Super-admin analytics command center (Pillar: predictive analytics)
`AdminController@dashboard` + `App\Support\AdminAnalytics` compute, org-wide:
KPIs with period-over-period **deltas**, throughput trends, status distribution,
**staff workload**, **bottlenecks**, time-by-stage, an **at-risk** list (documents
trending toward an SLA breach), fastest staff, and a throughput **heatmap**. This
turns the raw log into forward-looking operational insight rather than just
history. An **immutable audit log** (`AuditLogController`, backed by Spatie
activity log) records who did what.

### 6.5 On-premise AI assistant (Pillar: AI)
`App\Support\Ai\DocumentAssistant` is a small, tightly-scoped **RAG**: the only
knowledge given to the model is the **public-safe facts of the one document** the
citizen is already viewing (stage, progress, handler, key dates). It cannot see any
other record, so it cannot leak data, and it is instructed to answer only from those
facts — which stops a small self-hosted model from hallucinating. Providers are
pluggable behind `LlmProvider`: **`OllamaProvider`** (self-hosted, e.g. `llama3.2`)
or **`NullProvider`**. If the LLM is unavailable, a **deterministic rule-based
answer** is produced from the very same facts, so the feature always works.

### 6.6 Live tracking (Pillar: real-time)
**Laravel Reverb** WebSockets broadcast `DocumentStatusUpdated` and
`DocumentCommentPosted` events. Staff dashboards and the citizen tracking page
update **without refresh** the moment a stage changes or a comment is posted. In
production this needs the Reverb server running and `wss://` open (see
`SYSTEM_REQUIREMENTS.md`).

### 6.7 SLA enforcement
Each non-terminal stage has an SLA budget in hours (`DocumentStatus::slaHours()`:
Pending 24h, In Progress 72h, In Review 48h, Approved 24h; default 48h). A single
scheduled command **`documents:check-sla`** (`CheckDocumentSla`) runs **hourly**
(`routes/console.php`), sweeps active documents, and emails **`SlaWarningMail`** /
**`SlaBreachMail`** once each per stage-stay — deduped via `sla_warning_notified_at`
/ `sla_breach_notified_at`, which `applyStatus()` resets on every stage change.
`Document::isOverdue()` measures elapsed time from `status_changed_at`; **On Hold**
pauses the clock (null budget). One hourly sweep replaces thousands of per-scan
delayed jobs — it is self-healing. Requires `php artisan schedule:run` on cron in
production.

### 6.8 Notifications (email)
`AssignmentNotice`, `TicketSubmitted`, `StatusUpdated`, `ActionNeededMail`,
`CitizenDocumentUploadMail`, `StaffMessage`, and the two SLA mails.

---

## 7. Security & privacy design

- **Enumeration resistance** — high-entropy, unambiguous base-32 tracking numbers,
  plus per-route throttling as defense-in-depth.
- **Private attachments** — all citizen/staff uploads live on the **private** disk
  and are served only through `AttachmentController` after auth + per-scope checks.
  Only QR PNGs are on the public disk.
- **Untrusted input** — the public request form uses a honeypot, strict validation,
  MIME/size limits, and an explicit **RA 10173 consent** gate.
- **Least privilege** — fine-grained Spatie permissions; org-wide power limited to
  `super_admin`; everything else scoped via `AssignmentScope` / `ScopesByDepartment`.
- **Accountability** — every meaningful action is recorded in the Spatie activity
  log and surfaced as an immutable audit trail; system events also appear in the
  per-document timeline.
- **HTTPS is functional, not optional** — browser camera QR scanning only works over
  a secure origin (see `SYSTEM_REQUIREMENTS.md`).

---

## 8. Technology stack

| Layer | Choice |
|---|---|
| Language / framework | **PHP 8.3**, **Laravel 13** |
| Database | MySQL 8 (SQLite in dev) |
| Auth / RBAC | Laravel auth + **Spatie Laravel-Permission** |
| Audit | **Spatie Laravel-Activitylog** |
| Media | **Spatie Laravel-Medialibrary** + private disk |
| QR | **SimpleSoftwareIO/simple-qrcode** (needs PHP `gd`) |
| Real-time | **Laravel Reverb** (WebSockets) |
| AI | Self-hosted **Ollama** (pluggable `LlmProvider`, rule-based fallback) |
| Frontend | **Blade + Tailwind CSS 3 + Alpine.js + Vite**, `html5-qrcode` for camera scanning |
| Background | Scheduled `documents:check-sla` (hourly) + queue worker |

Server-rendered Blade throughout — no separate SPA. The "Civic Record" UI theme
(brass/green/muted color bands defined in `DocumentStatus::band()` and
`resources/css/app.css`) gives the stepper and badges a consistent civic look.

---

## 9. Request flow at a glance

```
Citizen ──submit──▶ /request ──▶ Document(Pending) ──▶ QR + tracking # + email
                                        │
                                        ▼
                          Supervisor Requests dashboard
                          ├─ Deny ─────────────▶ Denied (terminal)
                          └─ Approve = Assign ─▶ assigned_to set (still Pending)
                                        │
                                        ▼
                             Assigned staff triage
                             ├─ Decline  ─▶ back to supervisor queue
                             ├─ Revision ─▶ Returned (citizen fixes)
                             └─ Accept   ─▶ In Progress
                                        │  (manual stepper, SLA per stage)
                                        ▼
                     In Progress → In Review → Approved → Completed ─▶ History
                             │
                             └─ Hold (SLA paused) ↔ Unhold

Every transition ▶ activity log + timeline comment + Reverb broadcast
Hourly sweep ▶ SLA warning / breach emails (deduped per stage-stay)
Citizen anytime ▶ /track/{no.} live status + AI "where is my document?"
```

---

## 10. Where to look in the code

| You want… | Start here |
|---|---|
| The lifecycle definition (stages, SLA, order) | `app/Enums/DocumentStatus.php` |
| The document model & business helpers | `app/Models/Document.php` |
| Supervisor assign/deny + staff triage/review | `app/Http/Controllers/ReviewController.php` |
| Manual stage advance / revert / hold | `app/Http/Controllers/DocumentStatusController.php` |
| Citizen online submission | `app/Http/Controllers/PublicTicketController.php` |
| Public tracking + status polling | `app/Http/Controllers/TrackController.php` |
| AI assistant | `app/Support/Ai/DocumentAssistant.php`, `OllamaProvider.php` |
| Analytics command center | `app/Http/Controllers/AdminController.php`, `app/Support/AdminAnalytics.php` |
| SLA enforcement | `app/Console/Commands/CheckDocumentSla.php`, `routes/console.php` |
| Roles / permissions | `database/seeders/RolesAndPermissionsSeeder.php` |
| Scoping rules | `app/Support/AssignmentScope.php`, `app/Http/Controllers/Concerns/ScopesByDepartment.php` |
| All routes | `routes/web.php` |

---

*Companion docs:* `SYSTEM_REQUIREMENTS.md` (hardware/OS), `DEPLOYMENT.md` (production
setup), `SETUP.md` (first run), `TESTING.md` (manual QA checklist), `CLAUDE.md`
(repo conventions — note its Architecture section predates the June 2026 overhaul).
