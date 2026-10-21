# Email relationship in the academic database copy

Applied on 2026-10-21 to **`bloodmatcherd (13)` on 127.0.0.1:3306 only**.
The application database and configuration were not changed. The initial email
relationship change dropped no tables; the subsequent presentation cleanup below
removed the five remaining isolated tables from this copy only.

The existing relationship `email_donor_reservations.attempt_id → email_attempts.id`
is now joined to the core schema by an enforced foreign key:

```sql
ALTER TABLE `bloodmatcherd (13)`.`email_donor_reservations`
  ADD CONSTRAINT fk_erd_email_reservation_donor
  FOREIGN KEY (donor_id) REFERENCES `bloodmatcherd (13)`.`users` (id)
  ON DELETE RESTRICT ON UPDATE RESTRICT;
```

This connects both accounting tables without inventing a recipient for account-mail
attempts. `email_outbox` already connects to users through notifications.
The relationship means one user may have many donor reservations, each reservation
belongs to one user, and each attempt has zero or one donor reservation.

Verification confirmed matching unsigned BIGINT types, two existing reservations
with no missing donors, rejection of an invalid donor (MariaDB error 1452), and a
successful valid insertion rolled back afterward. Row counts and SHA-256 digests
of every table's data matched before and after across all 34 tables.

A two-connection test also confirmed that a reservation insert waits when another
connection holds the donor row with `FOR UPDATE` (bounded test returned error 1205).
This is a presentation-copy change, not a reviewed application migration. Do not
copy this FK into the running application without reviewing mail transaction
boundaries, concurrent account changes, and deletion/retention behavior. RESTRICT
keeps accounting records from being cascaded away when a user is deleted.

All 34 expanded tables received grouped positions on Designer page **2**, named
`bloodmatcherd (13)`. Saved coordinates were read back and verified. Visual browser
verification was unavailable because this session exposed no browser or app surface.
At that initial checkpoint, other disconnected operational/reference tables were
unchanged; the subsequent cleanup below supersedes that inventory.

## Subsequent floating-table cleanup

Following the user's report that floating tables remained, the original request
to remove isolated tables was completed in the academic copy. The following five
tables had no incoming or outgoing foreign keys and no references in this copy's
triggers, routines, views or events:

- `auth_throttle`
- `compatibility_pairs`
- `pending_registrations`
- `schema_migrations`
- `system_settings`

They were dropped from **`bloodmatcherd (13)` only**, with FK checks enabled. The
copy now has **29 tables, all in one foreign-key-connected component containing
`users`**. All email tables remain. Every surviving table's data digest, row count
and CREATE TABLE definition matched before and after. Designer entries for the
five removed tables were deleted only for this database; page 2 now has 29 saved
positions. Refresh Designer to reload the inventory.

This reduced schema is for academic presentation. It cannot replace the complete
application database: registration, compatibility rules, throttling, settings and
migration tracking require the removed tables.

A fresh full backup immediately before this cleanup is stored privately at
`backend/storage/db-review/bloodmatcherd-13-before-floating-removal-20261021.sql`,
SHA-256 `c55888fbb600c46332ea02b3675dea8ca1abb499eb5d6292c7a6868d1d156311`.
It includes the email-user FK and the five subsequently removed tables.
`floating-removal-result.json` records the verification results and removed-table
counts/digests/definitions; `designer-before-floating-removal.json` preserves the
previous coordinates in the same private folder.

Private recovery artifacts are in the git-ignored `backend/storage/db-review/`:

- `bloodmatcherd-13-before-email-user-fk-20261021.sql`: full pre-change database dump,
  including triggers, routines and events; SHA-256
  `da23eb6458de6b4c0bad4dac3aadd0fdedd0f7140913bdd31ecd7f6234cdd284`.
- `bloodmatcherd-13-designer-before.json`: prior copy-specific Designer metadata.
- `bloodmatcherd-13-email-user-result.json`: verification results and data digests.

To undo only the added relationship while retaining all data:

```sql
ALTER TABLE `bloodmatcherd (13)`.`email_donor_reservations`
  DROP FOREIGN KEY fk_erd_email_reservation_donor;
```

The one-time implementation is [connect_erd_copy_email.php](../database/connect_erd_copy_email.php).
