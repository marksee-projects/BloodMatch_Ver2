-- BM-SAFETY-PHASE5A-SCHEMA: structure only; no historical notification backfill.
CREATE TABLE email_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    delivery_key VARCHAR(190) NOT NULL,
    priority ENUM('normal','emergency') NOT NULL,
    status ENUM('pending','leased','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME(6) NOT NULL,
    lease_token CHAR(32) NULL,
    lease_expires_at DATETIME(6) NULL,
    expires_at DATETIME(6) NOT NULL,
    last_error_code VARCHAR(60) NULL,
    cancel_reason VARCHAR(60) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    sent_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_outbox_notification (notification_id),
    UNIQUE KEY uq_email_outbox_delivery (delivery_key),
    INDEX idx_email_outbox_due (status, priority, next_attempt_at, id),
    INDEX idx_email_outbox_lease (status, lease_expires_at),
    CONSTRAINT fk_email_outbox_notification FOREIGN KEY (notification_id)
        REFERENCES notifications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Independent committed reservations survive business rollbacks and transport failure.
-- No recipient, body, subject, OTP, reset token or credentials are stored here.
CREATE TABLE email_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mail_class ENUM('account','normal','emergency') NOT NULL,
    attempted_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_email_attempts_window (attempted_at, mail_class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
