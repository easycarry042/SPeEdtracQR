-- CTCAO's assignment table: who usually handles each request type. The Review
-- modal fills the first one in for the supervisor to confirm.
CREATE TABLE "request_type_handlers" (
    "id" BIGSERIAL NOT NULL,
    "request_type_id" BIGINT NOT NULL,
    "user_id" BIGINT NOT NULL,
    "sort_order" INTEGER NOT NULL DEFAULT 0,
    "created_at" TIMESTAMP(0),
    "updated_at" TIMESTAMP(0),

    CONSTRAINT "request_type_handlers_pkey" PRIMARY KEY ("id")
);

CREATE UNIQUE INDEX "request_type_handlers_type_user_unique" ON "request_type_handlers"("request_type_id", "user_id");
CREATE INDEX "request_type_handlers_user_id_index" ON "request_type_handlers"("user_id");

ALTER TABLE "request_type_handlers" ADD CONSTRAINT "request_type_handlers_request_type_id_foreign" FOREIGN KEY ("request_type_id") REFERENCES "request_types"("id") ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE "request_type_handlers" ADD CONSTRAINT "request_type_handlers_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE NO ACTION;

-- Shut out of the Data API like every other table.
ALTER TABLE "request_type_handlers" ENABLE ROW LEVEL SECURITY;

-- One extra question per request type on the public form (the paper form's
-- "Venue", "Size", "Others: ___"), and the requester's answer to it.
ALTER TABLE "request_types" ADD COLUMN "details_label" VARCHAR(255);
ALTER TABLE "documents" ADD COLUMN "details" TEXT;

-- The paper form's "Name and Department": the office or organization a
-- request comes from (another city office files through the same form).
ALTER TABLE "documents" ADD COLUMN "requester_office" VARCHAR(255);
