# Donation form and photo runtime checkpoint — 2026-10-19

**Current result:** user-authorized activation and end-to-end verification completed. Live backend has GD, dual storage and readable existing key; 28 HTTP and 100 DB checks passed. See [activation results](DONATION_PHOTO_ACTIVATION_RESULTS.md). Prepared-only/runtime-blocked text below is historical.

**Subsequent user-authorized preparation:** fresh review confirmed 99 legacy files and no older key requirement. One external LocalAppData key and matching private backup are now prepared using the existing helper. No configuration activation or historical conversion was performed. See [setup results and exact activation/GD steps](DOCUMENT_KEY_SETUP_HANDOFF.md). Earlier decision-needed text below is historical.

User requested removing the Philippine-time label and seconds, and fixing report photo uploads. Follow-up explicitly requires reuse of registration encryption/services and existing keys; do not create or replace keys unnecessarily.

## Completed source changes

- Shared `DonationDateTimeFields` now says **Donation time**, uses minute increments and displays HH:mm without seconds. Existing stored occurrence precision is preserved until the user edits the time; UTC conversion, default Asia/Manila interpretation and date/restriction policies remain unchanged. Removing the displayed timezone suffix follows this latest explicit UI instruction.
- Matches report submission now displays the complete error inside the modal, retaining date/photos for retry, instead of relying on a truncated toast. It checks future occurrence before attempting upload, consistent with the existing chat form and backend rule.
- Twenty chat-modal, thirteen date/status and twelve proof-form/preview offline checks pass. Production build passes (4,740 modules; existing chunk-size advisory), as does the diff whitespace check. No database/PowerShell suite, migration, real email, key generation, historical conversion, real `.env` or PHP configuration change performed.

## Read-only runtime findings

- `PendingRegistrationService::stage`, `DocumentController::upload`, and `DonationProofService::prepare` all call the same `DocumentStorageService::store`; it uses the same `DocumentCipher` and `DOCUMENT_*` configuration. There is no separate donation encryption key infrastructure to replace.
- The current local `.env` has no `DOCUMENT_*` configuration. CLI/process review reports `mode=legacy`, directory/active ID unconfigured, `key_ready=false`, `gd=false`. The HTTP error in the supplied screenshot also comes from the explicit legacy-mode guard.
- A header-only inspection counted **99 legacy, 0 encrypted, 0 unreadable** local stored document files. No document contents, personal metadata or key bytes were printed. Protected/authorized document access is separate from encryption at rest; the existing legacy path accepts registration documents without encryption.
- XAMPP CLI loads `C:\xampp\php\php.ini`; `extension=gd` is commented out and `php_gd.dll` exists. The web-serving runtime must be checked/restarted by its operator after configuration.
- No existing key was found in the previously recommended `$env:LOCALAPPDATA\BloodMatch` key location or its known backups. This is not an exhaustive search of the computer, and no key-loss conclusion is made about other deployments.

## Decision needed before configuration

If an existing external registration key/configuration belongs to another runtime/deployment, supply its directory and active key ID (never key bytes), so readiness and a backup can be verified and reused. Otherwise confirm initial activation of the already implemented encryption setup and choose an external protected key-backup folder. Do not create another key merely to bypass missing configuration.

Existing setup/backup procedure: `docs/REMAINING_FIXES_CHECKPOINT.md`, section 5; synthetic document-storage checks were previously user-passed (8), and need not be repeated just to generate another key. Use dual mode for new encrypted uploads while preserving access to historical legacy documents; historical conversion remains separate. Enable GD in the serving runtime, preserve photo metadata stripping and private evidence access, then verify an authorized optional-photo upload/retrieval. No plaintext donation-photo fallback is approved.

The supplied screenshot selected a future occurrence (October 20 while the clock showed October 19). Fixing storage does not permit reporting a future donation. Optional-photo-free submission remains available with a valid actual occurrence.
