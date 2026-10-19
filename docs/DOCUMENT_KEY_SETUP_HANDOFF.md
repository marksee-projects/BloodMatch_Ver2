# Local document-key preparation — 2026-10-19

**Subsequently activated with explicit user authorization:** the existing prepared key is now configured in `dual` mode, GD is enabled, and the running port-8000 PHP backend was restarted and HTTP-verified. Apache and historical uploads were preserved. The 28 HTTP + 100 DB regression checks passed. See [activation results](DONATION_PHOTO_ACTIVATION_RESULTS.md). Earlier activation steps below are historical; do not generate another key or rerun setup.

User authorized inspection of existing configuration/files and preparation of LocalAppData keys and a protected backup only if no previous key was required. Registration, ID documents and donation proofs already share `DocumentStorageService` and `DocumentCipher`; no new encryption implementation is introduced.

## Completed locally

- Fresh read-only header review: **99 legacy files, 0 encrypted envelopes, no required/missing historical key IDs**. Current configuration is legacy with no directory/active key ID; GD is disabled. No document plaintext or secret key bytes were emitted.
- Prepared one 32-byte random key using the existing `database/document_keys.php` helper. No existing key was replaced. A matching recovery copy was made and verified (`key_ready=true`, `aes_256_gcm=true`, `backup_matches=true`).
- Key directory: `%LOCALAPPDATA%\BloodMatch\document-keys`.
- Protected backup: `%LOCALAPPDATA%\BloodMatch\document-key-backups`.
- Both directories disable inherited grants and allow only the current Windows account and SYSTEM. This is a same-PC recovery copy, not protection against loss of the whole disk; retain an additional protected off-device copy before relying on it for disaster recovery.
- `.env`, PHP configuration and historical uploads are unchanged. No historical conversion, DB suite/migration, scheduler changes or email sending performed. Upload encryption is **not active yet**.

The preparer defaults to read-only review. `-Prepare` refuses existing configured encryption or any encrypted file, refuses links/unexpected grants and ambiguous keys, reuses a single existing prepared key, and checks backup equality. A missing referenced historical key stops preparation. It never overwrites a key or backup and does not activate configuration.

## Activate the existing shared storage

In a fresh PowerShell terminal from the repository root, discover the prepared **ID only** (never print/read key bytes):

```powershell
$documentKeyDir = Join-Path $env:LOCALAPPDATA 'BloodMatch\document-keys'
$documentKeys = @(Get-ChildItem -LiteralPath $documentKeyDir -File -Filter '*.key')
if ($documentKeys.Count -ne 1) { throw 'Expected exactly one prepared key. Stop and review.' }
$documentKeys[0].BaseName
```

Add these settings to the existing `.env`, using the printed ID:

```dotenv
DOCUMENT_ENCRYPTION_MODE=dual
DOCUMENT_KEY_DIRECTORY="C:/Users/PC/AppData/Local/BloodMatch/document-keys"
DOCUMENT_ACTIVE_KEY_ID=<printed ID>
```

`dual` encrypts new registration/proof uploads while still reading historical legacy uploads. Do not use `strict`, run the converter, or copy these keys into the repository, database export or a public directory. Other computers/service identities need their own deliberate deployment configuration and authorized key access.

Verify the existing key/backup after saving `.env`:

```powershell
$documentKeyBackup = Join-Path $env:LOCALAPPDATA 'BloodMatch\document-key-backups'
& 'C:\xampp\php\php.exe' database/document_keys.php "--verify-backup=$documentKeyBackup"
if ($LASTEXITCODE -ne 0) { throw 'Key verification failed. Stop before uploading.' }
& 'C:\xampp\php\php.exe' database/review_document_key_requirements.php
```

Expect key_ready/backup_matches true, mode dual, active_key_ready true, missing_key_ids empty. Process-level `DOCUMENT_*` settings override `.env`; use the same runtime/identity as the backend and restart it after changing configuration.

## Minimal GD activation

1. Run `& 'C:\xampp\php\php.exe' --ini`. The current CLI uses `C:\xampp\php\php.ini` and has the existing `ext\php_gd.dll`.
2. In the serving PHP runtime's `php.ini`, change `;extension=gd` to `extension=gd`. Avoid adding a duplicate line. No installation/new library is needed for this XAMPP runtime.
3. Restart the existing PHP development server, or restart Apache through XAMPP if Apache serves the backend. CLI and Apache may load different configuration files.
4. Check `& 'C:\xampp\php\php.exe' -m | Select-String '^gd$'`; then perform the actual browser upload against the restarted backend. CLI readiness alone does not prove Apache readiness.

The reported upload/post limits were 40M; keep at least 5M per file, enough total request size for three photos plus overhead, and max_file_uploads at least 3.

Browser check: submit an actual past donation with an optional JPG/PNG/WEBP proof, then verify its private preview as an authorized participant. Check an existing registration document still opens. Existing authorization, 3-photo/5MB limits and metadata stripping remain enforced; photo upload alone does not confirm donation.

Lightweight checks performed: PHP lint; PowerShell syntax parsing; read-only file-header review; key/backup readiness and ACL verification. Existing eight synthetic document-storage checks were previously user-passed; no database test was rerun here.
