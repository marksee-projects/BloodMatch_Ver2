# Main chat cleanup checkpoint — 2026-10-19

## Completed after explicit confirmation

User explicitly confirmed clearing all chat data, including the two moderation reports, saved evidence and the open case, and stated a database backup was available. This supersedes the pending scope decision below for this one cleanup.

Transaction committed on `bloodmatch:3306`: removed **4 conversations, 8 participant rows, 54 messages, 9 message requests, 2 chat reports, 11 evidence copies and 15 chat notifications**. Post-commit read-only verification reports zero rows in all seven cleanup categories. Foreign-key checks stayed enabled; no TRUNCATE, schema change or sequence reset.

Twenty-six unrelated tables were fingerprint-verified unchanged, including accounts, requests, matches, donation records, documents, schema and email accounting; unrelated notifications preserved. Existing audit rows preserved and one explicit-confirmation cleanup audit entry added. Donation chat contexts were empty, so no accepted-offer donation link was severed. .env unchanged. Existing maintenance guard and temporary pause/restoration of workspace writers were used; no new task or email experiment.

Fresh pre-delete full backup: private/ignored `backend/storage/maintenance/bloodmatch-20261019-142447-ac108f9c.sql`, SHA256 `832d3308a830ff958e4d3fdc62146515dff02a800d9666943b1ac1b4dbc2bba2`; completion marker verified. Result record: `backend/storage/maintenance/chat-cleanup-result.json`. Backups retain original chats for recovery and are not the cleaned teammate export. Export the current main database afresh for sharing. Refresh/close stale browser chat tabs to clear cached conversation content; a running test-target backend is a separate database and was not cleaned.

Earlier review and decision checkpoint follows as history.

User requested cleaning conversations in the main database before sharing it with a teammate. Fixed-target read-only review of `bloodmatch:3306` found:

- Four conversations, eight participant rows, 54 messages, nine message requests.
- Two chat moderation reports (one open, one dismissed), with 11 saved message-evidence entries.
- Two reported conversations contain 48 messages in total; two unreported conversations contain six messages.
- No donation chat contexts or coordination events currently exist on main. One donation report remains. Schema is now consolidated (032); do not assume the retired donation hold/credit tables exist.

An explicit scope decision is needed for removing reported threads and saved evidence, especially the open investigation. Deleting messages alone does not remove `chat_report_evidence` copies. Existing retention logic protects unresolved chat reports; do not silently discard the open investigation under an ambiguous conversation cleanup request.

Full pre-cleanup backup completed through the existing backup-only maintenance action. Private/ignored path: `backend/storage/maintenance/bloodmatch-20261019-142000-9c2959c3.sql`, 456809 bytes, SHA256 `402c84c46755c002816d9151cc5f9ea6609d55d59bcdee1e57778f0fd901149a`. Completion marker and hash verified. No restore rehearsal performed. Backup contains the original conversations and is for recovery, not the cleaned teammate copy.

**No deletions or schema/account/password/email/task changes performed.** No maintenance lock enabled. Local diagnostic and backup record are in ignored `backend/storage/maintenance/review_chat_cleanup.php` and `chat-cleanup-backup.json`.

Next decision: full chat reset including moderation reports/evidence, or preserve reported conversations and remove only the two unreported ones. Once scope is explicit, recheck current counts/references, pause writers through existing maintenance controls, use a transaction with FK checks enabled and preserve all accounts, blood requests, matches, donation records/holds/credits, documents and schema. Audit exact counts and verify integrity/preservation. No generic demo-account cleanup or schema migration. Full reset must also handle non-FK chat-request/report references and applicable chat notifications without deleting unrelated notifications or email history.
