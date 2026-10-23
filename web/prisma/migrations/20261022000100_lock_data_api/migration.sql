-- Supabase publishes every table in `public` through its Data API (PostgREST),
-- and the anon key that reaches the browser (for Realtime) is enough to call it.
-- The app never uses that API: it reads and writes through Prisma as the
-- `postgres` role, which bypasses row level security. So RLS goes on with no
-- policies at all, which shuts the Data API out of every table — citizen
-- contact details, password hashes and private messages included.
--
-- Any table added later needs the same line in its own migration.
ALTER TABLE "activity_log" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "bookings" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "departments" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "document_attachments" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "document_comments" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "document_custody_events" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "document_requirements" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "documents" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "model_has_permissions" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "model_has_roles" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "notifications" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "password_reset_tokens" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "permissions" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "request_steps" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "request_type_requirements" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "request_types" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "resources" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "role_has_permissions" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "roles" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "route_template_steps" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "route_templates" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "staff_highlights" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "users" ENABLE ROW LEVEL SECURITY;
