-- Additive only. No historical acceptance, proof or donation backfill.
ALTER TABLE matches ADD COLUMN offer_cycle INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE donation_credits ADD UNIQUE KEY uq_credit_donor_occurrence(donor_id,occurred_at);
CREATE TABLE donation_chat_contexts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 match_id BIGINT UNSIGNED NOT NULL,
 offer_cycle INT UNSIGNED NOT NULL,
 conversation_id BIGINT UNSIGNED NULL,
 accepted_by BIGINT UNSIGNED NULL,
 accepted_at DATETIME NOT NULL,
 responded_at DATETIME NOT NULL,
 UNIQUE KEY uq_offer_chat_cycle(match_id,offer_cycle),
 INDEX idx_context_conversation(conversation_id),
 FOREIGN KEY(match_id) REFERENCES matches(id) ON DELETE CASCADE,
 FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
 FOREIGN KEY(accepted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE donation_reports ADD COLUMN offer_context_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_report_offer_context FOREIGN KEY(offer_context_id) REFERENCES donation_chat_contexts(id) ON DELETE SET NULL;
CREATE TABLE donation_coordination_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 context_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 actor_reference BIGINT UNSIGNED NOT NULL,
 action VARCHAR(32) NOT NULL,
 report_version INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 INDEX idx_coordination_context(context_id,id),
 FOREIGN KEY(context_id) REFERENCES donation_chat_contexts(id) ON DELETE CASCADE,
 FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE donation_report_evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_id BIGINT UNSIGNED NOT NULL,
 content_version INT UNSIGNED NOT NULL,
 uploader_id BIGINT UNSIGNED NULL,
 retired_version INT UNSIGNED NULL,
 stored_name CHAR(64) NOT NULL,
 mime_type VARCHAR(40) NOT NULL,
 original_ext VARCHAR(8) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 uploaded_at DATETIME NOT NULL,
 UNIQUE KEY uq_donation_proof_file(stored_name),
 INDEX idx_donation_proof_version(report_id,content_version),
 FOREIGN KEY(report_id) REFERENCES donation_reports(id) ON DELETE RESTRICT,
 FOREIGN KEY(uploader_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE donation_correction_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 report_id BIGINT UNSIGNED NOT NULL,
 content_version INT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 actor_reference BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL,
 resolved_at DATETIME NULL,
 resolved_by BIGINT UNSIGNED NULL,
 resolution VARCHAR(500) NULL,
 UNIQUE KEY uq_donation_correction(report_id,content_version,actor_reference),
 FOREIGN KEY(report_id) REFERENCES donation_reports(id) ON DELETE RESTRICT,
 FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
