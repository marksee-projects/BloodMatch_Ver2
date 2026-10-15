# Chat, database and notification checkpoints

**Current application result, 15 October:** the user authorized production-database execution. `bloodmatch:3306` is repaired and migrated through 027, with 15 accounts and sole admin ID 3. Forty read-only integrity checks, all 15 credential checks and 18 application HTTP smoke checks passed. The API is online; [private account list](../backend/storage/maintenance/BLOODMATCH_DEMO_ACCOUNTS.md). See [the application results and outstanding operational checks](CHAT_APPLICATION_ROLLOUT_RESULTS.md). This supersedes historical source-only/test-only/pending-production notes below. Automatic scheduling and physical erasure acceptance remain pending.

Updated 15 October 2026. Implementation is approved on `feat/chat-privacy-account-lifecycle`. This is the current status; historical planning-only notes in the original plan do not override it. Source preparation is not live acceptance. Database mutations and PowerShell checks remain user-run.

**Latest authorization/result:** the user subsequently authorized the agent to perform the rehearsal. It is now complete on `bloodmatch_test`: source restore verified, imported chat repaired, 15 accounts/one designated admin, migrations 025–027 applied, 66 live API and 45 live schema/trigger checks passed. The application `bloodmatch` remains unchanged at 115 users with the same table/schema fingerprint. Production apply and browser/physical-retention acceptance remain outstanding. [Full results and limitations](CHAT_REHEARSAL_RESULTS.md) supersede the earlier pending test-rehearsal notes below.

| Checkpoint | Source status | Acceptance still required |
| --- | --- | --- |
| Branch first | Feature branch created before implementation; initial lifecycle features are in commit `2aee912`; latest follow-up remains uncommitted | Review final diff; no push/deployment performed |
| Registration | OTP-only creation and JSON location persistence passed live on test; upload UI prepared | Real email transport, multipart/document handling and remaining retry/uniqueness paths |
| Notifications/layout | Compact bottom-right five-second feedback, pause on interaction, adjacent confirmation buttons, form spacing and profile navigation prepared | Browser checks across desktop/mobile, themes, keyboard and reduced motion |
| Latest UI follow-up | Chat Reports paragraph removed/header centered; My Responses subtitle removed; plus/bell matching hover and subtle motion prepared; user-run build passed | Visual checks after these edits |
| Chat | Live test passed consent, two-way delivery, cursor polling, read/unread, imported-thread denial and verification revocation | Browser profile/card entry points, full history UI and concurrent interaction |
| Chat follow-up | Server rejects seven-day pending requests even without cleanup; stale page/account responses are ignored; account switch clears chat state; user reports source runner passed | Delayed-network/account-switch and live expiry acceptance checks |
| Moderation | Live report submission, nonparticipant-admin evidence review, resolution, audit records and conflict denial passed | Browser review and simulated audit-storage failures |
| Request lifecycle | Expiry prompt, extension, linked renewal, expired profile section, reasoned closure, reminders and timely late-review source prepared | SQL transactions/concurrency, worker operation and completed-unit accounting |
| Profile/privacy | Other-member email omitted, owner location display, supplied organization contact and revised notice prepared | Missing historical locations need verified source/owner correction; no invented backfill |
| Account deletion | Live password-confirmed closure and session revocation passed on owned fixtures | Active-work closure, pending-review preservation and aged physical erasure |
| Retention | Test review/apply completed; 53 old throttle records removed; no account/document erasure due | Seeded isolated-file erasure/holds, backup coverage and actual maintenance scheduling |
| Single admin/demo accounts | Application cleanup completed: 15 accounts, sole admin ID 3, all private credentials verified; API source label/count checked | Browser donor-window/status filter acceptance |
| Database | Application import repaired; migrations 025–027 applied; 40 SELECT-only integrity checks passed, plus earlier test trigger checks | No schema removal justified; remaining browser/lifecycle acceptance |

## Verification status

User supplied the completed source runner result on 15 October: `Source checks passed` and Vite 5.4.21 production build passed in 41.50 seconds, 4,724 modules. The 622.54 kB JavaScript bundle emitted a size warning, not a build failure. This confirms that runner's syntax/offline suites/build at the time of the run; it does not verify live APIs, migrations, browser behavior or retention.

Subsequent rollout review adds a new demo-cleanup guard and six pure policy checks: the legacy cleanup is blocked if any migration-025 workflow table exists, even if empty. The review output now reports `cleanup_allowed` and `cleanup_blocked_reason`. These maintenance-only additions have not been run; the frontend has not changed since the supplied successful build. No database mutation, credential reset or scheduled-task installation is confirmed.

Run from the repository root:

```powershell
powershell -ExecutionPolicy Bypass -File tests/check_chat_privacy_source.ps1
```

This performs PHP syntax checks, explicitly selected offline checks and the frontend build. It does not run live API tests or apply migrations. Follow [the implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md) for the separate backed-up test-database rollout, isolated suite and manual checks. Stop at the first failure; preserve its output before continuing.

Next checkpoint: run the read-only `Review` action in `scripts/chat_demo_maintenance.ps1` against both named databases to reconcile targets/counts and establish whether legacy cleanup is still eligible. Review creates a private report file; it does not change database rows. Do not run cleanup if `cleanup_allowed` is false. It requires a fresh impact review of the already-enabled privacy workflows.

## Remaining boundaries

- User-run diagnostics now identify both targets on 127.0.0.1:3306. `bloodmatch_test` contains 281 accounts (202 active members, four deactivated members, 75 active admins) and no designated-account candidate. `bloodmatch` contains 115 active accounts (97 members, 18 admins); account 3 matches `markseejr@gmail.com`, is active, verified and admin, and the supplied cleanup-blocked reason is null. No account correction/promotion is needed to establish the application administrator. Test-data reconciliation remains pending: back up both targets before replacing the unrelated test dataset with an application rehearsal copy. Backup, restore and cleanup have not been confirmed.

- Database-dependent features remain unverified until the user completes the test-copy rollout. Last recorded `bloodmatch:3306` count was 97 members and 18 admins; this follow-up did not re-query or change it.
- Independent review/administrator transfer is a separately reviewed operation; no self-investigation or automatic privilege bypass was added.
- Timely donation reports filed before closure remain reviewable with evidence. A new post-closure report-submission workflow is not part of the prepared implementation.
- External backup/version-history retention and release readiness still require operational verification.
- ERD generation remains deferred. The 100/500-user load test remains cancelled.

See [the complete approved plan](CHAT_DATABASE_NOTIFICATION_PLAN.md) and [the current implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md).
