# SPeEdtracQR — System Design & Feature Guide

A complete, plain-language explanation of what SPeEdtracQR is, who uses it, how a
request moves through it, and how the whole thing is built. This is the "read this
first" companion to the more focused docs (`SYSTEM_REQUIREMENTS.md`, `DEPLOYMENT.md`,
`SETUP.md`, `TESTING.md`). It is grounded in the code as it actually stands today —
including the CTCAO tourism workflow and the modern Next.js architecture.

---

## 1. What the system is

**SPeEdtracQR** is a document and service-tracking system for local government offices,
currently specialized for the **City Tourism, Culture and Arts Office (CTCAO)**. A citizen,
barangay, or another city office submits a request and immediately receives a **QR-coded tracking number** in the form:

```
SPD-YYYYMMDD-XXXXXX
```

The six-character suffix is drawn from an unambiguous base-32 alphabet with high
entropy, so tracking numbers cannot be casually guessed or enumerated. From that
point on:

- The **citizen or requester** can watch the request move through clear, human-readable stages
  (**Pending → Ongoing → Done**) on a public page — no account, no app, no login.
- A **supervisor (S/Admin)** reviews incoming requests where the usual handler for each
  request type is pre-filled from an assignment table, allowing one-click confirmation.
- The **assigned staff member** accepts the request (moving it to **Ongoing** and notifying
  supervisors), performs the service or verifies requirements, and finishes the request
  (moving it to **Done** and notifying supervisors).
- Borrowed equipment (flags, spider banner) and venue reservations (City Plaza, Gateway Park)
  are tracked via the Resources lending desk; handing over borrowed items automatically marks
  the request as **Done**.

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

The lifecycle is defined in the system domain models (`document-status.ts` / `App\Enums\DocumentStatus`). It
is the single source of truth for labels, ordering, SLA budgets, and colors.

### 4.1 The stages

**Forward line (CTCAO Workflow)** — the streamlined 3-stage stepper shown to requesters and staff:

```
Pending → Ongoing → Done
```

*(Note: Older rows in `In Review` or `Approved` are retired and map to the `Ongoing` stage for smooth backward compatibility).*

**Side states** (off the forward line):

- **Returned / For Revision** — sent back to the citizen or filing office for corrections.
- **On Hold** — blocked or waiting; the **SLA clock is paused**.
- **Denied** — supervisor rejected the request (terminal).
- **Withdrawn** — the filing office cancelled/closed an internal request after a return (terminal).

`Done`, `Denied`, and `Withdrawn` are **terminal** (the SLA clock stops).

### 4.2 The CTCAO journey, step by step (4 steps)

1. **Request filed.** A citizen or another city office/barangay fills in the online form (`/request`):
   - Selects from the 12 **CTCAO services** (venue reservations, events set-up, item borrowing, layout requests, etc.).
   - Requesters can provide an optional **Office or organization** name.
   - Answers the **service-specific question** (e.g., venue location for events set-up, size/dimensions for layouts).
   - For equipment borrowing (Spider Banner, Philippine Flag, San Pedro Seal Flag) or venue reservation (City Plaza, Gateway Park), dates and times are chosen; conflicts are blocked immediately. Quantity is optional for services.
   - Files are uploaded, privacy consent accepted, and the request generates an `SPD-YYYYMMDD-XXXXXX` tracking number with a QR code. Status starts at **Pending**.

2. **S/Admin assigns.** The office supervisor (Maria Santos, Carmela Torres, or Dennis Aquino) opens the request on the dashboard:
   - The **usual handler** for that request type is already pre-selected from the CTCAO assignment table.
   - The supervisor confirms with one click, reassigns, or Denies with a mandatory reason.

3. **Staff accepts.** The assigned staff member sees the request on their Dashboard:
   - Clicking **Accept** changes the status to **Ongoing** and immediately notifies the office supervisors via bell and email.
   - Alternatively, staff may **Decline** (returning it to the supervisor) or **Request revision** (status → **Returned**).
   - Staff works on the request, adding notes or attachments, verifying required files, and approving reservations.

4. **Staff finishes.**
   - For services: Staff clicks **"Finish request"** (with an optional note). The status becomes **Done**, and the supervisors are notified again via bell and email. The public tracking page clearly displays **Done** without prompting the requester to pick up physical paperwork.
   - For borrowed items: When the borrower arrives to collect items, staff hands them over on the Resources lending desk (or scans the QR code). Releasing the items automatically marks the request **Done**.
   - When borrowed items are returned, staff records items returned, damaged, or missing. When all items are returned, the loan closes and stock is restored.

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

### 6.7 CTCAO Tourism Services & Assignment Table

The system focuses on the City Tourism, Culture and Arts Office (CTCAO) catalog, mapping each request type to its designated staff members for accelerated assignment:

| Request Type | Category | Usually Handled By |
|---|---|---|
| **City Plaza reservation** | Facility Reservation | Ana Cruz (Jhacky Ong) / Bea Santiago (Susi Miranda) |
| **Gateway Park reservation** | Facility Reservation | Ana Cruz / Bea Santiago |
| **Other venue request** | Facility Reservation | Ana Cruz / Bea Santiago |
| **Events set-up** | Service | Mark Dizon (Sergio Machado) |
| **Spider banner borrowing** | Equipment Borrowing | Ana Cruz |
| **Philippine flag borrowing** | Equipment Borrowing | Ana Cruz |
| **Philippine flag requisition (to keep)** | Service (Stock not deducted) | Ana Cruz |
| **San Pedro seal flag borrowing** | Equipment Borrowing | Ana Cruz |
| **Lei making** | Service | Mark Dizon |
| **Layout request** | Service | Joy Navarro (Jezther Ygnacio) |
| **Social media posting** | Service | Kevin Ramos (Michael Tabuyan) |
| **Program assistance** | Service | Mark Dizon |

**Office Leadership & Personnel:**
- **Supervisors (S/Admin):** Maria Santos, Carmela Torres, Dennis Aquino.
- **Other Staff:** Tess Gomez, Leo Pascual, Ella Manalo.
- **Non-CTCAO services** (business permit, cedula, civil registry) are deactivated by default to keep the interface focused on tourism operations, but can be reactivated on the Request Types admin page.

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
