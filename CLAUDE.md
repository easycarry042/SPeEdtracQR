# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SPeEdtracQR is a Laravel 13 / PHP 8.3 document tracking system for government offices. Citizens submit documents (e.g. Business Permit, Cedula) and receive a QR-coded tracking number (`SPD-YYYYMMDD-NNNNN`). Staff scan documents IN/OUT at each department; the system records each move, enforces SLA timers, and exposes a public tracking page for citizens. The entire app lives in the `speed-traqr/` subdirectory.

## Working Directory

All commands must be run from `speed-traqr/`:

```bash
cd speed-traqr
```

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

# Queue worker (if running without composer dev)
php artisan queue:work

# First-time setup
composer setup
php artisan storage:link
php artisan db:seed --class=RolesAndPermissionsSeeder
```

## Architecture

### Request flow

1. **Document creation** — `DocumentWebController@store` calls `QrCodeService` to generate a unique tracking number and save a PNG QR to `storage/app/public/qrcodes/`. The QR encodes the public tracking URL.
2. **Scanning** — `ScanController@store` (POST `/api/documents/scan` via `routes/api.php`, also POST `/scan` via web) records a `DocumentScan` row. On `action=in` it sets `documents.current_department_id` and dispatches `CheckSlaJob` with a delay equal to the department's `sla_hours`. On `action=out` it looks up `routing_rules` to find the next department; if none exists the document is marked `completed`.
3. **SLA enforcement** — `CheckSlaJob` fires after `sla_hours` and sends a `SlaBreachMail`. `SendOverdueAlert` is a separate job for overdue notifications.
4. **Offline scanning** — `resources/js/offline-scanner.js` queues scans in `localStorage` when offline and POSTs them to `POST /api/scan/sync` (`ScanController@sync`) when connectivity returns.

### Key models and relationships

| Model | Table | Notes |
|---|---|---|
| `Document` | `documents` | SoftDeletes + Spatie ActivityLog. Status: `pending`, `in_transit`, `completed`. |
| `DocumentScan` | `document_scans` | `action` = `in` or `out`. Has `offline_uuid` for dedup on sync. |
| `Department` | `departments` | Has `sla_hours` column. |
| `RoutingRule` | `routing_rules` | `document_type + from_department_id → to_department_id + step_order`. Drives the routing on OUT scans. |

### Roles and permissions (Spatie)

Three roles seeded by `RolesAndPermissionsSeeder`:

- `clerk` — create documents, scan documents
- `department_head` — scan documents, view reports
- `admin` — all permissions

Default admin: `admin@speedtraqr.com` / `password123`

### Frontend stack

Blade + Tailwind CSS 3 + Alpine.js + Vite. No separate SPA; all views are server-rendered Blade. The `html5-qrcode` npm package provides camera-based QR scanning on the `/scan` page.

### Notable patterns

- **Duplicate route views**: Some views exist in both a flat form (`resources/views/scan.blade.php`) and a subdirectory form (`resources/views/scan/index.blade.php`). The router uses the subdirectory versions; the flat files are legacy and can be ignored.
- **Schema::hasColumn guards** in `ScanController@recordScan` — these exist because migrations were added incrementally and the check guards against environments that haven't run all migrations.
- `QrCodeService` requires the PHP `gd` extension. Check with `php -m | grep gd`; install via `sudo apt-get install php-gd` if missing.

## Manual Testing Checklist

See `speed-traqr/TESTING.md` for a 10-step UI flow covering: document creation, QR file check, IN/OUT scans, public tracking, dashboard, history, analytics, offline mode, and a full 3-department routing flow.
