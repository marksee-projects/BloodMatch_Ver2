-- Migration 036: Add disabled_until for temporary chat report disciplinary actions
ALTER TABLE users ADD COLUMN IF NOT EXISTS disabled_until DATETIME NULL AFTER account_status;
