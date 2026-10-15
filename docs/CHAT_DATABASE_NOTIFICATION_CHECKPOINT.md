# Chat, database and notification checkpoints

Updated 15 October 2026. Implementation is approved on `feat/chat-privacy-account-lifecycle`. This is the current status; historical planning-only notes in the original plan do not override it. Source preparation is not live acceptance. Database mutations and PowerShell checks remain user-run.

| Checkpoint | Source status | Acceptance still required |
| --- | --- | --- |
| Branch first | Feature branch created before implementation; initial lifecycle features are in commit `2aee912`; latest follow-up remains uncommitted | Review final diff; no push/deployment performed |
| Registration | Pending signup, OTP-only user creation, location payload handling, optional upload reset, popup/wiggle and shorter button labels prepared | Actual email/OTP, JSON/multipart, document handling, retry and uniqueness checks |
| Notifications/layout | Compact bottom-right five-second feedback, pause on interaction, adjacent confirmation buttons, form spacing and profile navigation prepared | Browser checks across desktop/mobile, themes, keyboard and reduced motion |
| Latest UI follow-up | Chat Reports paragraph removed/header centered; My Responses subtitle removed; plus/bell matching hover and subtle motion prepared | Build and visual checks after these edits |
| Chat | Verified active pairs, recipient consent, dedicated page, imported-thread consent gate and read cursors prepared | Two-account operation, profile/card entry points, unread badges, history, revocation and concurrency |
| Chat follow-up | Server rejects seven-day pending requests even without cleanup; stale page/account responses are ignored; account switch clears chat state | New expiry checks and delayed-network/account-switch acceptance checks |
| Moderation | Participant reports, bounded evidence, designated-admin review, evidence access logging and conflict denial prepared | Live nonparticipant-admin review, evidence authorization, audit failure and resolution checks |
| Request lifecycle | Expiry prompt, extension, linked renewal, expired profile section, reasoned closure, reminders and timely late-review source prepared | SQL transactions/concurrency, worker operation and completed-unit accounting |
| Profile/privacy | Other-member email omitted, owner location display, supplied organization contact and revised notice prepared | Missing historical locations need verified source/owner correction; no invented backfill |
| Account deletion | Password/DELETE confirmation, immediate access restriction and closure schedule prepared | Old-session denial, work closure, pending-review preservation and user-run deletion rehearsal |
| Retention | Review/apply runners, private-file handling, scoped holds and local backup aging prepared | Test erasure, holds, restore procedure, external backup coverage and actual maintenance scheduling |
| Single admin/demo accounts | Application restrictions, migration 027, source label and donor-window filter prepared | Reconcile database view; rehearse reviewed cleanup to fifteen accounts and sole admin; generate credentials privately |
| Database | Import repair helpers and migrations 025–027 prepared | Fresh backups, restore rehearsal, import repair, cleanup and migration apply; no schema removal justified by current evidence |

## Verification status

Earlier targeted offline suites and a frontend build passed before the latest follow-up. These are historical results, not a pass for the current working tree. No new tests/build, migration, cleanup, password reset, retention apply or scheduled-task installation was run during this follow-up.

Run from the repository root:

```powershell
powershell -ExecutionPolicy Bypass -File tests/check_chat_privacy_source.ps1
```

This performs PHP syntax checks, explicitly selected offline checks and the frontend build. It does not run live API tests or apply migrations. Follow [the implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md) for the separate backed-up test-database rollout, isolated suite and manual checks. Stop at the first failure; preserve its output before continuing.

## Remaining boundaries

- Database-dependent features remain unverified until the user completes the test-copy rollout. Last recorded `bloodmatch:3306` count was 97 members and 18 admins; this follow-up did not re-query or change it.
- Independent review/administrator transfer is a separately reviewed operation; no self-investigation or automatic privilege bypass was added.
- Timely donation reports filed before closure remain reviewable with evidence. A new post-closure report-submission workflow is not part of the prepared implementation.
- External backup/version-history retention and release readiness still require operational verification.
- ERD generation remains deferred. The 100/500-user load test remains cancelled.

See [the complete approved plan](CHAT_DATABASE_NOTIFICATION_PLAN.md) and [the current implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md).
