-- Additive only: existing reports retain NULL; no status or data backfill.
ALTER TABLE donation_reports
    ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER report_note;
