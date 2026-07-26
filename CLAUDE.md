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
