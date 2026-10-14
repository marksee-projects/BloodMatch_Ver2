# Chat, database, and notification plan

Prepared 14 October 2026. Code and user-run maintenance are now prepared. Database mutations are deferred to the user; load testing remains cancelled. See [the execution handoff](CHAT_DATABASE_NOTIFICATION_HANDOFF.md).

## Read-only findings

- Connected application database: `bloodmatch`, MariaDB 10.4.32, port 3306. README documents port 3307; the database connector has a fallback. All future scripts must assert the resolved database and port rather than rely on the documented port.
- 24 base tables; 115 users: 18 verified active admins, 60 verified active members, 34 pending active members, and 3 rejected active members.
- Chat code and UI exist, but live two-account operation has not been verified.
- `conversations` has 2 rows, `conversation_participants` 4, and `messages` 20. All three imported tables lack primary keys, automatic ID generation, indexes, and foreign keys present in the intended chat migration.
- Participant `last_read_at` is DATETIME without fractional precision; the intended schema uses DATETIME(3).
- Chat has 2 orphan participant rows and 1 orphan message row, measured against users and conversations. No duplicate IDs or participant pairs were found, and both conversations have two participant rows. Orphan causes still need investigation.
- The chat migration is absent from the migration ledger. Two migration filenames use prefix 021; do not run the entire migration runner to repair this import.
- 8 blood requests contain the invalid empty enum value for `review_status`; valid values are `not_required` and `pending_review`. Resolve their intended state from workflow evidence before changing data.
- A subsequent enum audit found one legacy admin with invalid blood-type provenance. The prepared repair retains its type as self-reported and removes the unsupported documentary-verification claim; the obsolete seeder is retired.
- Email outbox has no orphan notification references in this inspection.
- Frontend action feedback uses page-local messages and global alert CSS, shared confirmation dialogs, specialized dialogs, and a native browser alert in `ChatContext` when starting chat fails. There is no single shared action-result popup implementation in the inspected paths.
- No table or column is yet proven redundant. Empty operational tables are not evidence of redundancy. Generated names, location coordinates, blood verification flags, and ranking fields are referenced by current code.

## 1. Capture and audit the imported database

1. Export a complete local backup including schema, rows, triggers, and migration ledger. Verify that it can be restored into an isolated database.
2. Compare every actual table, column, default, index, constraint, and trigger with migrations and application SQL.
3. Trace each proposed removal through repositories, services, routes, scheduled workers, tests, and requirements. Classify fields as required, intentional snapshot/cache, derived, obsolete, or unresolved.
4. Check duplicates, orphan references, invalid enum values, timestamp precision, nullability, generated fields, normalization, and required indexes. Audit user deletion cascades and audit-log triggers explicitly.
5. Produce a table/column decision report with evidence and the exact migration required. Remove only proven obsolete objects; update their consumers in the same change.

## 2. Repair and verify chat

1. Investigate the orphan chat records and decide whether they can be restored from the import or must be archived before repair. Preserve existing message content in the backup/archive.
2. Prepare a new migration for existing tables, rather than rerunning CREATE TABLE. Restore ID keys and automatic generation, participant uniqueness, indexes, foreign keys, and millisecond read timestamps after integrity checks pass.
3. Reconcile the ledger and duplicate migration prefix with the actual migration runner behavior.
4. Verify conversation creation, permitted and denied contacts, sending, polling, unread counts, bounded read updates, reload persistence, inactive accounts, mobile tabs, and simultaneous starts using two accounts on an isolated copy first.
5. Report chat as verified only after live database and browser checks pass.

## 3. Inventory and unify notification feedback

Inventory the actual active routes and their handlers:

| Area | Actions and feedback to inspect |
| --- | --- |
| Authentication | Login/logout, registration, email verification/resend, password recovery/reset |
| Profile | Save details, photo upload, donor enrollment/availability, standby acknowledgment, ID and donor-card uploads |
| Requests | Create/edit/cancel, emergency creation, matching success/partial failure, retry/re-match |
| Donor coordination | Offer help, withdraw offer, donation report submission, My Responses actions |
| Staff | Verify/reject membership, confirm/reject donations, role/status/chapter updates |
| Chat | Start/load/send failures, permission errors, unread/read failures |
| Notifications/reports | Load/read actions, analytics/audit errors, export feedback |

Proposed common popup:

- One shared provider and popup component accepting severity, title, message, optional action, deduplication key, and dismissal behavior.
- Same surface, spacing, typography, icon placement, close control, and action placement throughout the system; use existing design tokens and the installed icon library.
- Desktop: a restrained corner stack positioned to avoid chat/header controls. Mobile: a viewport-safe placement with readable text and 44px controls.
- Success and informational notices dismiss after a short interval, paused during interaction. Errors and emergency notices remain until dismissed or handled.
- Emergency notifications use the same component with an emergency icon, red semantic accent, explicit urgency text, and a View request action. Do not style a successfully saved emergency request as a failed action.
- A saved request with failed matching shows a persistent warning and retry guidance, never a success-only notice or instruction to create a duplicate.
- Queue and deduplicate events; preserve notices across navigation; clear private notices on logout/account change.
- Announce routine updates through a polite live region and urgent/errors appropriately. Routine notices do not steal focus; support keyboard access, light/dark modes, and reduced motion.
- Keep validation next to the invalid field. Keep explicit destructive confirmations and verification/privacy workflows, using consistent shared styling. Persisted notification-center records and transient action feedback serve different purposes; avoid creating database records for every successful click.

## 4. Retain fifteen representative demo accounts

1. Keep `markseejr@gmail.com` as the sole Admin/Officer account. The user authorized selecting a representative count; fifteen accounts cover all eight blood groups, three chapters, pending/rejected verification and donation history.
2. Generate the retained-user manifest and deletion impact before cleanup; preserve existing enrollment, verification, and safety-window data.
3. Calculate the exact removal impact: requests, matches, reports, notifications/outbox, documents, password resets, verification decisions, chat records, audit references, and operational records lacking user foreign keys.
4. Rehearse cleanup on the restored copy; preserve audit history and handle chat conversations that would otherwise have insufficient participants. Prevent queued mail from being sent during the rehearsal and cleanup.
5. The user runs the reviewed cleanup after backup and integrity checks. Verify exactly fifteen users and one admin, no unintended orphan records, and functioning staff/member workflows.
6. Existing passwords are hashed and cannot be read back. Reset the retained accounts to new unique temporary passwords, store only secure hashes, invalidate old reset/verification tokens and sessions as applicable, and deliver the new login credentials privately. Do not put passwords in tracked documentation or frontend code.

## 5. Final ERD and verification

- Generate the ERD from the final actual schema, including primary/foreign keys, cardinalities, nullable relationships, and significant unique constraints.
- Supply a complete technical ERD and a readable domain view if operational tables make the full diagram difficult to read. Document application-level references that are not enforced foreign keys.
- Update `docs/erd.md` and the diagram artifact after the schema is stable.
- Run affected database integrity checks, chat integration checks, request/matching and account workflow regression checks, frontend build, and browser checks across popup variants, navigation, narrow screens, and light/dark modes.
- Deliver the audit findings, repair/removal migrations, notification inventory and implementation, fifteen-account manifest and private credentials, ERD, and verification results.

## Execution order

Backup and schema audit -> chat/import repairs -> notification inventory and shared popup -> ten-account cleanup and credential resets -> final ERD and regression verification.

Account data volume does not affect ERD structure. Account cleanup and schema normalization are separate tasks.
