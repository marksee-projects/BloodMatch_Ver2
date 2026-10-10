-- Additive structure only; no historical attempts/outbox backfill.
-- Recipient IDs/outcomes only; no address/body/secret.
CREATE TABLE email_donor_reservations (
    attempt_id BIGINT UNSIGNED NOT NULL,
    donor_id BIGINT UNSIGNED NOT NULL,
    reserved_at DATETIME(6) NOT NULL,
    outcome ENUM('outstanding','accepted','uncertain','released') NOT NULL DEFAULT 'outstanding',
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (attempt_id),
    INDEX idx_donor_reservation_window (donor_id, reserved_at, outcome),
    CONSTRAINT fk_donor_reservation_attempt FOREIGN KEY (attempt_id)
        REFERENCES email_attempts (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
