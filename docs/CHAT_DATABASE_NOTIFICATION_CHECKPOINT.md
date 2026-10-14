# Chat, database and notification checkpoints

Updated 15 October 2026. Implementation approved; source changes are prepared on `feat/chat-privacy-account-lifecycle`. Historical pending notes below are superseded by this status and [the current implementation handoff](CHAT_PRIVACY_IMPLEMENTATION_HANDOFF.md). Database migrations and live PowerShell checks are supplied for the user to run; the 100/500-user load test remains cancelled and no ERD generation is included.

| Execution checkpoint | Current result |
| --- | --- |
| Branch first | Created feat/chat-privacy-account-lifecycle before application edits |
| Registration and location | Pending-registration/OTP source prepared; 11 transaction/OTP checks and 10 location-selection checks pass; live JSON/multipart, email and upload checks pending |
| Feedback/layout/navigation | Bottom-right five-second compact notices, wiggles, logout/form/nav/card changes prepared; production build passed; visual/mobile/keyboard verification pending |
| Chat consent/full page/reports | Source and migration 025 prepared; 29 offline chat checks pass; live tests and administrator evidence/conflict review pending |
| Request lifecycle | Extension, linked renewal, expired tab/prompt, reasoned closure, reminders and timely late-review source prepared; 22 policy checks pass; SQL/concurrency/worker verification pending |
| Privacy/deletion/retention | Supplied organization/contact installed; password-confirmed closure and retention runners prepared; erasure/holds/backups and daily scheduling unverified |
| Single admin/demo accounts | API restrictions and trigger migration 027 prepared; source label/cooldown filters prepared; application data remains unchanged until reviewed user-run cleanup/migrations |
| Final verification | Request/profile 21-check suite, Home callbacks, management/privacy checks, notification/helper checks and PHP/PowerShell syntax checks pass; live APIs, restore, migrations, credentials and browser checks remain user-run |

| Checkpoint | Current status | Completion evidence required |
| --- | --- | --- |
| 1. Baseline audit and backup | Read-only audit and SQL backup completed; import repair and demo-cleanup scripts prepared | Restore backup into bloodmatch_test, review deletion/schema impacts, rehearse repair successfully |
| 2. Shared feedback and registration | Shared popup implementation prepared and offline checks passed; later amendments pending | Compact bottom-right popups dismiss after five seconds; replace dropdown/upload red paragraphs with popup plus brief accessible wiggle; fix optional-ID stale errors; remove Next: prefixes; create account only after valid OTP; verify retry/expiry/uniqueness and temporary-upload cleanup |
| 3. Request lifecycle | Existing needed-by expiry confirmed in source; approved improvements pending | Owner prompt when deadline expires, deferred prompt if offline, no automatic renewal; verify expiry worker/filtering; deadline extension and explicit renewal; profile Expired posts tab; reasoned closure without fabricated donations; reminders and evidence-based late-report review |
| 4. Accepted chat | Verified-only chat policy prepared; live operation unverified; consent workflow pending | Diagnose imported/demo conversations and profile Message action; profile/card requests and successful I can help create/reuse pending consent; recipient acceptance gates backend access and normal chat list; review existing-chat transition |
| 5. Single administrator and demo accounts | Configured bloodmatch still has 97 members and 18 admins; user's reported 15-row view unresolved | Match UI/API/database target and filters before cleanup; retain only markseejr@gmail.com as administrator; remove promotion UI and enforce backend rule; add derived cooldown status/filter independently of deactivation; rehearse final fifteen-account cleanup and private credential delivery |
| 6. Final verification | Live checks pending; ERD generation deferred by latest user instruction | User applies reviewed migrations on test copy; verify integrity, functional chat/registration/request/staff workflows, unread badge and browser feedback; do not generate/export an ERD |

## Implementation dependencies

Account deletion and moderation checkpoint: implement identity-confirmed permanent closure, immediate access revocation and scheduled category-specific erasure; preserve only justified evidence/history. Add participant reports with designated-administrator access limited to reported messages and necessary context, with backend authorization and access logs. No unrestricted staff chat browsing is approved. Retention periods remain proposed until approved and enforced; privacy-notice contact details remain unresolved.

Privacy/chat/profile checkpoint: revise privacy notice against actual controls, hide profile email in UI and other-member API, add dropdown expansion to a dedicated accepted-chat/message-request page, investigate registration-to-profile location persistence, and replace center Profile navigation with page-card and avatar-menu access. The inspected screenshot account (ID 3) currently has a null location reference; the origin of that missing data is unresolved. These are pending implementation, with owner/staff authentication data preserved and no invented address backfill.

Latest UI checkpoint: simplify logout to a title and balanced side-by-side buttons; remove paragraph-style popup copy; reduce request-modal header spacing and separate form groups; remove unintended chat-input hover lines; remove the plus icon's circular background; use avatar-only profile hover highlighting while retaining motion and keyboard focus. All remain pending implementation. Allowing admin requests and retaining Responses are documented recommendations, not new approved policy changes.

1. Restore and audit the isolated database before applying migrations. Preserve historical messages, completed donations and audit records.
2. Finalize pending-registration, chat-consent, renewal/closure and late-report data decisions. Prepare migrations and rollback/backup guidance; do not remove tables/columns without evidence of redundancy.
3. Implement registration/feedback, lifecycle/consent and administrator enforcement. Update seeders, maintenance tools and test fixtures for the resulting schema/policies.
4. Rehearse final repair and account cleanup against that schema. The earlier prepared scripts do not implement the newly approved pending features; obtain a fresh impact review before final cleanup.
5. Complete user-run live functional checks and browser checks, then provide credentials privately. Do not generate/export an ERD. Do not claim live chat or restore verification until those checks pass.

## Decisions to resolve during implementation

- Retention: review/approve the proposed category-specific schedule in the plan, implement erasure/anonymization and backup aging, and replace unsupported current-notice claims only with verified behavior. There is currently no confirmed automatic general purge policy.

- Renewal: reopening with preserved history versus creating a linked new request; choose after checking matches/reports and audit relationships.
- Late reports: reliable actual donation-time evidence, which reports remain reviewable after expiry, and the effect on confirmed-unit accounting and donor-window timestamps.
- Message requests: expiry/retry/rate limits and an explicit reviewed transition for existing conversations without consent records.
- Reminder cadence and closure representation: avoid notification spam and distinguish externally met/cancelled needs from verified-donation fulfillment.
- Account creation before OTP: reviewed handling of existing unverified users, pending-registration retention and secure temporary-document cleanup.

## Verification already recorded

Offline checks passed: 27 chat-policy checks, 5 session-identity checks, 9 request-popup checks and 16 registration/notification helper checks. Production build and PHP/PowerShell syntax checks passed. The older profile UI test has a reproduced baseline fixture failure; browser rendering, live APIs, backup restore, repairs, cleanup and credential resets remain unverified/unapplied. No passwords are stored in tracked documentation.

See [the complete plan](CHAT_DATABASE_NOTIFICATION_PLAN.md) and [the execution handoff](CHAT_DATABASE_NOTIFICATION_HANDOFF.md).
