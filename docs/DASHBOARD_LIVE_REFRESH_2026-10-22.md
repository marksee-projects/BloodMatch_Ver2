# Dashboard automatic refresh — 2026-10-22

Operational screens use React Query polling while the browser tab is visible. The shared policy refreshes every 10 seconds and on mount, focus, and network reconnect. Background refresh retains the displayed data.

New coverage includes admin analytics, users, audit logs, and chat reports; member profile, responses, and notification history; and donor match details. Existing admin overview, verification, and donation-report views already use the shared policy. Requester match details retain their 5-second interval. Existing feed/request-history intervals (5 seconds) and demand-map interval (30 seconds) remain in place.

Filters, pagination, and unsaved review drafts remain local to their views. Profile polling pauses during editing or uploads, and match polling pauses during reporting or actions.

## Verification

- Production frontend build passed. Vite still reports its existing bundle-size warning.
- Headless Chrome exercised the actual frontend with intercepted synthetic API responses. All 12 checks passed: automatic data changes in admin overview, analytics, users, audit logs, chat reports, and member profile; preserved user filters/edit draft, audit draft, and chat-review findings; paused profile polling during editing; stopped hidden-tab polling and resumed on visibility; refreshed on network reconnect; retained analytics on a temporary refresh failure and recovered afterward.
- The audit draft assertion is included in the audit-log refresh check. No main-database records were changed by these browser checks. These checks verify frontend refresh behavior, not backend authorization or every feature on each page.
- `git diff --check` passed.

Automatic screen refresh is independent of the email and safety scheduler switches. This change does not enable recurring workers.
