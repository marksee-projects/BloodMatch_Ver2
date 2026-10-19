# Main migration 031 and UI checkpoint — 2026-10-19

User authorized main migration and proceeding with the pending UI/notification plan. Applied only `031_offer_chat_donation_proof.sql` to **bloodmatch:3306**, SHA `3a5e0589b7f5625472c5d19dd574dfc2c4243060cb2bdd8f7a7761a4d2d4f66a`.

## Rollout evidence

- Preflight: wholly unapplied, no partial schema or duplicate occurrence-credit groups.
- Enabled the existing HTTP maintenance gate and temporarily paused only this workspace's enabled MembershipEmail/Safety tasks. No transaction killed, task installed or worker manually started.
- Full backup: `C:\BloodMatchBackups\bloodmatch-before-031-agent-20261019-175626.sql`, 413846 bytes. Completion marker and SHA verified: `2260e4ca08a4eee65d3b71f995c32ce555c6bd2b302570f0bb9d5a12a7ecfb63`. No restore rehearsal run.
- After: recorded/ready, four new tables, two columns, one unique index. **Do not rerun the apply.**
- Existing-column hashes matched for 20 users, 24 requests, 93 matches, one report and zero credits. No fabricated acceptance, occurrence or donation backfill.
- .env hash unchanged; maintenance removed; original MembershipEmail/Safety task enablement restored and both verified Ready.
- Read-only runtime queries passed for all 24 requests, five offer rows and 45 privacy-safe match rows. This is not an authenticated HTTP or concurrency suite.
- Detailed local record: ignored/private `backend/storage/maintenance/rollout031-result.json`. Backup remains outside Git.

## Implementation and checks

Implemented five-row pagination per donor tab with filtered totals, Previous/Next and page reset/clamping; avatars/right-side actions; restrained enrollment cards without drop icons; centered consistent report heading and removal of redundant banner; explicit picker buttons; visible main donation actions and clear form feedback; bounded report panel; quiet login/reconnect, fresh allowlist, cross-tab claims and rate limiting. Existing donation, consent, privacy, email and safety semantics remain.

Lightweight checks: 36 notification delivery/claim cases, 17 grouping/pagination, 13 date/status, 12 form/preview, ten consent presentation and 19 offer/proof policy checks (**107 total**); changed PHP lint and frontend build passed (existing bundle-size advisory). No browser surface connected, so visual layout, actual picker opening and native multi-tab behavior remain unverified by the agent.

Photo processing remains `gd:false`, `encrypted_key_ready:false`; optional-photo-free reports can be tested. Uploads still fail closed until the existing protected-document runtime is configured. No keys, plaintext fallback, historical conversion or .env changes introduced.

## User-run regression commands

From repository root; fixed test target, not main:

```powershell
& 'C:\xampp\php\php.exe' tests/offer_chat_db.php
if ($LASTEXITCODE -ne 0) { throw 'Stop and save the full offer/chat failure.' }
& 'C:\xampp\php\php.exe' tests/donation_completion_db.php
if ($LASTEXITCODE -ne 0) { throw 'Stop and save the full donation failure.' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_isolated.ps1 -Suite offer_chat_api.ps1
if ($LASTEXITCODE -ne 0) { throw 'Stop and save the full API failure.' }
```

The offer/chat DB suite adds six authorized-alert destination assertions after the previous twelve messaging-only assertions. Earlier 47-check evidence predates these extensions. Agent did not run database/PowerShell suites or real-email experiments.

Browser acceptance: pagination/filter reset and mobile actions; avatar fallback; suggested-message consent versus actual offer acceptance at all urgencies; visible donation controls and picker buttons; future-time failure beside Submit/preserved inputs; successful report then exact-version acknowledgment; silent old login notifications/correct badge; one cancellation success with donor bell-only update; capped/deduplicated fresh notifications across tabs and suppression in the exact report/chat. Retain privacy/late-report/safety/concurrency checks from the handoff.

If the dedicated test backend is still running, its process-only override remains test-targeted. Stop it and close that terminal before restarting the normal backend. A migration does not switch running servers' database connections or login sessions.
