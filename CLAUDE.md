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
| `Department` | `departments` | Has `sla_hours` column. |
| `RoutingRule` | `routing_rules` | `document_type + from_department_id → to_department_id + step_order`. Drives the routing on OUT scans. |

### Roles and permissions (Spatie)

Roles seeded by `RolesAndPermissionsSeeder`:

- `staff` — create documents, scan documents, view reports
- `receiving_staff` — scan documents (intake)
- `department_admin` — manage users (own dept), view reports, view all documents
- `super_admin` — all permissions; org-wide (not scoped to one department)

Department scoping is centralized in `App\Support\DepartmentScope`; `super_admin` is org-wide, everyone else is limited to their `department_id`.

Default admin: `admin@speedtraqr.com` (password from `ADMIN_PASSWORD`, dev fallback `password123`).

### Frontend stack

Blade + Tailwind CSS 3 + Alpine.js + Vite. No separate SPA; all views are server-rendered Blade. The `html5-qrcode` npm package provides camera-based QR scanning on the `/scan` page.

### Notable patterns

- **Active layout**: `resources/views/layouts/app.blade.php` is the live shell (collapsible icon+label sidebar). Track/scan views live under their subdirectories (`resources/views/track/`, `resources/views/scan/`).
- `QrCodeService` requires the PHP `gd` extension. Check with `php -m | grep gd`; install via `sudo apt-get install php-gd` if missing.

## Manual Testing Checklist

See `TESTING.md` (repo root) for a 10-step UI flow covering: document creation, QR file check, IN/OUT scans, public tracking, dashboard, history, analytics, offline mode, and a full 3-department routing flow.
