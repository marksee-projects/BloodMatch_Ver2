# Registration National ID uploads

Registration now offers separate front and back National ID fields. Both accept JPG, PNG, WEBP or PDF files, up to 5 MB each. The explanation asks for clear, complete sides and separate files for a digital ID. Uploading remains optional during registration; selecting one side requires the other before proceeding.

The multipart endpoint accepts `national_id_front` and `national_id_back`. Pending registration stores both using existing protected document storage, creates two National ID records after successful OTP verification, and emits one verification-request notification. Failure and expiry cleanup handle the pair. Existing single-file clients and pending registrations remain supported.

## Profile and administrator review follow-up

Migration 037 adds nullable `document_side` metadata and was applied only to local `bloodmatch:3306`. All eight existing document rows were snapshotted and verified unchanged apart from the new null field. The pre-migration snapshot is in ignored `scratch/browser-check/documents-before-037.json`. Existing files with unknown sides are explicitly labelled as such rather than guessed.

Registration and profile uploads persist front/back labels. Profile upload accepts both files in one request and saves their document rows atomically, with new-file cleanup on failure. Existing active-account, verified-ID-lock, privacy, throttle, storage validation, review notification, and protected document access checks remain.

The profile Verification tab lists each ID file and opens a protected in-page preview. Other profile tabs do not display the documents. Administrator review labels the sides and uses the same preview; PDF previews no longer offer a separate-tab link. Owner previews use the owner endpoint and admin previews retain the administrator endpoint.

Additional verification passed: three Chrome scenarios for profile labels/tab isolation/same-tab previews, admin front/back/legacy labels/inspection, and paired profile submission. API responses were simulated; no live member upload or decision was submitted. Offline preview checks cover the owner URL and side labels; a database transaction checked side metadata insert/list and rolled back all test rows. The final production build and PHP syntax checks passed. No emails were sent by these checks.

Validation completed:

- Chrome: two PDFs, pair validation, review filenames, mixed image/PDF, invalid MIME and oversized-file rejection, removal, optional skip, desktop and 390px mobile layout. No uncaught JavaScript errors or horizontal mobile overflow.
- Synthetic PDF storage/readback and paired/legacy cleanup passed using the local storage configuration; only files created by the check were removed.
- 22 offline registration/notification assertions and 3 email-alias assertions passed; PHP syntax checks passed.
- Production build passed with the existing bundle-size warning.

Browser API responses were simulated. No account was submitted, database records changed, or email sent for these checks. Full paired registration through a real OTP and administrator decision was not repeated.
