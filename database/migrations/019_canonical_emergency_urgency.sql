-- U0: historical urgency critical becomes emergency. Run with request writers stopped.
-- MySQL/MariaDB DDL auto-commits. Each step can be retried after a partial failure.
-- Append the new ENUM member first to preserve existing stored ENUM ordinals.
SET @u0_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES');

ALTER TABLE blood_requests
    MODIFY COLUMN urgency ENUM('routine', 'urgent', 'critical', 'emergency') NOT NULL DEFAULT 'routine';

-- Preserve lifecycle timestamps: this rename must not look like a request edit.
UPDATE blood_requests SET urgency = 'emergency', updated_at = updated_at
WHERE urgency = 'critical';

-- Enforced CHECK assertions (supported by the project's MariaDB 10.4 / MySQL 8.0.16+).
-- Reject unmapped/invalid rows before shrinking the ENUM, even in non-strict installations.
DROP TEMPORARY TABLE IF EXISTS u0_urgency_assertion;
CREATE TEMPORARY TABLE u0_urgency_assertion (
    invalid_rows BIGINT NOT NULL,
    CONSTRAINT chk_u0_urgency_zero CHECK (invalid_rows = 0)
);
INSERT INTO u0_urgency_assertion (invalid_rows)
SELECT COUNT(*) FROM blood_requests
WHERE urgency IS NULL OR urgency NOT IN ('routine', 'urgent', 'emergency');

ALTER TABLE blood_requests
    MODIFY COLUMN urgency ENUM('routine', 'urgent', 'emergency') NOT NULL DEFAULT 'routine';

-- A failed assertion prevents the runner from recording this migration as applied.
INSERT INTO u0_urgency_assertion (invalid_rows)
SELECT COUNT(*) FROM blood_requests WHERE urgency = 'critical';
DROP TEMPORARY TABLE u0_urgency_assertion;
SET SESSION sql_mode = @u0_previous_sql_mode;
