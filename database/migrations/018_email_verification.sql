-- 018_email_verification.sql
-- Add email verification columns to users table and grandfather existing users.

ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER account_status,
    ADD COLUMN email_code_hash VARCHAR(255) NULL AFTER email_verified_at,
    ADD COLUMN email_code_expires_at DATETIME NULL AFTER email_code_hash;

UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE email_verified_at IS NULL;
