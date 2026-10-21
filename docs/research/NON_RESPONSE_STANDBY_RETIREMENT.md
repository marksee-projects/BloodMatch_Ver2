# Non-response standby retired ? October 21, 2026

The owner approved removing the unanswered-opportunity timeout and acknowledgment workflow. Missing an opportunity no longer creates an account-wide donor-matching hold.

- Notification issuance no longer creates response timers.
- Matching and offer eligibility ignore legacy non-response flags. Profile and auth expose the old API field as null for compatibility; the banner/button are removed.
- The legacy service and authenticated acknowledgment endpoint remain inert so older callers cannot create holds, modify records, send standby alerts, or trigger rematching.
- The application safety runner retains request expiry but no longer processes response timers.
- Donation cooldowns, post-donation windows, unresolved report holds, manual availability, compatibility, and other eligibility checks remain. Existing notification deduplication, emergency behavior, and delivery limits are unchanged.
- Historical tables, migrations, audit records, and notifications remain intact. No schema/data cleanup or bulk rematching was performed. Matches previously closed by an older eligibility refresh are not automatically reopened by this change; normal matching refresh paths apply.

## Verification

Offline retirement/eligibility checks: 11 passed. Profile rendering with legacy 42/48-hour flags and a donation cooldown: 8 passed. Donation-completion policy: 32 passed. Offer/proof UI: 12 passed. Notification feedback: 9 passed. Production frontend build and changed PHP syntax checks passed. Browser interaction and database-backed execution were not tested; no database, application worker, or real notifications were used.

The old non_response_48_db.php and non-response sections of safety_followup.php document the retired behavior and are superseded for that feature by non_response_48_offline.php; do not treat their old timeout expectations as current requirements. The baseline requirements and earlier proposal still describe historical behavior and need separate review before manuscript updates. No manuscript, ERD, or data dictionary was edited.
