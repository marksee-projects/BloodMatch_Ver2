**Missing-table repair — October 22, 2026**

Main database `bloodmatch` on `127.0.0.1:3306` now has migrations **028, 030, and 031**. The previously failing presence, other-member profiles, offer acceptance, donation report access, and logout checks passed in the browser retest.

| Migration | Changes applied |
|---|---|
| 028 | Created `user_presence`. |
| 030 | Added response/report workflow columns; created `donation_report_events`, `donation_report_holds`, and `donation_credits`. |
| 031 | Added offer-cycle/context columns; created `donation_chat_contexts`, `donation_coordination_events`, `donation_report_evidence`, and `donation_correction_requests`. |

**Eight missing tables** were created. Migration 036 was already applied. No table was dropped and later consolidation/retirement migrations were not run; the application supports this pre-032 storage layout.

**Backup and preservation**

The full backup was restored to a separate rehearsal database first. An initial rehearsal caught a timestamp side effect in migration 030: recovering response metadata also changed three existing `matches.updated_at` values. Main was unchanged by that attempt.

The successful execution wrapper added `m.updated_at=m.updated_at` to that backfill statement. The original numbered SQL file was not edited. Source and effective executed-statement SHA-256 hashes are recorded in the rollout result.

Rehearsal and main checks confirmed that all original columns and rows were preserved across the **28 existing business tables**, excluding the migration ledger. This includes 17 users, 20 requests, 52 matches, 15 messages, and one confirmed donation report. Its new workflow metadata identifies historical administrative completion; no occurrence date or new credit was invented.

- Backup: `scratch/schema-fix-20261021-190716/bloodmatch-before.sql`.
- Backup SHA-256: `9b0ba4cd3af1a52cb78b202f40aff8cd0536e2f74e2656c72330703a43f3e0f1`.
- Successful rehearsal database: `bloodmatch_schema_rehearsal_20261021_190717`.
- [Detailed migration and preservation results](../scratch/schema-fix-20261021-190716/result.json).

A temporary maintenance lock blocked browser writers during the rollout and was removed afterward. All recurring workers remained off and `.env` was not edited.

**Browser retest on main**

The two existing synthetic accounts were temporarily reactivated. Routine request **64** was matched only to synthetic donor 139. No genuine member was notified and no real email was sent during this repair.

| Browser check | Result |
|---|---|
| Other member's profile opens | Passed |
| Donation reports screen loads | Passed |
| Donor submits an offer | Passed |
| Requester selects Accept & chat | Passed |
| Requester message reaches independent donor session | Passed |
| Donor reply reaches requester | Passed |
| Accepted donor opens the donation report form | Passed; cancelled without submitting a fictional donation |
| Presence heartbeat | Passed; no presence 500 responses |
| Confirm logout | Passed; HTTP 200 and signed-out landing form |

All **nine targeted checks passed**, with no JavaScript/server error events recorded during the retest. An additional check confirmed that both saved messages render inside each participant's actual conversation history, beyond sidebar previews. Full donation submission/acknowledgment/fulfillment and reminder/escalation were not tested in this checkpoint.

**Remaining limitations and cleanup**

Proof uploads still need GD and valid external document-encryption keys; the readiness check currently reports both unavailable on this PC. Creating the evidence table does not verify photo uploads. Automatic expiry persistence and recurring schedules also remain unverified.

Request 64 was closed, synthetic accounts were restored to their prior inactive/unavailable state, and their presence rows cleared. Test chat history was retained. The original 15 users were checked against their original snapshot; the donation report/credit counts were also checked after cleanup.

- [Other-member profile](../scratch/browser-check/schema-retest-evidence/01-other-member-profile.png)
- [Donation reports](../scratch/browser-check/schema-retest-evidence/02-donation-reports.png)
- [Accepted request chat](../scratch/browser-check/schema-retest-evidence/04-request-chat.png)
- [Requester receives donor reply](../scratch/browser-check/schema-retest-evidence/06-requester-received-reply.png)
- [Donation report form](../scratch/browser-check/schema-retest-evidence/07-donation-report-form.png)
- [Successful logout](../scratch/browser-check/schema-retest-evidence/08-logged-out.png)
- [Raw browser results](../scratch/browser-check/schema-retest-results.json)
- [Cleanup verification](../scratch/browser-check/schema-retest-finish.json)

Backups, credentials, sessions, and raw evidence remain local in ignored `scratch/` and should not be published.
