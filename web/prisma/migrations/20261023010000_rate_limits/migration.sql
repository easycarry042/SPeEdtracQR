-- CreateTable
CREATE TABLE "rate_limits" (
    "key" VARCHAR(191) NOT NULL,
    "hits" INTEGER NOT NULL,
    "reset_at" TIMESTAMPTZ(3) NOT NULL,

    CONSTRAINT "rate_limits_pkey" PRIMARY KEY ("key")
);

-- CreateIndex
CREATE INDEX "rate_limits_reset_at_index" ON "rate_limits"("reset_at");


-- Shut out of the Data API like every other table.
ALTER TABLE "rate_limits" ENABLE ROW LEVEL SECURITY;
