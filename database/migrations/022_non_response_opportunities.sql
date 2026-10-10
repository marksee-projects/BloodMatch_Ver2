-- Prepared only: no backfill, no changes to donation windows or existing users.
CREATE TABLE donor_response_state (
    user_id BIGINT UNSIGNED NOT NULL,
    standby_started_at DATETIME(6) NULL,
    consumed_through DATETIME(6) NULL,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_response_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE donor_opportunities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    donor_id BIGINT UNSIGNED NOT NULL,
    generation INT UNSIGNED NOT NULL,
    notification_id BIGINT UNSIGNED NULL,
    notified_at DATETIME(6) NOT NULL,
    due_at DATETIME(6) NOT NULL,
    status ENUM('pending','answered','invalidated','acknowledged','superseded') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (id),
    UNIQUE KEY uq_opportunity_generation (request_id,donor_id,generation),
    INDEX idx_opportunity_due (status,due_at,id),
    INDEX idx_opportunity_donor (donor_id,status,due_at),
    CONSTRAINT fk_opportunity_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_opportunity_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_opportunity_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
