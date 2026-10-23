-- AlterTable
ALTER TABLE "document_requirements" ADD COLUMN     "opened_at" TIMESTAMP(0),
ADD COLUMN     "opened_by" BIGINT;

-- CreateIndex
CREATE INDEX "document_requirements_opened_by_index" ON "document_requirements"("opened_by");

-- AddForeignKey
ALTER TABLE "document_requirements" ADD CONSTRAINT "document_requirements_opened_by_foreign" FOREIGN KEY ("opened_by") REFERENCES "users"("id") ON DELETE SET NULL ON UPDATE NO ACTION;


