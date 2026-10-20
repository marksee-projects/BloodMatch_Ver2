# Focused48h Implementation and Verification Results

20 October 2026 (Asia/Manila). Scope: approved new-opportunity 48h change and checklist reconciliation, not a new full audit. Supersedes the prepared-only statements in the [proposal](NON_RESPONSE_48_HOUR_CHANGE_PROPOSAL.md) for the selected policy.

## Changes made

- [NonResponseStandbyService](../../backend/src/Services/NonResponseStandbyService.php) now uses a separately named RESPONSE_HOURS=48 for **new** per-request/donor/generation issuance. Same-generation retries remain insert-no-op; existing stored due_at and active standby are not retimed or cleared. This is the user's approved temporary legacy 42h exception, not an unnoticed failure to align with the paper.
- Timeout uses request → user → match → opportunity locks and the current shared request-eligibility evaluator, including live membership/email, enrollment, age/consent, effective availability, blood compatibility, report holds and post-donation windows. Obsolete/ineligible **specific pending timer** becomes invalidated, with reasoned audit; no match, report, donor availability, donation anchor or unrelated opportunity is edited by invalidation.
- Invalidated rows cannot be revived by eligibility recovery or same-generation replay. A genuine newer issuance has a separate48h row; normal supersession, response resolution, deduplication and acknowledgment due-at watermark rules remain.
- Active non-response standby is checked before a fresh sanction; no duplicate transition or automatic recovery. Invalidations and standby transitions fail/roll back if their audit cannot be recorded. A bounded internal optional ID scope permits tests/recovery to avoid processing unrelated opportunities; normal safety ticks continue their all-due keyset drain.
- [ProfilePage](../../frontend/src/pages/ProfilePage.jsx) and new standby alerts use deadline-neutral wording, accurate for mixed 42h/48h cohorts. Historical saved notifications are not rewritten.
- No post-donation setting/seed, report hold, report reminder 24h or escalation 48h code was changed. No migration is required by prospective issuance; no existing timer/data adjustment was performed.

## Checks actually executed

| Check | Actual result | Limit |
| --- | --- | --- |
| PHP lint: service, new in-memory suite, DB suite and isolation helper | PASS | Syntax, not SQL execution |
| `php tests/non_response_48_offline.php` | **72 PASS** | Real service/evaluator over an in-memory PDO adapter and synthetic notifications/audit. Covers new 48h, exact/pre-boundary, legacy 42h, active state, invalidation/recovery, supersession, one new genuine obligation, audit rollback, unrelated scopes and 105-row drain. Not MySQL semantics or real locks |
| `php tests/non_response_offline.php` | **4 PASS** | Existing due-at/consumed watermark boundaries |
| `php tests/donor_age_offline.php` | **23 PASS** | Shared age/consent, account/email, availability, compatibility and restriction gates; no DB projection proof |
| `php tests/donation_completion_offline.php` | **32 PASS** | Includes occurrence anchor/hold and original 24h/48h report policy |
| `php tests/offer_chat_offline.php` | **19 PASS** | Existing authorized chat/report access during donor restrictions and protected proof policy |
| `node tests/non_response_profile_ui.mjs` | **8 PASS** | Current profile rendered with synthetic cache/auth state; deadline-neutral acknowledgment text for both cohorts; no browser/API |
| `node tests/home_ui.mjs` | PASS | Existing rendered/callback suite plus chapter+Emergency/Urgent/Routine, zero-result and reset checks. Source-backed replacement UI coverage; mocked aggregate/response data, no live API |
| `npm.cmd run build` in frontend | PASS, 4744 modules | Existing >500kB bundle warning; no production server restart |
| `node tests/profile_ui.mjs` | FAIL: `member: navigation is inside the profile header` | Unrelated existing fixture uses stale profile cache key; control run substituting unchanged HEAD ProfilePage source **in memory** fails at the identical assertion. No unrelated fix or green-suite claim |
| `php tests/non_response_48_db.php` | **BLOCKED / exit1,0 checks** | Explicit test-only preflight exits before schema/target verification and before any fixture writes: listener sends no initial greeting. No SQL/concurrency PASS claim |

**158 counted offline/in-memory checks passed**, plus the Home rendered/callback suite and frontend build. Counts do not include prepared but unexecuted DB assertions. No historical suite is presented as freshly passed.

## Test target, isolation and blocked database diagnosis

The new [DB suite](../../tests/non_response_48_db.php) and [test isolation bootstrap](../../tests/helpers/non_response_isolation.php) hard-code 127.0.0.1:3306/bloodmatch_test with isolation=1 and no fallback. The diagnostic printed only target/driver/configuration-present booleans, never credentials. On a healthy connection the suite must first verify SELECT DATABASE(),@@port, required 022/030/031/032 migration markers and response schema before writes.

The suite uses a test-only AppConfig base path and per-run temporary session/key/log/barrier directory; PHPMailer is replaced before use with the existing no-socket fake transport. Synthetic in-app notifications persist only in disposable fixture transactions/owned race rows. Main fixtures roll back; committed race fixtures have nonce ownership checks and scoped cleanup. Append-only synthetic audit rows are retained on the disposable target by design. None of that fixture lifecycle ran while preflight was blocked.

Read-only diagnostics actually performed:

- Windows netstat: PID 24912, `c:\xampp\mysql\bin\mysqld.exe`, listening on 0.0.0.0:3306. No 3307/3308 listener was observed. The XAMPP my.ini client/server ports both specify 3306.
- Server binary: MariaDB 10.4.32; bundled mysql.exe: MariaDB 10.4.32. PHP 8.2.12 uses mysqlnd 8.2.12 with PDO MySQL enabled. There is no observed version-negotiation error because negotiation never starts.
- Raw IPv4 TCP connection succeeds, but **zero initial greeting bytes** arrive within 3s. IPv6 ::1 also accepts a connection but its greeting read times out. No credentials or database-selection packet is sent by these probes.
- Bundled mysql client with --no-defaults, explicit TCP/host/port/test database and bounded connect timeout returns **ERROR 2013 (HY000), handshake: reading initial communication packet**. Disabling SSL has the same result; an outside-sandbox read-only attempt has the same result.
- The server error log ends at “Server socket created on IP: '::'”; no subsequent “ready for connections” message is present in the inspected tail. Process CPU stayed 0.171875s during the observations.

**Exact observed failure:** a listening MariaDB process accepts TCP but does not issue the initial server greeting; authentication, database existence/schema verification and SQL execution have not been reached. A wrong database name, wrong password, application fallback or SSL negotiation is not an evidenced explanation for a missing pre-auth greeting. The internal cause of the server initialization/stall cannot be established from these read-only observations. No repair, restart, shutdown, port switch, application-database connection, service configuration or scheduler change was performed.

## Prepared SQL/concurrency acceptance, still required

The test-only suite includes48h issuance/exact microsecond boundary,43h new-not-due and legacy 42h preservation, retries/deduplication, acknowledgment/future timer/watermark, supersession, live ineligibility and non-revival, report/hold preservation and independent settings assertions. Multi-process cases explicitly wait for an InnoDB LOCK WAIT while the parent holds the request lock, then exercise response-before-timeout, timeout-before-response, expiry-before-timeout, timeout-before-expiry and two concurrent ticks. These are prepared assertions, **not verified results** until the suite can run on the healthy, verified disposable target.

After the shared server is made responsive by its operator, run only:

```powershell
& C:\xampp\php\php.exe tests\non_response_48_db.php
```

It refuses a different target or missing migrations; it does not create/drop/migrate a database. Review any failure/retained diagnostic artifacts rather than bulk-cleaning other test data. No application data should be used to bypass a blocked test target.

## Local rollout/restart and readiness

New source is in the shared workspace. A PHP server without persistent opcode caching normally reads changed PHP on its next invocation; a Vite dev server normally notices the JSX change. A separately deployed/cached runtime or long-lived worker must be refreshed through its normal local deployment process **after** SQL/race acceptance; this task did not restart it. The production frontend build was generated locally; no hosting/deployment was performed. Existing worker activation/health remains unverified, and no schedule was activated. No database migration/backfill or changes to standby_hours/cooldown_days are required.

Keep old due_at, active standby, consumed_through and existing notices through deployment/rollback. New issuance after the runtime picks up the change gets48h; a true later generation supersedes pending old generations as before. Acknowledgment remains the only normal non-response recovery and cannot override another restriction.

**Ready for an accurately qualified working SOP/objective draft:** the nine source objectives are mapped, the user-approved search-to-demand replacement is recorded, and the detailed 71-item coverage is retained. **Not ready for final verified implementation sign-off:** real SQL/races are blocked, recurring processing and real delivery are unverified, automatic demand refresh is absent, and unrelated actual gaps remain in the checklist. Do not rewrite the final manuscript, ERD or data dictionary at this checkpoint.
