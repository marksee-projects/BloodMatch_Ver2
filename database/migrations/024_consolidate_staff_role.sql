-- Approved 2026-10-11: global Admin/Officer staff, canonical role admin.
-- Run only after external backup, impact review and maintenance; no email/backfill.
-- Data conversion/audit are atomic. ALTER TABLE auto-commits separately.
START TRANSACTION;
INSERT INTO audit_log (actor_id, action, target_type, target_id, context, created_at)
SELECT NULL, 'admin.user.role_consolidated', 'user', CAST(id AS CHAR),
       JSON_OBJECT('from_role', 'officer', 'to_role', 'admin', 'chapter_id', chapter_id,
                   'migration', '024_consolidate_staff_role.sql'), UTC_TIMESTAMP(3)
FROM users WHERE role = 'officer';
UPDATE users SET role = 'admin', updated_at = updated_at WHERE role = 'officer';
COMMIT;
ALTER TABLE users MODIFY COLUMN role ENUM('member', 'admin') NOT NULL DEFAULT 'member';
