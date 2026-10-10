# Focused profile and notification checkpoint

Target: **bloodmatch_test, 127.0.0.1:3306**. This checkpoint does not use the application database bloodmatch. Existing PHP/MySQL, canonical migration 016 and Bataan location seed, and the previously verified test schema 021/022 are prerequisites. Stop other test/database writers while running the fixture test. No application server, scheduler or SMTP is needed.

## Read-only preflight

From the repository root in PowerShell:

```powershell
& 'C:\xampp\php\php.exe' tests\registration_location_checkpoint.php --review
if ($LASTEXITCODE -ne 0) { throw 'Location checkpoint preflight failed; stop.' }
```

Expected: JSON identifies bloodmatch_test, port 3306, writes=false, smtp=false, and an active municipality/barangay pair. Missing reference/schema prerequisites cause a failure; stop and inspect rather than applying any migration automatically.

## Migration application

None required or authorized for these improvements. No historical account correction/backfill.

## Disposable fixture test (after successful review)

```powershell
& 'C:\xampp\php\php.exe' tests\registration_location_checkpoint.php
if ($LASTEXITCODE -ne 0) { throw 'Focused registration/location checkpoint failed; stop.' }
```

Expected: 17 PASS checks and “Registration/location/notification checkpoint passed; disposable transaction rolled back; no files or SMTP.” Tests JSON and multipart-style registration fields through AuthService, canonical persistence/coordinates, the own-profile API projection, municipality-only/omitted optional locations, cross-municipality/spoofed selection and raw-coordinate rejection before account creation, new name-based opportunity/response text, and quiet read/creation timestamp preservation. No real ID file is uploaded; actual file-bearing browser submission remains a manual check. No previous broad suite is repeated.

Cleanup: the test encloses its four users, one request, related alerts/offers/opportunities and test audit entries in one transaction and rolls it back on success or error. No files are created, SMTP is disabled before .env loading, and the shared attempt ledger must remain unchanged. A terminated process loses its PDO connection and rolls back the transaction; no manual bulk deletion or ledger reset is needed. Auto-increment values may advance. Exit code must be zero. The agent has not run this database test.

## Lightweight evidence

Agent-run passed: 10 offline PHP location selection checks; 16 offline JSON/multipart payload and legacy notification presentation/link checks; mocked Home/card checks including collapsed Responded visibility and removal of the redundant expanded action; syntax checks for all 10 affected/new PHP files; git diff --check; final npm.cmd frontend build. The existing >500 kB bundle warning remains. These checks use no database or real emails and are not browser evidence.

## Remaining manual checks

1. Check Responded at the top of collapsed/expanded cards on desktop and a narrow screen; response confirmation/disabled behavior remains intact.
2. Inspect new and older notifications in both flyout and Notifications page: relevant names/descriptions, no visible request numbers, correct links. Harmless edits/repeated processing must keep read notifications read and create no extra popup.
3. Register in the isolated test environment with municipality and barangay, both without and with an ID upload; after login/reload, verify About municipality and Edit profile's selected municipality/barangay. Change municipality before submitting and ensure the previous barangay is cleared. No selection shows “Municipality not provided.” Use disposable test addresses, not a real email delivery experiment.

Only the previously reported Emergency response followed by incompatible blood-type edit notification is user-verified. Other browser checks and this new database suite remain pending. Operational scheduling, real queued transport and deployed rollout remain deferred; the earlier donor-cap reservation/outcome gap is not changed here.
