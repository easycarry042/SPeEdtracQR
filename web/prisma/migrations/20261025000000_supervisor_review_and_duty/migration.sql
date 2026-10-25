-- Supervisor sign-off on a citizen request (staff submit → supervisor confirms
-- or returns), and the daily On Duty / On Leave switch that auto-dispatch
-- reads. IF NOT EXISTS: these columns were first added to Supabase by hand
-- (`prisma db push`), so this migration must be a no-op there.
ALTER TABLE "documents" ADD COLUMN IF NOT EXISTS "reviewed_by" BIGINT;
ALTER TABLE "documents" ADD COLUMN IF NOT EXISTS "reviewed_at" TIMESTAMP(0);
ALTER TABLE "documents" ADD COLUMN IF NOT EXISTS "supervisor_notes" TEXT;
ALTER TABLE "users" ADD COLUMN IF NOT EXISTS "is_available" BOOLEAN NOT NULL DEFAULT true;

CREATE INDEX IF NOT EXISTS "documents_reviewed_by_index" ON "documents"("reviewed_by");

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'documents_reviewed_by_foreign') THEN
    ALTER TABLE "documents" ADD CONSTRAINT "documents_reviewed_by_foreign" FOREIGN KEY ("reviewed_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;
  END IF;
END $$;
