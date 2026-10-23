-- CreateSchema
CREATE SCHEMA IF NOT EXISTS "public";

-- CreateTable
CREATE TABLE "activity_log" (
    "id" BIGSERIAL NOT NULL,
    "log_name" VARCHAR(255),
    "description" TEXT NOT NULL,
    "subject_type" VARCHAR(255),
    "subject_id" BIGINT,
    "event" VARCHAR(255),
    "causer_type" VARCHAR(255),
    "causer_id" BIGINT,
    "properties" JSONB,
    "batch_uuid" CHAR(36),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "activity_log_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "bookings" (
    "id" BIGSERIAL NOT NULL,
    "document_id" BIGINT NOT NULL,
    "resource_id" BIGINT NOT NULL,
    "quantity" INTEGER,
    "starts_at" TIMESTAMP(0) NOT NULL,
    "ends_at" TIMESTAMP(0) NOT NULL,
    "status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "released_at" TIMESTAMP(0),
    "released_by" BIGINT,
    "returned_at" TIMESTAMP(0),
    "returned_by" BIGINT,
    "returned_quantity" INTEGER NOT NULL DEFAULT 0,
    "damaged_quantity" INTEGER NOT NULL DEFAULT 0,
    "lost_quantity" INTEGER NOT NULL DEFAULT 0,
    "return_notes" TEXT,
    "overdue_reminded_at" TIMESTAMP(0),
    "notes" VARCHAR(255),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "bookings_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "departments" (
    "id" BIGSERIAL NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "code" VARCHAR(10) NOT NULL,
    "internal_step_hours" SMALLINT NOT NULL DEFAULT 72,
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "departments_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "document_attachments" (
    "id" BIGSERIAL NOT NULL,
    "document_id" BIGINT NOT NULL,
    "file_path" VARCHAR(255) NOT NULL,
    "uploaded_by" BIGINT,
    "sort_order" SMALLINT NOT NULL DEFAULT 0,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "document_attachments_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "document_comments" (
    "id" BIGSERIAL NOT NULL,
    "document_id" BIGINT NOT NULL,
    "author_id" BIGINT,
    "author_type" VARCHAR(255) NOT NULL DEFAULT 'staff',
    "author_name" VARCHAR(255),
    "body" TEXT NOT NULL,
    "visibility" VARCHAR(255) NOT NULL DEFAULT 'internal',
    "staff_read_at" TIMESTAMP(0),
    "citizen_read_at" TIMESTAMP(0),
    "attachment_path" VARCHAR(255),
    "attachment_name" VARCHAR(255),
    "parent_id" BIGINT,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "document_comments_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "document_custody_events" (
    "id" BIGSERIAL NOT NULL,
    "document_id" BIGINT NOT NULL,
    "user_id" BIGINT NOT NULL,
    "capture_method" VARCHAR(255) NOT NULL DEFAULT 'scan',
    "override_reason" VARCHAR(500),
    "note" VARCHAR(500),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "document_custody_events_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "document_requirements" (
    "id" BIGSERIAL NOT NULL,
    "document_id" BIGINT NOT NULL,
    "request_type_requirement_id" BIGINT,
    "label" VARCHAR(255) NOT NULL,
    "is_mandatory" BOOLEAN NOT NULL DEFAULT true,
    "review_status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "review_comment" TEXT,
    "reviewed_at" TIMESTAMP(0),
    "reviewed_by" BIGINT,
    "uploaded_file_path" VARCHAR(255),
    "verified_at" TIMESTAMP(0),
    "verified_by" BIGINT,
    "notes" VARCHAR(255),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "document_requirements_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "documents" (
    "id" BIGSERIAL NOT NULL,
    "tracking_number" VARCHAR(255) NOT NULL,
    "document_type" VARCHAR(255) NOT NULL,
    "citizen_name" VARCHAR(255),
    "citizen_contact" VARCHAR(255),
    "purpose" VARCHAR(255),
    "quantity" INTEGER,
    "needed_by" DATE,
    "description" TEXT,
    "status" VARCHAR(255) NOT NULL DEFAULT 'pending',
    "created_by" BIGINT,
    "remarks" TEXT,
    "completed_at" TIMESTAMP(0),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),
    "deleted_at" TIMESTAMP(0),
    "attachment_path" VARCHAR(255),
    "qr_code_path" VARCHAR(255),
    "sla_warning_notified_at" TIMESTAMP(0),
    "sla_breach_notified_at" TIMESTAMP(0),
    "assigned_to" BIGINT,
    "assigned_by" BIGINT,
    "department_id" BIGINT,
    "assigned_at" TIMESTAMP(0),
    "accepted_at" TIMESTAMP(0),
    "claimed_at" TIMESTAMP(0),
    "claim_date" DATE,
    "released_by" BIGINT,
    "status_changed_at" TIMESTAMP(0),
    "source" VARCHAR(255) NOT NULL DEFAULT 'walk_in',
    "origin" VARCHAR(20) NOT NULL DEFAULT 'external',
    "has_hard_copy" BOOLEAN NOT NULL DEFAULT true,
    "requesting_department_id" BIGINT,
    "amount" DECIMAL(14,2),
    "citizen_email" VARCHAR(255),
    "notify_citizen" BOOLEAN NOT NULL DEFAULT true,
    "sla_breached_at" TIMESTAMP(0),
    "status_before_hold" VARCHAR(255),
    "hold_reason" TEXT,
    "hold_until" DATE,
    "blocked_by" VARCHAR(255),
    "held_at" TIMESTAMP(0),
    "held_by" BIGINT,
    "decided_by" BIGINT,
    "decided_at" TIMESTAMP(0),
    "hold_reminder_sent_at" TIMESTAMP(0),

    CONSTRAINT "documents_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "model_has_permissions" (
    "permission_id" BIGINT NOT NULL,
    "model_type" VARCHAR(255) NOT NULL,
    "model_id" BIGINT NOT NULL,

    CONSTRAINT "model_has_permissions_pkey" PRIMARY KEY ("permission_id","model_id","model_type")
);

-- CreateTable
CREATE TABLE "model_has_roles" (
    "role_id" BIGINT NOT NULL,
    "model_type" VARCHAR(255) NOT NULL,
    "model_id" BIGINT NOT NULL,

    CONSTRAINT "model_has_roles_pkey" PRIMARY KEY ("role_id","model_id","model_type")
);

-- CreateTable
CREATE TABLE "notifications" (
    "id" CHAR(36) NOT NULL,
    "type" VARCHAR(255) NOT NULL,
    "notifiable_type" VARCHAR(255) NOT NULL,
    "notifiable_id" BIGINT NOT NULL,
    "data" TEXT NOT NULL,
    "read_at" TIMESTAMP(0),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "notifications_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "password_reset_tokens" (
    "email" VARCHAR(255) NOT NULL,
    "token" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMP(0),

    CONSTRAINT "password_reset_tokens_pkey" PRIMARY KEY ("email")
);

-- CreateTable
CREATE TABLE "permissions" (
    "id" BIGSERIAL NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "guard_name" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "permissions_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "request_steps" (
    "id" BIGSERIAL NOT NULL,
    "document_id" BIGINT NOT NULL,
    "step_order" SMALLINT NOT NULL,
    "department_id" BIGINT NOT NULL,
    "recipient_id" BIGINT,
    "action" VARCHAR(100) NOT NULL,
    "status" VARCHAR(20) NOT NULL DEFAULT 'pending',
    "acted_by" BIGINT,
    "acted_at" TIMESTAMP(0),
    "started_at" TIMESTAMP(0),
    "remarks" VARCHAR(500),
    "signature_path" VARCHAR(255),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "request_steps_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "request_type_requirements" (
    "id" BIGSERIAL NOT NULL,
    "request_type_id" BIGINT NOT NULL,
    "label" VARCHAR(255) NOT NULL,
    "is_mandatory" BOOLEAN NOT NULL DEFAULT true,
    "sort_order" INTEGER NOT NULL DEFAULT 0,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "request_type_requirements_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "request_types" (
    "id" BIGSERIAL NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "kind" VARCHAR(255) NOT NULL DEFAULT 'document',
    "department_id" BIGINT,
    "resource_id" BIGINT,
    "description" TEXT,
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "sort_order" INTEGER NOT NULL DEFAULT 0,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "request_types_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "resources" (
    "id" BIGSERIAL NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "kind" VARCHAR(255) NOT NULL DEFAULT 'facility',
    "total_quantity" INTEGER,
    "damaged_quantity" INTEGER NOT NULL DEFAULT 0,
    "capacity" INTEGER,
    "location" VARCHAR(255),
    "description" TEXT,
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "sort_order" INTEGER NOT NULL DEFAULT 0,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "resources_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "role_has_permissions" (
    "permission_id" BIGINT NOT NULL,
    "role_id" BIGINT NOT NULL,

    CONSTRAINT "role_has_permissions_pkey" PRIMARY KEY ("permission_id","role_id")
);

-- CreateTable
CREATE TABLE "roles" (
    "id" BIGSERIAL NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "guard_name" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "roles_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "route_template_steps" (
    "id" BIGSERIAL NOT NULL,
    "route_template_id" BIGINT NOT NULL,
    "step_order" SMALLINT NOT NULL,
    "department_id" BIGINT NOT NULL,
    "action" VARCHAR(100) NOT NULL,
    "condition" VARCHAR(30),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "route_template_steps_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "route_templates" (
    "id" BIGSERIAL NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "description" VARCHAR(500),
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "route_templates_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "staff_highlights" (
    "id" BIGSERIAL NOT NULL,
    "user_id" BIGINT NOT NULL,
    "document_id" BIGINT,
    "highlight_type" VARCHAR(255) NOT NULL DEFAULT 'note',
    "body" TEXT NOT NULL,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "staff_highlights_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "users" (
    "id" BIGSERIAL NOT NULL,
    "name" VARCHAR(255) NOT NULL,
    "email" VARCHAR(255) NOT NULL,
    "email_verified_at" TIMESTAMP(0),
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "last_active_at" TIMESTAMP(0),
    "avatar_path" VARCHAR(255),
    "department_id" BIGINT,
    "signature_path" VARCHAR(255),
    "password" VARCHAR(255) NOT NULL,
    "remember_token" VARCHAR(100),
    "identity_code" VARCHAR(40),
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),
    "deleted_at" TIMESTAMP(0),

    CONSTRAINT "users_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "activity_log_log_name_index" ON "activity_log"("log_name");

-- CreateIndex
CREATE INDEX "causer" ON "activity_log"("causer_type", "causer_id");

-- CreateIndex
CREATE INDEX "subject" ON "activity_log"("subject_type", "subject_id");

-- CreateIndex
CREATE INDEX "bookings_document_id_index" ON "bookings"("document_id");

-- CreateIndex
CREATE INDEX "bookings_released_by_index" ON "bookings"("released_by");

-- CreateIndex
CREATE INDEX "bookings_resource_id_starts_at_ends_at_index" ON "bookings"("resource_id", "starts_at", "ends_at");

-- CreateIndex
CREATE INDEX "bookings_returned_by_index" ON "bookings"("returned_by");

-- CreateIndex
CREATE UNIQUE INDEX "departments_name_unique" ON "departments"("name");

-- CreateIndex
CREATE UNIQUE INDEX "departments_code_unique" ON "departments"("code");

-- CreateIndex
CREATE INDEX "document_attachments_document_id_index" ON "document_attachments"("document_id");

-- CreateIndex
CREATE INDEX "document_attachments_uploaded_by_index" ON "document_attachments"("uploaded_by");

-- CreateIndex
CREATE INDEX "doc_comments_unread_idx" ON "document_comments"("document_id", "author_type", "staff_read_at");

-- CreateIndex
CREATE INDEX "document_comments_author_id_index" ON "document_comments"("author_id");

-- CreateIndex
CREATE INDEX "document_comments_document_id_visibility_index" ON "document_comments"("document_id", "visibility");

-- CreateIndex
CREATE INDEX "document_comments_parent_id_index" ON "document_comments"("parent_id");

-- CreateIndex
CREATE INDEX "document_custody_events_document_id_created_at_index" ON "document_custody_events"("document_id", "created_at");

-- CreateIndex
CREATE INDEX "document_custody_events_user_id_index" ON "document_custody_events"("user_id");

-- CreateIndex
CREATE INDEX "document_requirements_document_id_index" ON "document_requirements"("document_id");

-- CreateIndex
CREATE INDEX "document_requirements_request_type_requirement_id_index" ON "document_requirements"("request_type_requirement_id");

-- CreateIndex
CREATE INDEX "document_requirements_reviewed_by_index" ON "document_requirements"("reviewed_by");

-- CreateIndex
CREATE INDEX "document_requirements_verified_by_index" ON "document_requirements"("verified_by");

-- CreateIndex
CREATE UNIQUE INDEX "documents_tracking_number_unique" ON "documents"("tracking_number");

-- CreateIndex
CREATE INDEX "documents_assigned_by_index" ON "documents"("assigned_by");

-- CreateIndex
CREATE INDEX "documents_assigned_to_index" ON "documents"("assigned_to");

-- CreateIndex
CREATE INDEX "documents_created_by_index" ON "documents"("created_by");

-- CreateIndex
CREATE INDEX "documents_decided_by_index" ON "documents"("decided_by");

-- CreateIndex
CREATE INDEX "documents_department_id_index" ON "documents"("department_id");

-- CreateIndex
CREATE INDEX "documents_held_by_index" ON "documents"("held_by");

-- CreateIndex
CREATE INDEX "documents_origin_index" ON "documents"("origin");

-- CreateIndex
CREATE INDEX "documents_released_by_index" ON "documents"("released_by");

-- CreateIndex
CREATE INDEX "documents_requesting_department_id_index" ON "documents"("requesting_department_id");

-- CreateIndex
CREATE INDEX "documents_status_index" ON "documents"("status");

-- CreateIndex
CREATE INDEX "documents_tracking_number_index" ON "documents"("tracking_number");

-- CreateIndex
CREATE INDEX "model_has_permissions_model_id_model_type_index" ON "model_has_permissions"("model_id", "model_type");

-- CreateIndex
CREATE INDEX "model_has_roles_model_id_model_type_index" ON "model_has_roles"("model_id", "model_type");

-- CreateIndex
CREATE INDEX "notifications_notifiable_type_notifiable_id_index" ON "notifications"("notifiable_type", "notifiable_id");

-- CreateIndex
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" ON "permissions"("name", "guard_name");

-- CreateIndex
CREATE INDEX "request_steps_acted_by_index" ON "request_steps"("acted_by");

-- CreateIndex
CREATE INDEX "request_steps_department_id_index" ON "request_steps"("department_id");

-- CreateIndex
CREATE INDEX "request_steps_document_id_step_order_index" ON "request_steps"("document_id", "step_order");

-- CreateIndex
CREATE INDEX "request_steps_recipient_id_index" ON "request_steps"("recipient_id");

-- CreateIndex
CREATE INDEX "request_type_requirements_request_type_id_index" ON "request_type_requirements"("request_type_id");

-- CreateIndex
CREATE UNIQUE INDEX "request_types_name_unique" ON "request_types"("name");

-- CreateIndex
CREATE INDEX "request_types_department_id_index" ON "request_types"("department_id");

-- CreateIndex
CREATE INDEX "request_types_resource_id_index" ON "request_types"("resource_id");

-- CreateIndex
CREATE UNIQUE INDEX "resources_name_unique" ON "resources"("name");

-- CreateIndex
CREATE INDEX "role_has_permissions_role_id_index" ON "role_has_permissions"("role_id");

-- CreateIndex
CREATE UNIQUE INDEX "roles_name_guard_name_unique" ON "roles"("name", "guard_name");

-- CreateIndex
CREATE INDEX "route_template_steps_department_id_index" ON "route_template_steps"("department_id");

-- CreateIndex
CREATE INDEX "route_template_steps_route_template_id_step_order_index" ON "route_template_steps"("route_template_id", "step_order");

-- CreateIndex
CREATE UNIQUE INDEX "route_templates_name_unique" ON "route_templates"("name");

-- CreateIndex
CREATE INDEX "staff_highlights_document_id_index" ON "staff_highlights"("document_id");

-- CreateIndex
CREATE INDEX "staff_highlights_user_id_created_at_index" ON "staff_highlights"("user_id", "created_at");

-- CreateIndex
CREATE UNIQUE INDEX "users_email_unique" ON "users"("email");

-- CreateIndex
CREATE UNIQUE INDEX "users_identity_code_unique" ON "users"("identity_code");

-- CreateIndex
CREATE INDEX "users_department_id_index" ON "users"("department_id");

-- AddForeignKey
ALTER TABLE "bookings" ADD CONSTRAINT "bookings_document_id_foreign" FOREIGN KEY ("document_id") REFERENCES "documents"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "bookings" ADD CONSTRAINT "bookings_released_by_foreign" FOREIGN KEY ("released_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "bookings" ADD CONSTRAINT "bookings_resource_id_foreign" FOREIGN KEY ("resource_id") REFERENCES "resources"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "bookings" ADD CONSTRAINT "bookings_returned_by_foreign" FOREIGN KEY ("returned_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_attachments" ADD CONSTRAINT "document_attachments_document_id_foreign" FOREIGN KEY ("document_id") REFERENCES "documents"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_attachments" ADD CONSTRAINT "document_attachments_uploaded_by_foreign" FOREIGN KEY ("uploaded_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_comments" ADD CONSTRAINT "document_comments_author_id_foreign" FOREIGN KEY ("author_id") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_comments" ADD CONSTRAINT "document_comments_document_id_foreign" FOREIGN KEY ("document_id") REFERENCES "documents"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_comments" ADD CONSTRAINT "document_comments_parent_id_foreign" FOREIGN KEY ("parent_id") REFERENCES "document_comments"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_custody_events" ADD CONSTRAINT "document_custody_events_document_id_foreign" FOREIGN KEY ("document_id") REFERENCES "documents"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_custody_events" ADD CONSTRAINT "document_custody_events_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_requirements" ADD CONSTRAINT "document_requirements_document_id_foreign" FOREIGN KEY ("document_id") REFERENCES "documents"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_requirements" ADD CONSTRAINT "document_requirements_request_type_requirement_id_foreign" FOREIGN KEY ("request_type_requirement_id") REFERENCES "request_type_requirements"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_requirements" ADD CONSTRAINT "document_requirements_reviewed_by_foreign" FOREIGN KEY ("reviewed_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "document_requirements" ADD CONSTRAINT "document_requirements_verified_by_foreign" FOREIGN KEY ("verified_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_assigned_by_foreign" FOREIGN KEY ("assigned_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_assigned_to_foreign" FOREIGN KEY ("assigned_to") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_created_by_foreign" FOREIGN KEY ("created_by") REFERENCES "users"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_decided_by_foreign" FOREIGN KEY ("decided_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "departments"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_held_by_foreign" FOREIGN KEY ("held_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_released_by_foreign" FOREIGN KEY ("released_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "documents" ADD CONSTRAINT "documents_requesting_department_id_foreign" FOREIGN KEY ("requesting_department_id") REFERENCES "departments"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "model_has_permissions" ADD CONSTRAINT "model_has_permissions_permission_id_foreign" FOREIGN KEY ("permission_id") REFERENCES "permissions"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "model_has_roles" ADD CONSTRAINT "model_has_roles_role_id_foreign" FOREIGN KEY ("role_id") REFERENCES "roles"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "request_steps" ADD CONSTRAINT "request_steps_acted_by_foreign" FOREIGN KEY ("acted_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "request_steps" ADD CONSTRAINT "request_steps_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "departments"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "request_steps" ADD CONSTRAINT "request_steps_document_id_foreign" FOREIGN KEY ("document_id") REFERENCES "documents"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "request_steps" ADD CONSTRAINT "request_steps_recipient_id_foreign" FOREIGN KEY ("recipient_id") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "request_type_requirements" ADD CONSTRAINT "request_type_requirements_request_type_id_foreign" FOREIGN KEY ("request_type_id") REFERENCES "request_types"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "request_types" ADD CONSTRAINT "request_types_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "departments"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "request_types" ADD CONSTRAINT "request_types_resource_id_foreign" FOREIGN KEY ("resource_id") REFERENCES "resources"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "role_has_permissions" ADD CONSTRAINT "role_has_permissions_permission_id_foreign" FOREIGN KEY ("permission_id") REFERENCES "permissions"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "role_has_permissions" ADD CONSTRAINT "role_has_permissions_role_id_foreign" FOREIGN KEY ("role_id") REFERENCES "roles"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "route_template_steps" ADD CONSTRAINT "route_template_steps_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "departments"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "route_template_steps" ADD CONSTRAINT "route_template_steps_route_template_id_foreign" FOREIGN KEY ("route_template_id") REFERENCES "route_templates"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "staff_highlights" ADD CONSTRAINT "staff_highlights_document_id_foreign" FOREIGN KEY ("document_id") REFERENCES "documents"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "staff_highlights" ADD CONSTRAINT "staff_highlights_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "users" ADD CONSTRAINT "users_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "departments"("id") ON DELETE SET NULL ON UPDATE NO ACTION;

