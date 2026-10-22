ALTER TABLE member_documents ADD COLUMN IF NOT EXISTS document_side ENUM('front','back') NULL;
