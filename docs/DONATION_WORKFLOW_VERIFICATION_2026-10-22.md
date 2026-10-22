**Proof storage and full donation workflow — October 22, 2026**

Proof prerequisites are configured, and the requester/donor completion journey passed on main (`bloodmatch:3306`), using the labelled synthetic accounts only. This checkpoint extends the earlier missing-table repair; it does not establish that all administrative or scheduled features are verified.

**Configuration completed**

- Enabled the installed GD extension in `C:/xampp/php/php.ini`.
- Used the existing project helper to prepare an AES-256-GCM document key and matching backup outside the repository, restricted to the Windows user and SYSTEM. No previously configured key or encrypted document was replaced.
- Set `.env` to `DOCUMENT_ENCRYPTION_MODE=dual` with the prepared key directory/ID. Existing legacy uploads remain readable; new uploads are encrypted. No historical document conversion was performed.
- Primary key directory: `C:/Users/bossm/AppData/Local/BloodMatch/document-keys`.
- Key backup directory: `C:/Users/bossm/AppData/Local/BloodMatch/document-key-backups`.
- Matching-key verification passed. Keys themselves were never printed, stored in the database, or committed to the repository.
- Original `.env` and PHP configuration were privately backed up in ignored `scratch/proof-setup`.
- Restarted the verified PHP preview server on port 8000 and the standalone XAMPP Apache instance to load GD. The default Apache service restart command found no installed service; the verified standalone processes were then restarted successfully.

**Simulation and results**

Synthetic requester **138** and donor **139** used separate Chrome contexts. Routine test request **65** was matched only to donor 139, and proof fixtures visibly state that they are synthetic and no donation occurred. A fresh full database backup was saved before this run at `scratch/browser-check/full-donation/main-before.sql` (SHA-256 `6574d873974e8bda02f46f419c31dd3a867c6628f05f4bd7df39d60472e60942`).

| Check | Result |
|---|---|
| Donor offers; requester explicitly accepts | Passed in browser; accepted request-scoped chat opened. |
| Four proof files selected | Rejected by browser; three-file limit enforced. |
| PNG, JPG, WEBP submitted together | Passed through the browser multipart form. Server re-encoded the images; WEBP input was stored as JPEG. |
| Donor report before requester response | Report **2** was `PENDING` / `awaiting_requester`; request remained `OPEN`; zero donation credits existed. |
| Report-specific donor hold | Created on submission, independently of availability preference. |
| Encrypted storage | All three files had encrypted envelopes and decrypted into valid raster images. |
| Protected preview | Requester opened and decoded all three images through the private proof preview. |
| Anonymous proof access | Denied with an authentication/access error. A separate unrelated authenticated-member case was not exercised. |
| Requester selects Donation received | Passed in browser; report became `CONFIRMED` / `completed`, with completion basis `mutual`. |
| Fulfillment | One-unit request 65 became `FULFILLED`; the donor match completed and exactly one tracking credit was recorded. |
| Hold release | Hold was released with type `donation_recorded`. |
| Donor restriction/history | Browser showed the completed report and post-donation restriction; backend recorded standby and the occurrence-based restriction anchor. |
| Repeated acknowledgment | Returned unchanged; no second completion or credit. |
| Stale report version | Rejected with HTTP 409. |
| Donor self-acknowledgment | Rejected with HTTP 403. |
| Duplicate completed report | Rejected with HTTP 409; credit count remained exactly one. |

The replay/version/authorization checks used the authenticated browser sessions to make supplemental HTTP requests. They were not separate visible UI actions.

One initial preview assertion used an explicit `[role=dialog]` selector against a native `<dialog>`, so it reported a test-script failure despite the preview opening. The corrected check used the native dialog's accessible role and verified actual image decoding for all three files. This was an automation selector issue, not a product failure. Raw attempts remain in the local results file.

The donor response preceded the selected donation time; no historical response/report timestamps were backdated to bypass chronology rules.

**Preservation and final state**

- The original 15 users, existing donation report, and five existing document files were compared with their snapshots and remained unchanged.
- Synthetic accounts were restored to inactive/unavailable after testing. The synthetic donor's completed-test restriction anchor was retained with its test history.
- Request 65, report 2, three encrypted proofs, and the single synthetic credit were retained for traceability. They are demo/test records on main and affect aggregate test counts; no real donation occurred.
- No original donation credit was reversed, and no genuine member was notified or emailed by this run.
- All four recurring-worker flags remain off. Worker installation/activation, automatic persisted expiry, 24/48-hour reminder/escalation delivery, admin exception review, exports, and erasure/retention remain outside this checkpoint.

**Evidence**

- [Three proofs selected](../scratch/browser-check/full-donation-evidence/03-three-proofs-selected.png)
- [Pending report](../scratch/browser-check/full-donation-evidence/04-donor-pending-report.png)
- [Protected proof preview](../scratch/browser-check/full-donation-evidence/06-requester-proof-1.png)
- [Pending donor hold](../scratch/browser-check/full-donation-evidence/07-pending-donor-hold.png)
- [Mutually completed report](../scratch/browser-check/full-donation-evidence/08-mutually-completed.png)
- [Donor restriction](../scratch/browser-check/full-donation-evidence/10-donor-restriction.png)
- [Pending database state](../scratch/browser-check/full-donation/state-PENDING.json)
- [Confirmed state and encrypted-file checks](../scratch/browser-check/full-donation/state-CONFIRMED.json)
- [Browser results](../scratch/browser-check/full-donation-results.json)
- [Cleanup and preservation](../scratch/browser-check/full-donation/finish.json)

Back up the external keys together with the protected document files when moving this setup to another PC; a database SQL dump does not include either. Private configuration backups, fixture credentials, and raw database backups must remain unpublished.
