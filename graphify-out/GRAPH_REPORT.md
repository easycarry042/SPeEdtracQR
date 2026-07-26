# Graph Report - .  (2026-07-03)

## Corpus Check
- 315 files · ~138,480 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1173 nodes · 2018 edges · 233 communities (211 shown, 22 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 177 edges (avg confidence: 0.81)
- Token cost: 158,561 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Realtime Broadcast Events + Assignment Flow|Realtime Broadcast Events + Assignment Flow]]
- [[_COMMUNITY_SLA Jobs, Notifications & Mail|SLA Jobs, Notifications & Mail]]
- [[_COMMUNITY_DocumentStatus State Machine|DocumentStatus State Machine]]
- [[_COMMUNITY_Document Attachment UploadStorage|Document Attachment Upload/Storage]]
- [[_COMMUNITY_Feature Test Suite Analytics & Auth|Feature Test Suite: Analytics & Auth]]
- [[_COMMUNITY_Login Auditing & Service Providers|Login Auditing & Service Providers]]
- [[_COMMUNITY_Admin Dashboard & Analytics Support|Admin Dashboard & Analytics Support]]
- [[_COMMUNITY_System Review Findings & Known Issues|System Review Findings & Known Issues]]
- [[_COMMUNITY_User Model & Password Confirmation Tests|User Model & Password Confirmation Tests]]
- [[_COMMUNITY_Session Auth & Profile Controllers|Session Auth & Profile Controllers]]
- [[_COMMUNITY_NPM Frontend Dependencies|NPM Frontend Dependencies]]
- [[_COMMUNITY_Document Comment Thread|Document Comment Thread]]
- [[_COMMUNITY_RolePermission Middleware|Role/Permission Middleware]]
- [[_COMMUNITY_Core Eloquent Models & Traits|Core Eloquent Models & Traits]]
- [[_COMMUNITY_Database Seeders|Database Seeders]]
- [[_COMMUNITY_Production Deployment Stack|Production Deployment Stack]]
- [[_COMMUNITY_Core Document Web Flow (Design Doc)|Core Document Web Flow (Design Doc)]]
- [[_COMMUNITY_AI Document Assistant|AI Document Assistant]]
- [[_COMMUNITY_Staff Profile & Workload KPIs|Staff Profile & Workload KPIs]]
- [[_COMMUNITY_User Management Controller|User Management Controller]]
- [[_COMMUNITY_Blade Layout Components|Blade Layout Components]]
- [[_COMMUNITY_Legacy RoutingScan Architecture (deprecated)|Legacy Routing/Scan Architecture (deprecated)]]
- [[_COMMUNITY_Roles & Department Scoping|Roles & Department Scoping]]
- [[_COMMUNITY_Manual UI Testing Checklist|Manual UI Testing Checklist]]
- [[_COMMUNITY_composer.json Metadata|composer.json Metadata]]
- [[_COMMUNITY_Composer Runtime Dependencies|Composer Runtime Dependencies]]
- [[_COMMUNITY_Composer Scripts|Composer Scripts]]
- [[_COMMUNITY_Department Retirement DB Snapshots|Department Retirement DB Snapshots]]
- [[_COMMUNITY_ScanController cluster|ScanController cluster]]
- [[_COMMUNITY_documentscheck-sla scheduled command cluster|documents:check-sla scheduled command cluster]]
- [[_COMMUNITY_Composer Dev Dependencies|Composer Dev Dependencies]]
- [[_COMMUNITY_Admin Dashboard Feature Tests|Admin Dashboard Feature Tests]]
- [[_COMMUNITY_ConfirmablePasswordController cluster|ConfirmablePasswordController cluster]]
- [[_COMMUNITY_NewPasswordController cluster|NewPasswordController cluster]]
- [[_COMMUNITY_PasswordResetLinkController cluster|PasswordResetLinkController cluster]]
- [[_COMMUNITY_RegisteredUserController cluster|RegisteredUserController cluster]]
- [[_COMMUNITY_pestphppest-plugin cluster|pestphp/pest-plugin cluster]]
- [[_COMMUNITY_Production .env configuration cluster|Production .env configuration cluster]]
- [[_COMMUNITY_AuthorizationTest cluster|AuthorizationTest cluster]]
- [[_COMMUNITY_DocumentHoldTest cluster|DocumentHoldTest cluster]]
- [[_COMMUNITY_StaffProfileEnhancementsTest cluster|StaffProfileEnhancementsTest cluster]]
- [[_COMMUNITY_AuditLogController cluster|AuditLogController cluster]]
- [[_COMMUNITY_EmailVerificationNotificationController cluster|EmailVerificationNotificationController cluster]]
- [[_COMMUNITY_EmailVerificationPromptController cluster|EmailVerificationPromptController cluster]]
- [[_COMMUNITY_UserFactory cluster|UserFactory cluster]]
- [[_COMMUNITY_SETUP.md — First-run Setup Steps cluster|SETUP.md — First-run Setup Steps cluster]]
- [[_COMMUNITY_AttachmentAccessTest cluster|AttachmentAccessTest cluster]]
- [[_COMMUNITY_StaffProfileSmokeTest cluster|StaffProfileSmokeTest cluster]]
- [[_COMMUNITY_PasswordController cluster|PasswordController cluster]]
- [[_COMMUNITY_VerifyEmailController cluster|VerifyEmailController cluster]]
- [[_COMMUNITY_CitizenController cluster|CitizenController cluster]]
- [[_COMMUNITY_autoload cluster|autoload cluster]]
- [[_COMMUNITY_CheckDocumentSlaTest cluster|CheckDocumentSlaTest cluster]]
- [[_COMMUNITY_DocumentOverdueTest cluster|DocumentOverdueTest cluster]]
- [[_COMMUNITY_PublicController cluster|PublicController cluster]]
- [[_COMMUNITY_2026_06_30_180000_drop_department_foreign_keys cluster|2026_06_30_180000_drop_department_foreign_keys cluster]]
- [[_COMMUNITY_documents.partials.create-modal cluster|documents.partials.create-modal cluster]]
- [[_COMMUNITY_profile.partials.delete-user-form cluster|profile.partials.delete-user-form cluster]]
- [[_COMMUNITY_Claude Code (AI coding agent) cluster|Claude Code (AI coding agent) cluster]]
- [[_COMMUNITY_profile.blade cluster|profile.blade cluster]]
- [[_COMMUNITY_autoload-dev cluster|autoload-dev cluster]]
- [[_COMMUNITY_extra cluster|extra cluster]]
- [[_COMMUNITY_QR-coded Tracking Number Feature cluster|QR-coded Tracking Number Feature cluster]]
- [[_COMMUNITY_admin.partials.donut cluster|admin.partials.donut cluster]]
- [[_COMMUNITY_documents.partials.created-card cluster|documents.partials.created-card cluster]]
- [[_COMMUNITY_dashboard.blade cluster|dashboard.blade cluster]]
- [[_COMMUNITY_dashboard.blade cluster|dashboard.blade cluster]]
- [[_COMMUNITY_show.blade cluster|show.blade cluster]]
- [[_COMMUNITY_deploy.sh cluster|deploy.sh cluster]]
- [[_COMMUNITY_setup-mysql.sh cluster|setup-mysql.sh cluster]]
- [[_COMMUNITY_tailwind.config.js cluster|tailwind.config.js cluster]]
- [[_COMMUNITY_SPeEdtracQR Brand Logo|SPeEdtracQR Brand Logo]]

## God Nodes (most connected - your core abstractions)
1. `Document` - 156 edges
2. `User` - 118 edges
3. `TestCase` - 49 edges
4. `Controller` - 44 edges
5. `AdminAnalytics` - 26 edges
6. `AssignmentScope` - 26 edges
7. `DocumentCommentPosted` - 22 edges
8. `DocumentStatusUpdated` - 22 edges
9. `SYSTEM_REVIEW.md — System Review & Planning Notes` - 22 edges
10. `docs/SYSTEM_DESIGN.md — System Design & Feature Guide` - 21 edges

## Surprising Connections (you probably didn't know these)
- `up()` --calls--> `Document`  [INFERRED]
  database/migrations/2026_06_03_020000_backfill_route_steps_from_routing_rules.php → app/Models/Document.php
- `CLAUDE.md wrongly states app lives in speed-traqr/` --conceptually_related_to--> `CLAUDE.md — Project Guidance`  [INFERRED]
  SYSTEM_REVIEW.md → CLAUDE.md
- `Document model` --conceptually_related_to--> `Document model (design doc)`  [INFERRED]
  CLAUDE.md → docs/SYSTEM_DESIGN.md
- `June 2026 model overhaul: scanning route to assignment + manual stages` --conceptually_related_to--> `Department model`  [INFERRED]
  docs/SYSTEM_DESIGN.md → CLAUDE.md
- `Reverb server (php artisan reverb:start)` --conceptually_related_to--> `Laravel Reverb (design doc)`  [INFERRED]
  DEPLOYMENT.md → docs/SYSTEM_DESIGN.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Pluggable LLM Provider Pattern for AI Assistant** — docs_system_design_md_documentassistant, docs_system_design_md_llmprovider, docs_system_design_md_ollamaprovider, docs_system_design_md_nullprovider [EXTRACTED 1.00]
- **Scheduled SLA Enforcement Flow** — docs_system_design_md_checkdocumentsla_command, docs_system_design_md_slawarningmail, docs_system_design_md_slabreachmail, docs_system_design_md_document_model, docs_system_design_md_documentstatus_enum [EXTRACTED 1.00]
- **10-Step UI + Flow Testing Checklist** — testing_md_new_submission_flow, testing_md_qr_code_file_check, testing_md_scan_in, testing_md_track_public, testing_md_scan_out, testing_md_dashboard_check, testing_md_history_check, testing_md_analytics_check, testing_md_offline_scan, testing_md_three_department_flow [EXTRACTED 1.00]

## Communities (233 total, 22 thin omitted)

### Community 0 - "Realtime Broadcast Events + Assignment Flow"
Cohesion: 0.06
Nodes (23): DocumentCommentPosted, DocumentStatusUpdated, AssignmentController, Request, CommentController, Request, DocumentStatusController, DocumentStatus (+15 more)

### Community 1 - "SLA Jobs, Notifications & Mail"
Cohesion: 0.06
Nodes (32): CheckDocumentSla, MoveToMysql, ActionNeededMail, Content, Envelope, AssignmentNotice, Content, Envelope (+24 more)

### Community 2 - "DocumentStatus State Machine"
Cohesion: 0.06
Nodes (22): activeValues(), fromLoose(), next(), position(), previous(), values(), AnalyticsController, Request (+14 more)

### Community 3 - "Document Attachment Upload/Storage"
Cohesion: 0.07
Nodes (15): AttachmentController, Request, CitizenDocumentUploadController, Request, storeAttachmentsForDocument(), DocumentWebController, Request, Request (+7 more)

### Community 4 - "Feature Test Suite: Analytics & Auth"
Cohesion: 0.08
Nodes (11): BaseTestCase, RefreshDatabase, AnalyticsChartTest, AuthenticationTest, EmailVerificationTest, PasswordResetTest, PasswordUpdateTest, RegistrationTest (+3 more)

### Community 5 - "Login Auditing & Service Providers"
Cohesion: 0.08
Nodes (10): LogUserLogin, LogUserLogout, AppServiceProvider, NullProvider, OllamaProvider, LlmProvider, Login, Logout (+2 more)

### Community 6 - "Admin Dashboard & Analytics Support"
Cohesion: 0.16
Nodes (6): AdminController, Request, AdminAnalytics, Builder, Carbon, Collection

### Community 7 - "System Review Findings & Known Issues"
Cohesion: 0.11
Nodes (31): App\Enums\DocumentStatus, public/robots.txt — allow-all crawler policy, General accessibility (AA) pass needed, CheckSlaJob doesn't verify elapsed time, CLAUDE.md wrongly states app lives in speed-traqr/, Dead code: legacy views and controllers, Default admin admin@speedtraqr.com / password123, SYSTEM_REVIEW.md — System Review & Planning Notes (+23 more)

### Community 8 - "User Model & Password Confirmation Tests"
Cohesion: 0.10
Nodes (6): User, Authenticatable, PasswordConfirmationTest, DocumentAttachmentUploadTest, DocumentEditTest, ProfileTest

### Community 9 - "Session Auth & Profile Controllers"
Cohesion: 0.12
Nodes (11): AuthenticatedSessionController, RedirectResponse, Request, View, RedirectResponse, Request, View, ProfileController (+3 more)

### Community 10 - "NPM Frontend Dependencies"
Cohesion: 0.09
Nodes (21): dependencies, axios, html5-qrcode, laravel-echo, pusher-js, devDependencies, alpinejs, autoprefixer (+13 more)

### Community 11 - "Document Comment Thread"
Cohesion: 0.14
Nodes (6): DocumentComment, BelongsTo, BelongsTo, StaffHighlight, HasMany, Model

### Community 12 - "Role/Permission Middleware"
Cohesion: 0.16
Nodes (11): EnsureHasPermission, Closure, Request, Response, EnsureHasRole, Closure, Request, Response (+3 more)

### Community 13 - "Core Eloquent Models & Traits"
Cohesion: 0.13
Nodes (7): HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes, StaffAssignmentAcceptTest, UserArchiveDeleteTest

### Community 14 - "Database Seeders"
Cohesion: 0.14
Nodes (7): DatabaseSeeder, RolesAndPermissionsSeeder, RoleSeeder, TeamUsersSeeder, UserSeeder, Seeder, WithoutModelEvents

### Community 15 - "Production Deployment Stack"
Cohesion: 0.13
Nodes (19): scripts/deploy.sh, DEPLOYMENT.md — Deployment Guide, MySQL 8+ database, Nginx + PHP-FPM web server, scripts/nginx-speedtraqr.conf.example, PHP-FPM 8.3, Queue worker (database connection), Reverb server (php artisan reverb:start) (+11 more)

### Community 16 - "Core Document Web Flow (Design Doc)"
Cohesion: 0.16
Nodes (16): DocumentWebController@store, QrCodeService, App\Support\AdminAnalytics, AdminController, App\Support\AssignmentScope, AuditLogController, docs/SYSTEM_DESIGN.md — System Design & Feature Guide, Document model (design doc) (+8 more)

### Community 17 - "AI Document Assistant"
Cohesion: 0.22
Nodes (5): DocumentAssistantController, Request, DocumentAssistant, LlmProvider, JsonResponse

### Community 18 - "Staff Profile & Workload KPIs"
Cohesion: 0.22
Nodes (3): Carbon, Collection, StaffProfile

### Community 20 - "Blade Layout Components"
Cohesion: 0.23
Nodes (7): AppLayout, View, CitizenLayout, View, GuestLayout, View, Component

### Community 21 - "Legacy Routing/Scan Architecture (deprecated)"
Cohesion: 0.24
Nodes (13): resources/views/layouts/app.blade.php, AttachmentController, Department model, CLAUDE.md — Project Guidance, Document::isOverdue(), Document model, DocumentScan model, html5-qrcode npm package (+5 more)

### Community 22 - "Roles & Department Scoping"
Cohesion: 0.23
Nodes (12): department_admin role, App\Support\DepartmentScope, receiving_staff role, RolesAndPermissionsSeeder, staff role, super_admin role, receiving_staff role (design doc), RolesAndPermissionsSeeder (design doc) (+4 more)

### Community 23 - "Manual UI Testing Checklist"
Cohesion: 0.18
Nodes (11): Step 8 — Analytics Check, Step 6 — Dashboard Check, TESTING.md — UI + Flow Testing Checklist, Step 7 — History Check, Step 1 — New Submission, Step 9 — Offline Scan, Step 2 — QR Code File Check, Step 3 — Scan IN (+3 more)

### Community 24 - "composer.json Metadata"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 25 - "Composer Runtime Dependencies"
Cohesion: 0.22
Nodes (9): require, laravel/framework, laravel/reverb, laravel/tinker, php, simplesoftwareio/simple-qrcode, spatie/laravel-activitylog, spatie/laravel-medialibrary (+1 more)

### Community 26 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 27 - "Department Retirement DB Snapshots"
Cohesion: 0.22
Nodes (8): backup_department_notifications_snapshot, backup_departments_snapshot, backup_document_attachments_department_snapshot, backup_document_route_steps_snapshot, backup_document_scans_snapshot, backup_documents_current_department_snapshot, backup_routing_rules_snapshot, backup_users_department_snapshot

### Community 29 - "documents:check-sla scheduled command cluster"
Cohesion: 0.32
Nodes (8): documents:check-sla scheduled command, SlaBreachMail, SlaWarningMail, Cron scheduler (schedule:run), documents:check-sla (deployment context), documents:check-sla / CheckDocumentSla, SlaBreachMail (design doc), SlaWarningMail (design doc)

### Community 30 - "Composer Dev Dependencies"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 32 - "ConfirmablePasswordController cluster"
Cohesion: 0.43
Nodes (4): ConfirmablePasswordController, RedirectResponse, Request, View

### Community 33 - "NewPasswordController cluster"
Cohesion: 0.48
Nodes (4): NewPasswordController, RedirectResponse, Request, View

### Community 34 - "PasswordResetLinkController cluster"
Cohesion: 0.43
Nodes (4): PasswordResetLinkController, RedirectResponse, Request, View

### Community 35 - "RegisteredUserController cluster"
Cohesion: 0.43
Nodes (4): RedirectResponse, Request, View, RegisteredUserController

### Community 36 - "pestphp/pest-plugin cluster"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 37 - "Production .env configuration cluster"
Cohesion: 0.29
Nodes (7): Production .env configuration, Ollama self-hosted LLM (llama3.2), App\Support\Ai\DocumentAssistant, LlmProvider interface, NullProvider, OllamaProvider, Self-hosted Ollama AI assistant (requirements doc)

### Community 41 - "AuditLogController cluster"
Cohesion: 0.47
Nodes (3): AuditLogController, Request, Controller

### Community 42 - "EmailVerificationNotificationController cluster"
Cohesion: 0.47
Nodes (3): EmailVerificationNotificationController, RedirectResponse, Request

### Community 43 - "EmailVerificationPromptController cluster"
Cohesion: 0.53
Nodes (4): EmailVerificationPromptController, RedirectResponse, Request, View

### Community 44 - "UserFactory cluster"
Cohesion: 0.47
Nodes (3): UserFactory, Factory, static

### Community 45 - "SETUP.md — First-run Setup Steps cluster"
Cohesion: 0.33
Nodes (6): SETUP.md — First-run Setup Steps, .env.example, PHP exif extension, PHP gd extension, simplesoftwareio/simple-qrcode package, php artisan storage:link

### Community 48 - "PasswordController cluster"
Cohesion: 0.60
Nodes (3): PasswordController, RedirectResponse, Request

### Community 49 - "VerifyEmailController cluster"
Cohesion: 0.60
Nodes (3): RedirectResponse, VerifyEmailController, EmailVerificationRequest

### Community 51 - "autoload cluster"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 56 - "documents.partials.create-modal cluster"
Cohesion: 0.50
Nodes (3): documents.partials.create-modal, layouts.partials.header-actions, layouts.partials.topnav-links

### Community 57 - "profile.partials.delete-user-form cluster"
Cohesion: 0.50
Nodes (3): profile.partials.delete-user-form, profile.partials.update-password-form, profile.partials.update-profile-information-form

### Community 58 - "Claude Code (AI coding agent) cluster"
Cohesion: 0.67
Nodes (4): Claude Code (AI coding agent), README.md — Laravel Framework Readme, Laravel Boost (AI agent tooling), Laravel Framework

### Community 59 - "profile.blade cluster"
Cohesion: 0.50
Nodes (3): staff.partials.composer, staff.partials.feed-entry, staff.partials.hold-note

### Community 61 - "autoload-dev cluster"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 62 - "extra cluster"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 63 - "QR-coded Tracking Number Feature cluster"
Cohesion: 0.67
Nodes (3): QR-coded Tracking Number Feature, SPeEdtracQR Branding / Visual Identity, SPeEdtracQR App Icon (QR Arrow Logo)

## Knowledge Gaps
- **122 isolated node(s):** `$schema`, `name`, `type`, `description`, `keywords` (+117 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **22 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Document` connect `Realtime Broadcast Events + Assignment Flow` to `SLA Jobs, Notifications & Mail`, `DocumentStatus State Machine`, `Document Attachment Upload/Storage`, `Login Auditing & Service Providers`, `Admin Dashboard & Analytics Support`, `User Model & Password Confirmation Tests`, `Document Comment Thread`, `Core Eloquent Models & Traits`, `AI Document Assistant`, `Staff Profile & Workload KPIs`, `ScanController cluster`, `Admin Dashboard Feature Tests`, `DocumentHoldTest cluster`, `StaffProfileEnhancementsTest cluster`, `AttachmentAccessTest cluster`, `StaffProfileSmokeTest cluster`, `CheckDocumentSlaTest cluster`, `DocumentOverdueTest cluster`, `PublicController cluster`?**
  _High betweenness centrality (0.140) - this node is a cross-community bridge._
- **Why does `Controller` connect `AuditLogController cluster` to `Realtime Broadcast Events + Assignment Flow`, `ConfirmablePasswordController cluster`, `DocumentStatus State Machine`, `Document Attachment Upload/Storage`, `NewPasswordController cluster`, `PasswordResetLinkController cluster`, `Admin Dashboard & Analytics Support`, `RegisteredUserController cluster`, `Session Auth & Profile Controllers`, `EmailVerificationNotificationController cluster`, `EmailVerificationPromptController cluster`, `PasswordController cluster`, `VerifyEmailController cluster`, `CitizenController cluster`, `User Management Controller`, `AI Document Assistant`, `PublicController cluster`, `ScanController cluster`?**
  _High betweenness centrality (0.075) - this node is a cross-community bridge._
- **Why does `User` connect `User Model & Password Confirmation Tests` to `Realtime Broadcast Events + Assignment Flow`, `SLA Jobs, Notifications & Mail`, `DocumentStatus State Machine`, `Document Attachment Upload/Storage`, `Feature Test Suite: Analytics & Auth`, `Login Auditing & Service Providers`, `Admin Dashboard & Analytics Support`, `Core Eloquent Models & Traits`, `Database Seeders`, `Staff Profile & Workload KPIs`, `User Management Controller`, `Admin Dashboard Feature Tests`, `RegisteredUserController cluster`, `AuthorizationTest cluster`, `DocumentHoldTest cluster`, `StaffProfileEnhancementsTest cluster`, `AuditLogController cluster`, `AttachmentAccessTest cluster`, `StaffProfileSmokeTest cluster`, `CheckDocumentSlaTest cluster`, `DocumentOverdueTest cluster`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **Are the 36 inferred relationships involving `Document` (e.g. with `.handle()` and `.dashboard()`) actually correct?**
  _`Document` has 36 INFERRED edges - model-reasoned connections that need verification._
- **Are the 46 inferred relationships involving `User` (e.g. with `.assign()` and `.assignableStaff()`) actually correct?**
  _`User` has 46 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _124 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Realtime Broadcast Events + Assignment Flow` be split into smaller, more focused modules?**
  _Cohesion score 0.058426966292134834 - nodes in this community are weakly interconnected._