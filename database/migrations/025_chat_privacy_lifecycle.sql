-- User-run only, after reviewed import repair on bloodmatch_test.
-- Existing conversations receive NO implicit consent. Participants must request/accept.
CREATE TABLE pending_registrations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 payload_json LONGTEXT NOT NULL,
 document_json LONGTEXT NULL,
 code_hash VARCHAR(255) NOT NULL,
 code_expires_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX pending_expiry (expires_at),
 CHECK (JSON_VALID(payload_json)),
 CHECK (document_json IS NULL OR JSON_VALID(document_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE chat_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_low BIGINT UNSIGNED NOT NULL,
 user_high BIGINT UNSIGNED NOT NULL,
 sender_id BIGINT UNSIGNED NOT NULL,
 recipient_id BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
 conversation_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY chat_pair (user_low,user_high),
 INDEX incoming_requests (recipient_id,status),
 INDEX accepted_thread (conversation_id,status),
 FOREIGN KEY (user_low) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (user_high) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
 CHECK (user_low < user_high AND user_low=LEAST(sender_id,recipient_id) AND user_high=GREATEST(sender_id,recipient_id)),
 CHECK ((status='accepted' AND conversation_id IS NOT NULL) OR (status<>'accepted' AND conversation_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE chat_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reporter_id BIGINT UNSIGNED NULL,
 conversation_id BIGINT UNSIGNED NULL,
 reason VARCHAR(500) NOT NULL,
 status ENUM('open','under_review','resolved','dismissed') NOT NULL DEFAULT 'open',
 findings VARCHAR(2000) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 INDEX report_queue (status,created_at),
 FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE chat_report_evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_id BIGINT UNSIGNED NOT NULL,
 message_id BIGINT UNSIGNED NOT NULL,
 sender_id BIGINT UNSIGNED NULL,
 body TEXT NOT NULL,
 message_created_at DATETIME(3) NOT NULL,
 is_selected TINYINT(1) NOT NULL,
 FOREIGN KEY (report_id) REFERENCES chat_reports(id) ON DELETE CASCADE,
 INDEX report_evidence (report_id,message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE account_closures (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 closed_at DATETIME NOT NULL,
 erase_after DATETIME NOT NULL,
 profile_erased_at DATETIME NULL,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX closure_due (erase_after,profile_erased_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE blood_requests ADD COLUMN renewed_from_id BIGINT UNSIGNED NULL,
 ADD COLUMN closure_reason VARCHAR(50) NULL;
ALTER TABLE conversation_participants ADD COLUMN last_read_message_id BIGINT UNSIGNED NULL;
UPDATE conversation_participants cp SET last_read_message_id=(SELECT MAX(m.id) FROM messages m WHERE m.conversation_id=cp.conversation_id AND m.created_at<=cp.last_read_at);


ALTER TABLE donation_reports ADD COLUMN review_evidence VARCHAR(500) NULL;
