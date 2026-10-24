# SPeED TraQR — Data Dictionary

Database: `speedtraqr` (MySQL 8.0). Generated from the live schema on 2026-07-30.

All `id` columns are auto-incrementing primary keys. All `created_at` / `updated_at` columns are
Laravel timestamps recording when the row was inserted and last modified.

---

## Narrative Description

Table 13 shows the comprehensive database schema designed to manage the end-to-end lifecycle of City
Government service requests, user access, and backend system infrastructure. At the core of the
application, the `documents` table captures and tracks the essential details of a submitted request,
recording the requester's identifiers, the service being applied for, the handling department, the
staff member assigned to it, and the current processing stage. Each request carries its own checklist
of prerequisites in the `document_requirements` table, where staff record whether an uploaded
submission was approved, rejected, or returned for revision, while the `document_attachments` table
stores the supporting files and physical forms provided with a request on a private disk reachable
only through an access-checked controller. Correspondence between the office and the requester is
kept in the `document_comments` table, which separates internal staff notes from the
citizen-visible message thread. Because many requests involve a physical folder that changes hands,
the `document_custody_events` table maintains a chain-of-custody trail recording who took possession
of a document, when, and whether custody was captured by QR scan or by a justified manual override.
Requests that require multi-office clearance are routed through the `request_steps` table, which
holds the ordered approval sequence, the acting officer, the e-signature applied, and the remarks
behind any return or denial. Reservations of facilities and equipment are recorded in the `bookings`
table, and notable staff accomplishments are surfaced through the `staff_highlights` table.

The behaviour of these transactions is governed by a set of reference and configuration tables. The
`departments` table defines the various City Government offices, while the `request_types` table
catalogues every service the public may apply for, its category, and the department it is routed to;
`request_type_requirements` stores the reusable checklist template that is copied onto each new
request, and `resources` defines the bookable facilities and equipment. Recurring approval paths are
standardized through the `route_templates` and `route_template_steps` tables, which describe the
office sequence and the conditions — such as a monetary threshold — that determine whether a given
step applies to a request.

To securely manage the personnel interacting with these records, the `users` table handles the
authentication credentials, department assignment, e-signature, and badge identity code of City
Government employees. System access is strictly enforced through the `roles` and `permissions`
tables, which define access levels and granular system capabilities, and these rights are conferred
through the `model_has_roles`, `role_has_permissions`, and `model_has_permissions` tables, ensuring
that individuals only interact with authorized data. Account security is further supported by the
`password_reset_tokens` table, which facilitates secure password recovery, and the `sessions` table,
which manages active user logins.

For transparency and accountability, the `activity_log` table maintains a comprehensive audit trail
of system events, recording exactly who altered a record and when, while the `notifications` table
delivers in-app alerts to staff about new assignments, pending approvals, and service-level
warnings. Finally, backend application stability is sustained by a suite of infrastructure tables.
The `jobs`, `job_batches`, and `failed_jobs` tables queue, group, and monitor automated background
processing tasks such as outgoing email. Application performance is optimized by the `cache` and
`cache_locks` tables, which store temporary data for faster loading and prevent concurrent data race
conditions, and the `migrations` table records all structural updates made to the database schema
over time. System health and performance are observed through the
`health_check_result_history_items` table and the `pulse_entries`, `pulse_aggregates`, and
`pulse_values` tables, which capture raw, rolled-up, and latest-value operational metrics
respectively.

---

## 1. Core Application Tables

| Table Name | Field Name | Data Type | Description |
|---|---|---|---|
| documents | id | bigint unsigned | Primary key of the request/document record. |
| documents | tracking_number | varchar(255) | Unique public tracking code in the format `SPD-YYYYMMDD-XXXXXX`; encoded into the QR image and used on the public tracking page. |
| documents | document_type | varchar(255) | Name of the request type being applied for (e.g. Business Permit, Cedula). |
| documents | citizen_name | varchar(255) | Full name of the citizen who filed the request. Null for internal department-to-department requests. |
| documents | citizen_contact | varchar(255) | Contact number of the citizen for follow-ups and pickup notices. |
| documents | purpose | varchar(255) | Stated reason the document or service is being requested. |
| documents | quantity | int unsigned | Number of copies or units requested. |
| documents | needed_by | date | Date the requester needs the document or service completed by. |
| documents | description | text | Free-text details or special instructions supplied at filing. |
| documents | status | varchar(255) | Current workflow stage: `pending`, `in_progress`, `in_review`, `approved`, `completed`, `returned`, `on_hold`, or `denied`. |
| documents | created_by | bigint unsigned | Foreign key to `users.id` — the staff member who encoded the request. Null for citizen-submitted online requests. |
| documents | remarks | text | Internal staff notes about the request. |
| documents | completed_at | timestamp | Date and time the request reached the completed stage. |
| documents | created_at | timestamp | Date and time the request was filed. |
| documents | updated_at | timestamp | Date and time the record was last modified. |
| documents | deleted_at | timestamp | Soft-delete marker; non-null means the record is archived and hidden from normal listings. |
| documents | attachment_path | varchar(255) | Storage path of the primary supporting file, held on the private disk. |
| documents | qr_code_path | varchar(255) | Storage path of the generated QR code image on the public disk. |
| documents | sla_warning_notified_at | timestamp | When the SLA warning email was sent; prevents duplicate warnings for the same stay. |
| documents | sla_breach_notified_at | timestamp | When the SLA breach email was sent; prevents duplicate breach alerts for the same stay. |
| documents | assigned_to | bigint unsigned | Foreign key to `users.id` — the staff member currently responsible for processing the request. |
| documents | assigned_by | bigint unsigned | Foreign key to `users.id` — the supervisor who made the assignment. |
| documents | department_id | bigint unsigned | Foreign key to `departments.id` — the department currently handling the request. |
| documents | assigned_at | timestamp | When the request was assigned to the current staff member. |
| documents | accepted_at | timestamp | When the assigned staff member acknowledged and accepted the workload. |
| documents | claimed_at | timestamp | When the finished document was physically claimed by the requester. |
| documents | released_by | bigint unsigned | Foreign key to `users.id` — the staff member who released the document to the requester. |
| documents | status_changed_at | timestamp | When the status column last changed; drives elapsed-time and SLA calculations. |
| documents | source | varchar(255) | Intake channel: `walk_in` for counter-encoded requests, `online` for the public web form. |
| documents | origin | varchar(20) | Requester category: `external` for citizen requests, `internal` for department-to-department requests. |
| documents | requesting_department_id | bigint unsigned | Foreign key to `departments.id` — the originating department on an internal request. |
| documents | amount | decimal(14,2) | Monetary value attached to the request; used to select the correct approval route on procurement requests. |
| documents | citizen_email | varchar(255) | Email address used to send status-update notifications to the requester. |
| documents | notify_citizen | tinyint(1) | Boolean flag; when true the system emails the requester on each status change. |
| documents | sla_breached_at | timestamp | When the request first exceeded its service-level deadline. |
| documents | status_before_hold | varchar(255) | Status the request held immediately before being put on hold, so it can be restored on resume. |
| documents | hold_reason | text | Explanation of why processing was paused. |
| documents | hold_until | date | Date the hold is expected to be lifted. |
| documents | blocked_by | varchar(255) | Party the hold is waiting on: `citizen`, `internal`, or `external`. |
| documents | held_at | timestamp | When the request was placed on hold. |
| documents | held_by | bigint unsigned | Foreign key to `users.id` — the staff member who placed the hold. |
| documents | decided_by | bigint unsigned | Foreign key to `users.id` — the supervisor who approved or denied the request. |
| documents | decided_at | timestamp | When the approval or denial decision was recorded. |
| document_requirements | id | bigint unsigned | Primary key of the per-request requirement checklist item. |
| document_requirements | document_id | bigint unsigned | Foreign key to `documents.id` — the request this checklist item belongs to. |
| document_requirements | request_type_requirement_id | bigint unsigned | Foreign key to `request_type_requirements.id` — the template item this was copied from. |
| document_requirements | label | varchar(255) | Name of the required document (e.g. Valid ID, Barangay Clearance). |
| document_requirements | is_mandatory | tinyint(1) | Boolean flag; when true the request cannot proceed until this item is approved. |
| document_requirements | review_status | varchar(255) | Outcome of staff review: `pending`, `approved`, `needs_revision`, or `rejected`. |
| document_requirements | review_comment | text | Reviewer's explanation of what must be corrected or why the item was rejected. |
| document_requirements | reviewed_at | timestamp | When the review decision was recorded. |
| document_requirements | reviewed_by | bigint unsigned | Foreign key to `users.id` — the staff member who reviewed the submission. |
| document_requirements | uploaded_file_path | varchar(255) | Storage path of the file the requester uploaded to satisfy this item. |
| document_requirements | verified_at | timestamp | When the uploaded file was verified as authentic. |
| document_requirements | verified_by | bigint unsigned | Foreign key to `users.id` — the staff member who verified the file. |
| document_requirements | notes | varchar(255) | Additional remarks about this checklist item. |
| document_requirements | created_at | timestamp | When the checklist item was created. |
| document_requirements | updated_at | timestamp | When the checklist item was last modified. |
| document_attachments | id | bigint unsigned | Primary key of the supporting file record. |
| document_attachments | document_id | bigint unsigned | Foreign key to `documents.id` — the request the file belongs to. |
| document_attachments | file_path | varchar(255) | Storage path of the file on the private disk; served only through an access-checked controller. |
| document_attachments | uploaded_by | bigint unsigned | Foreign key to `users.id` — the staff member who uploaded the file. |
| document_attachments | sort_order | smallint unsigned | Display position of the file within the request's attachment list. |
| document_attachments | created_at | timestamp | When the file was uploaded. |
| document_attachments | updated_at | timestamp | When the record was last modified. |
| document_comments | id | bigint unsigned | Primary key of the comment or message. |
| document_comments | document_id | bigint unsigned | Foreign key to `documents.id` — the request being discussed. |
| document_comments | author_id | bigint unsigned | Foreign key to `users.id` — the staff author. Null for citizen and system messages. |
| document_comments | author_type | varchar(255) | Who wrote the message: `staff`, `citizen`, or `system`. |
| document_comments | author_name | varchar(255) | Display name of the author, stored for citizens who have no user account. |
| document_comments | body | text | Text content of the comment or message. |
| document_comments | visibility | varchar(255) | Audience of the message: `internal` for staff only, `public` for the citizen-visible thread. |
| document_comments | staff_read_at | timestamp | When a staff member first read the message; null means unread. |
| document_comments | citizen_read_at | timestamp | When the citizen first read the message; null means unread. |
| document_comments | attachment_path | varchar(255) | Storage path of a file attached to the message. |
| document_comments | attachment_name | varchar(255) | Original filename of the attachment, shown to the reader. |
| document_comments | parent_id | bigint unsigned | Foreign key to `document_comments.id` — the message this one replies to, forming a thread. |
| document_comments | created_at | timestamp | When the message was posted. |
| document_comments | updated_at | timestamp | When the message was last edited. |
| document_custody_events | id | bigint unsigned | Primary key of the chain-of-custody entry. |
| document_custody_events | document_id | bigint unsigned | Foreign key to `documents.id` — the physical document that changed hands. |
| document_custody_events | user_id | bigint unsigned | Foreign key to `users.id` — the staff member who took custody. |
| document_custody_events | capture_method | varchar(255) | How custody was recorded: `scan` for a QR scan, `manual` for a keyed-in override. |
| document_custody_events | override_reason | varchar(500) | Justification required when custody is recorded manually instead of by scan. |
| document_custody_events | note | varchar(500) | Optional remark about the handover. |
| document_custody_events | created_at | timestamp | When custody was taken. |
| document_custody_events | updated_at | timestamp | When the entry was last modified. |
| request_steps | id | bigint unsigned | Primary key of the approval step on a live request. |
| request_steps | document_id | bigint unsigned | Foreign key to `documents.id` — the request being routed. |
| request_steps | step_order | smallint unsigned | Position of this step in the approval sequence, starting at 1. |
| request_steps | department_id | bigint unsigned | Foreign key to `departments.id` — the department that must act on this step. |
| request_steps | action | varchar(100) | Action the department must perform at this step (e.g. review, approve, certify funds). |
| request_steps | status | varchar(20) | Step state: `pending`, `current`, `approved`, `returned`, `denied`, or `skipped`. |
| request_steps | acted_by | bigint unsigned | Foreign key to `users.id` — the staff member who completed the step. |
| request_steps | acted_at | timestamp | When the step was completed. |
| request_steps | started_at | timestamp | When the step became the current step; used to measure per-step turnaround. |
| request_steps | remarks | varchar(500) | Notes recorded by the approver, such as the reason for a return. |
| request_steps | signature_path | varchar(255) | Storage path of the approver's e-signature image applied at this step. |
| request_steps | created_at | timestamp | When the step was created. |
| request_steps | updated_at | timestamp | When the step was last modified. |
| bookings | id | bigint unsigned | Primary key of the resource reservation. |
| bookings | document_id | bigint unsigned | Foreign key to `documents.id` — the booking request this reservation fulfils. |
| bookings | resource_id | bigint unsigned | Foreign key to `resources.id` — the facility or equipment reserved. |
| bookings | starts_at | datetime | Start of the reserved time slot. |
| bookings | ends_at | datetime | End of the reserved time slot. |
| bookings | status | varchar(255) | Reservation state: `pending`, `approved`, or `cancelled`. |
| bookings | notes | varchar(255) | Remarks about the reservation, such as setup requirements. |
| bookings | created_at | timestamp | When the reservation was made. |
| bookings | updated_at | timestamp | When the reservation was last modified. |
| staff_highlights | id | bigint unsigned | Primary key of the staff activity highlight. |
| staff_highlights | user_id | bigint unsigned | Foreign key to `users.id` — the staff member the highlight belongs to. |
| staff_highlights | document_id | bigint unsigned | Foreign key to `documents.id` — the related request, if any. |
| staff_highlights | highlight_type | varchar(255) | Kind of highlight: `completed`, `milestone`, or `note`. |
| staff_highlights | body | text | Text of the highlight shown on the staff profile feed. |
| staff_highlights | created_at | timestamp | When the highlight was recorded. |
| staff_highlights | updated_at | timestamp | When the highlight was last modified. |

---

## 2. Reference and Configuration Tables

| Table Name | Field Name | Data Type | Description |
|---|---|---|---|
| departments | id | bigint unsigned | Primary key of the office or department. |
| departments | name | varchar(255) | Full department name; must be unique. |
| departments | code | varchar(10) | Short unique abbreviation used on labels and tracking displays. |
| departments | is_active | tinyint(1) | Boolean flag; when false the department is retired and cannot receive new requests. |
| departments | created_at | timestamp | When the department was created. |
| departments | updated_at | timestamp | When the department was last modified. |
| request_types | id | bigint unsigned | Primary key of the service or document type offered. |
| request_types | name | varchar(255) | Unique public-facing name of the service (e.g. Business Permit). |
| request_types | kind | varchar(255) | Category of service: `document`, `booking`, `equipment`, or `service`. |
| request_types | department_id | bigint unsigned | Foreign key to `departments.id` — the department that requests of this type are routed to. |
| request_types | resource_id | bigint unsigned | Foreign key to `resources.id` — the bookable facility or equipment, for booking and equipment kinds. |
| request_types | description | text | Public explanation of the service shown on the request form. |
| request_types | is_active | tinyint(1) | Boolean flag; when false the service is hidden from the public request form. |
| request_types | sort_order | int unsigned | Display position of the service in listings. |
| request_types | created_at | timestamp | When the service type was created. |
| request_types | updated_at | timestamp | When the service type was last modified. |
| request_type_requirements | id | bigint unsigned | Primary key of the template checklist item. |
| request_type_requirements | request_type_id | bigint unsigned | Foreign key to `request_types.id` — the service this requirement applies to. |
| request_type_requirements | label | varchar(255) | Name of the required document (e.g. Valid ID). |
| request_type_requirements | is_mandatory | tinyint(1) | Boolean flag; when true the requirement must be satisfied before processing. |
| request_type_requirements | sort_order | int unsigned | Display position of the requirement in the checklist. |
| request_type_requirements | created_at | timestamp | When the template item was created. |
| request_type_requirements | updated_at | timestamp | When the template item was last modified. |
| resources | id | bigint unsigned | Primary key of the bookable facility or equipment. |
| resources | name | varchar(255) | Unique name of the resource (e.g. Session Hall, Service Vehicle). |
| resources | description | text | Details about the resource shown when booking. |
| resources | is_active | tinyint(1) | Boolean flag; when false the resource cannot be booked. |
| resources | sort_order | int unsigned | Display position of the resource in listings. |
| resources | created_at | timestamp | When the resource was created. |
| resources | updated_at | timestamp | When the resource was last modified. |
| route_templates | id | bigint unsigned | Primary key of the reusable approval route. |
| route_templates | name | varchar(255) | Unique name of the route template (e.g. Procurement — Above Threshold). |
| route_templates | description | varchar(500) | Explanation of when this route should be applied. |
| route_templates | is_active | tinyint(1) | Boolean flag; when false the template is no longer applied to new requests. |
| route_templates | created_at | timestamp | When the template was created. |
| route_templates | updated_at | timestamp | When the template was last modified. |
| route_template_steps | id | bigint unsigned | Primary key of the step within a route template. |
| route_template_steps | route_template_id | bigint unsigned | Foreign key to `route_templates.id` — the route this step belongs to. |
| route_template_steps | step_order | smallint unsigned | Position of the step in the approval sequence. |
| route_template_steps | department_id | bigint unsigned | Foreign key to `departments.id` — the department that must act at this step. |
| route_template_steps | action | varchar(100) | Action the department performs at this step. |
| route_template_steps | condition | varchar(30) | Rule deciding whether the step applies: `has_amount`, `below_threshold`, or `at_least_threshold`. Null means the step always applies. |
| route_template_steps | created_at | timestamp | When the step was created. |
| route_template_steps | updated_at | timestamp | When the step was last modified. |

---

## 3. Users, Roles, and Permissions

| Table Name | Field Name | Data Type | Description |
|---|---|---|---|
| users | id | bigint unsigned | Primary key of the system user account. |
| users | name | varchar(255) | Full name of the staff member. |
| users | email | varchar(255) | Unique email address used as the login identifier. |
| users | email_verified_at | timestamp | When the email address was confirmed; null means unverified. |
| users | is_active | tinyint(1) | Boolean flag; when false the account is disabled and cannot sign in. |
| users | department_id | bigint unsigned | Foreign key to `departments.id` — the office the user belongs to; scopes what records they may view. |
| users | signature_path | varchar(255) | Storage path of the user's e-signature image applied when approving steps. |
| users | password | varchar(255) | Bcrypt hash of the account password. Never stored in plain text. |
| users | remember_token | varchar(100) | Token supporting the "remember me" persistent login. |
| users | identity_code | varchar(40) | Unique staff identity code encoded in the user's badge QR. |
| users | created_at | timestamp | When the account was created. |
| users | updated_at | timestamp | When the account was last modified. |
| users | deleted_at | timestamp | Soft-delete marker; non-null means the account is archived. |
| roles | id | bigint unsigned | Primary key of the role. |
| roles | name | varchar(255) | Role name: `staff`, `receiving_staff`, `supervisor`, or `super_admin`. |
| roles | guard_name | varchar(255) | Authentication guard the role applies to; `web` for this application. |
| roles | created_at | timestamp | When the role was created. |
| roles | updated_at | timestamp | When the role was last modified. |
| permissions | id | bigint unsigned | Primary key of the permission. |
| permissions | name | varchar(255) | Permission name describing a single allowed action (e.g. create documents). |
| permissions | guard_name | varchar(255) | Authentication guard the permission applies to. |
| permissions | created_at | timestamp | When the permission was created. |
| permissions | updated_at | timestamp | When the permission was last modified. |
| role_has_permissions | permission_id | bigint unsigned | Foreign key to `permissions.id`; part of the composite primary key. |
| role_has_permissions | role_id | bigint unsigned | Foreign key to `roles.id`; pairs a permission with a role. |
| model_has_roles | role_id | bigint unsigned | Foreign key to `roles.id` — the role being granted. |
| model_has_roles | model_type | varchar(255) | Class name of the model holding the role, normally the User model. |
| model_has_roles | model_id | bigint unsigned | Identifier of the record holding the role, normally `users.id`. |
| model_has_permissions | permission_id | bigint unsigned | Foreign key to `permissions.id` — a permission granted directly, bypassing roles. |
| model_has_permissions | model_type | varchar(255) | Class name of the model holding the permission. |
| model_has_permissions | model_id | bigint unsigned | Identifier of the record holding the permission. |
| password_reset_tokens | email | varchar(255) | Email address the reset link was issued to; primary key. |
| password_reset_tokens | token | varchar(255) | Hashed single-use token validating the reset link. |
| password_reset_tokens | created_at | timestamp | When the token was issued; used to expire stale links. |
| sessions | id | varchar(255) | Primary key; the session identifier stored in the browser cookie. |
| sessions | user_id | bigint unsigned | Foreign key to `users.id` — the signed-in user, null for guests. |
| sessions | ip_address | varchar(45) | IP address the session originated from. |
| sessions | user_agent | text | Browser and device string reported by the client. |
| sessions | payload | longtext | Serialized session data. |
| sessions | last_activity | int | Unix timestamp of the most recent request; drives session expiry. |

---

## 4. Audit, Notification, and System Tables

| Table Name | Field Name | Data Type | Description |
|---|---|---|---|
| activity_log | id | bigint unsigned | Primary key of the audit trail entry. |
| activity_log | log_name | varchar(255) | Category of the logged activity, used to group related entries. |
| activity_log | description | text | Human-readable summary of what happened. |
| activity_log | subject_type | varchar(255) | Class name of the record that was acted upon. |
| activity_log | subject_id | bigint unsigned | Identifier of the record that was acted upon. |
| activity_log | event | varchar(255) | Type of change recorded: created, updated, or deleted. |
| activity_log | causer_type | varchar(255) | Class name of the actor who caused the change, normally the User model. |
| activity_log | causer_id | bigint unsigned | Identifier of the user who caused the change. |
| activity_log | properties | json | Old and new attribute values captured at the time of the change. |
| activity_log | batch_uuid | char(36) | Groups multiple log entries written in a single operation. |
| activity_log | created_at | timestamp | When the activity occurred. |
| activity_log | updated_at | timestamp | When the log entry was last modified. |
| notifications | id | char(36) | Primary key; a UUID identifying the notification. |
| notifications | type | varchar(255) | Class name of the notification, indicating what event it reports. |
| notifications | notifiable_type | varchar(255) | Class name of the recipient model, normally the User model. |
| notifications | notifiable_id | bigint unsigned | Identifier of the recipient, normally `users.id`. |
| notifications | data | text | JSON payload rendered in the notification bell. |
| notifications | read_at | timestamp | When the recipient opened the notification; null means unread. |
| notifications | created_at | timestamp | When the notification was generated. |
| notifications | updated_at | timestamp | When the notification was last modified. |
| jobs | id | bigint unsigned | Primary key of the queued background job. |
| jobs | queue | varchar(255) | Name of the queue the job is waiting on. |
| jobs | payload | longtext | Serialized job class and arguments. |
| jobs | attempts | smallint unsigned | Number of times execution has been tried. |
| jobs | reserved_at | int unsigned | Unix timestamp when a worker claimed the job. |
| jobs | available_at | int unsigned | Unix timestamp when the job becomes eligible to run. |
| jobs | created_at | int unsigned | Unix timestamp when the job was queued. |
| job_batches | id | varchar(255) | Primary key of the job batch. |
| job_batches | name | varchar(255) | Descriptive name of the batch. |
| job_batches | total_jobs | int | Number of jobs the batch started with. |
| job_batches | pending_jobs | int | Number of jobs still waiting to finish. |
| job_batches | failed_jobs | int | Number of jobs in the batch that failed. |
| job_batches | failed_job_ids | longtext | JSON list of the identifiers of failed jobs. |
| job_batches | options | mediumtext | Serialized batch configuration. |
| job_batches | cancelled_at | int | Unix timestamp when the batch was cancelled. |
| job_batches | created_at | int | Unix timestamp when the batch was created. |
| job_batches | finished_at | int | Unix timestamp when the batch finished. |
| failed_jobs | id | bigint unsigned | Primary key of the failed job record. |
| failed_jobs | uuid | varchar(255) | Unique identifier of the failed job. |
| failed_jobs | connection | text | Queue connection the job ran on. |
| failed_jobs | queue | text | Queue name the job ran on. |
| failed_jobs | payload | longtext | Serialized job class and arguments. |
| failed_jobs | exception | longtext | Full error message and stack trace of the failure. |
| failed_jobs | failed_at | timestamp | When the job failed. |
| cache | key | varchar(255) | Primary key; the cache entry's lookup name. |
| cache | value | mediumtext | Serialized cached value. |
| cache | expiration | bigint | Unix timestamp when the entry expires. |
| cache_locks | key | varchar(255) | Primary key; name of the atomic lock. |
| cache_locks | owner | varchar(255) | Identifier of the process currently holding the lock. |
| cache_locks | expiration | bigint | Unix timestamp when the lock is automatically released. |
| migrations | id | int unsigned | Primary key of the applied migration record. |
| migrations | migration | varchar(255) | Filename of the migration that was run. |
| migrations | batch | int | Batch number grouping migrations run together, enabling rollback. |
| health_check_result_history_items | id | bigint unsigned | Primary key of the system health check result. |
| health_check_result_history_items | check_name | varchar(255) | Internal name of the health check performed. |
| health_check_result_history_items | check_label | varchar(255) | Display label of the health check. |
| health_check_result_history_items | status | varchar(255) | Result of the check: ok, warning, failed, or skipped. |
| health_check_result_history_items | notification_message | text | Full message sent to administrators when the check fails. |
| health_check_result_history_items | short_summary | varchar(255) | Brief result summary shown on the health dashboard. |
| health_check_result_history_items | meta | json | Additional measurements captured by the check. |
| health_check_result_history_items | ended_at | timestamp | When the check finished running. |
| health_check_result_history_items | batch | char(36) | Groups all checks run in the same sweep. |
| health_check_result_history_items | created_at | timestamp | When the result was recorded. |
| health_check_result_history_items | updated_at | timestamp | When the result was last modified. |
| pulse_entries | id | bigint unsigned | Primary key of the raw performance metric sample. |
| pulse_entries | timestamp | int unsigned | Unix timestamp when the sample was captured. |
| pulse_entries | type | varchar(255) | Kind of metric recorded (e.g. slow query, request duration). |
| pulse_entries | key | mediumtext | Identifier of the thing measured, such as the route or query. |
| pulse_entries | key_hash | binary(16) | Generated hash of the key column, used for fast indexed lookups. |
| pulse_entries | value | bigint | Measured value of the sample. |
| pulse_aggregates | id | bigint unsigned | Primary key of the rolled-up performance metric. |
| pulse_aggregates | bucket | int unsigned | Unix timestamp marking the start of the time bucket. |
| pulse_aggregates | period | mediumint unsigned | Length of the aggregation window in minutes. |
| pulse_aggregates | type | varchar(255) | Kind of metric being aggregated. |
| pulse_aggregates | key | mediumtext | Identifier of the thing measured. |
| pulse_aggregates | key_hash | binary(16) | Generated hash of the key column, used for fast indexed lookups. |
| pulse_aggregates | aggregate | varchar(255) | Aggregation applied: count, max, average, or sum. |
| pulse_aggregates | value | decimal(20,2) | Computed aggregate value. |
| pulse_aggregates | count | int unsigned | Number of samples the aggregate was computed from. |
| pulse_values | id | bigint unsigned | Primary key of the latest-value performance metric. |
| pulse_values | timestamp | int unsigned | Unix timestamp when the value was recorded. |
| pulse_values | type | varchar(255) | Kind of metric recorded. |
| pulse_values | key | mediumtext | Identifier of the thing measured. |
| pulse_values | key_hash | binary(16) | Generated hash of the key column, used for fast indexed lookups. |
| pulse_values | value | mediumtext | Most recent recorded value. |
</content>
