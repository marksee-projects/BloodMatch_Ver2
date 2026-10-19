Schema migrations are applied once by `php run_migrations.php` and tracked in `schema_migrations`.

Prepared 021 schema/shared-accounting checkpoint creates `email_outbox` and
`email_attempts`, without backfill or worker integration. Agent has not applied it.
Use the fixed-test-target single-file helper for this checkpoint, not the generic
all-pending runner. Exact review/apply/test commands:
[`EMAIL_SCHEMA_ACCOUNTING_CHECKPOINT.md`](../../docs/EMAIL_SCHEMA_ACCOUNTING_CHECKPOINT.md).

Rules:
- One numbered file per change: `001_create_users.sql`, `002_...`
- Use only statements supported inside a transaction (InnoDB DDL is fine on MySQL 8; avoid mixing engines)
- Never edit an already-applied migration; add a new one
- No credentials or real personal data in any migration

Prepared U0 migration: `019_canonical_emergency_urgency.sql` migrates the historical
urgency value to `emergency`, preserving request rows and timestamps. Final domain:
`routine`, `urgent`, `emergency`. It has not been executed by the coding agent.
Execution, preflight, and verification: [`docs/urgency-migration-u0.md`](../../docs/urgency-migration-u0.md).

Prepared rejection-reason migration: `020_donation_report_rejection_reason.sql` adds
`donation_reports.rejection_reason VARCHAR(500) NULL` only. Existing reasons stay
NULL and statuses are untouched. It has not been executed by the coding agent.
The test-only review/apply helper targets `bloodmatch_test` on port 3306 and applies
only 020, recording it in the existing ledger. Review and exact commands:
[`docs/rejection-reason-migration.md`](../../docs/rejection-reason-migration.md).

Current migration state: 001–016 applied (verified via `run_migrations.php`; see `docs/erd.md` and `docs/test-log-location.md`).

| Migration | Purpose |
|---|---|
| `001_create_chapters.sql` | Fixed 3-chapter reference (Mt. Samat/Orani, Mt. Tarak/Mariveles, Meridian Heights/Balanga City) with canonical centroids |
| `002_create_users.sql` | Accounts: identity, role, `verification_status` + `account_status`/`deactivated_at`, chapter FK, DOB, blood type + provenance (`blood_type`, `blood_type_source`, `blood_type_verified`), donor enrollment/availability, `last_verified_donation_at`, coordinates |
| `003_create_password_resets.sql` | Historical reset-token schema; removed by migration 034 |
| `004_create_audit_log.sql` | Append-only `audit_log` + `UPDATE`/`DELETE`-blocking triggers |
| `005_create_auth_throttle.sql` | Login/reset throttling (`identifier`, `failed_count`, `locked_until`) |
| `006_create_member_documents.sql` | Verification evidence (`national_id`/`donor_card`/`parental_consent`, 64-hex `stored_name` outside webroot) |
| `007_create_verification_decisions.sql` | Verification history (`verified`/`rejected`/`re_review_requested`/`resubmitted`) |
| `008_create_blood_requests.sql` | Blood requests (requester, immutable `request_chapter_id`, blood type, quantity, facility, urgency, needed datetime, `OPEN`/`FULFILLED`/`CANCELLED`/`EXPIRED`, `review_status`, coordinates) |
| `009_create_compatibility_matrix.sql` | Central 8-type red-cell ABO/Rh lookup consumed only by `BloodCompatibilityService` |
| `010_create_matches.sql` | Persistent `(request_id, donor_id)` matches with `generation`, status, `distance_km`, `rank_score` |
| `011_create_donation_reports.sql` | Donation reports linked to `matches.id` + confirmer |
| `012_create_system_settings.sql` | Key-value settings (`standby_hours=42`, `cooldown_days=90`) |
| `013_create_notifications.sql` | In-app notifications with `UNIQUE(dedup_key, generation)` deduplication |
| `014_performance_indexes.sql` | Composite indexes `blood_requests(request_chapter_id, status, created_at)`, `audit_log(target_type, target_id, created_at)` |
| `015_create_profile_picture.sql` | `users.profile_picture VARCHAR(64) NULL` — 64-hex server-generated filename in `backend/storage/profile_pictures`, NULL when none |
| `016_location_reference.sql` | `bataan_locations` canonical reference (12 municipalities + 237 barangays, PSGC 030800000) + `users.location_id` / `blood_requests.location_id` FKs (`ON UPDATE CASCADE`, `ON DELETE SET NULL`); `latitude`/`longitude` retained as backend-resolved reference coordinates |

Prepared migration 022: donor_opportunities retains reference-only per-generation notification/timer history (including superseded generations); donor_response_state stores separate non-response standby and the due-at acknowledgment watermark. No backfill or existing user/donation changes. Preparation is approved; agent has not applied it. Test-only review/apply/verification and focused commands: [Safety follow-up checkpoint](../../docs/SAFETY_FOLLOWUP_CHECKPOINT.md). 020/021 user-supplied readiness is confirmed only on bloodmatch_test:3306; historical generic migration status above does not establish another target.
## 2026-10-11 — 024 canonical global staff (prepared; user-run only)

Latest approval supersedes chapter-scoped Officer rules. `024_consolidate_staff_role.sql` converts officer accounts to admin, appends conversion audit events and changes users.role to member/admin only. IDs, chapters, membership/activation statuses and all other user fields remain unchanged. This intentionally grants former officers global staff permissions.

Do not run all pending migrations for this checkpoint. The generic runner refuses pending 024. Use `review_staff_role_migration.php` (default read-only) and its reviewed-hash/maintenance apply mode. Review affected accounts, create an external backup, apply to bloodmatch_test:3306 and run the focused regression before application bloodmatch:3306. DDL auto-commits separately; retain backups and inspect interrupted state rather than dropping a database or blindly retrying. Exact commands: [Staff checkpoint](../../docs/STAFF_ROLE_CONSOLIDATION_CHECKPOINT.md).


Migration `034_remove_password_resets.sql` removes the password recovery table. Deploy the matching application removal first. Keep migration 003 as schema history.
