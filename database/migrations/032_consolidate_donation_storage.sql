-- Staged consolidation. Source tables are retained unchanged, never dropped here.
-- Execute ONLY through review_donation_consolidation.php with writers stopped.
ALTER TABLE donation_reports
 ADD COLUMN credit_recorded_at DATETIME NULL,
 ADD COLUMN hold_started_at DATETIME NULL,
 ADD COLUMN hold_released_at DATETIME NULL,
 ADD COLUMN hold_released_by BIGINT UNSIGNED NULL,
 ADD COLUMN hold_release_basis VARCHAR(500) NULL,
 ADD COLUMN hold_release_type VARCHAR(32) NULL,
 ADD CONSTRAINT fk_report_hold_released_by FOREIGN KEY(hold_released_by) REFERENCES users(id) ON DELETE SET NULL;
-- next statement
UPDATE donation_reports d LEFT JOIN donation_credits c ON c.report_id=d.id
 LEFT JOIN donation_report_holds h ON h.report_id=d.id
 SET d.credit_recorded_at=c.recorded_at,d.hold_started_at=h.started_at,
 d.hold_released_at=h.released_at,d.hold_released_by=h.released_by,
 d.hold_release_basis=h.release_basis,d.hold_release_type=h.release_type;
-- next statement
ALTER TABLE matches ADD UNIQUE KEY uq_match_identity(id,donor_id);
-- next statement
ALTER TABLE donation_reports
 ADD UNIQUE KEY uq_report_match(match_id),
 ADD CONSTRAINT fk_report_match_donor FOREIGN KEY(match_id,donor_id) REFERENCES matches(id,donor_id) ON DELETE CASCADE,
 ADD COLUMN credited_donor_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN credit_recorded_at IS NOT NULL THEN donor_id ELSE NULL END) STORED,
 ADD COLUMN credited_occurred_at DATETIME GENERATED ALWAYS AS (CASE WHEN credit_recorded_at IS NOT NULL THEN occurred_at ELSE NULL END) STORED,
 ADD UNIQUE KEY uq_report_credited_occurrence(credited_donor_id,credited_occurred_at),
 ADD INDEX idx_report_active_hold(donor_id,hold_released_at,hold_started_at),
 ADD CONSTRAINT chk_report_credit CHECK(credit_recorded_at IS NULL OR (status='CONFIRMED' AND occurred_at IS NOT NULL AND completion_basis IS NOT NULL AND completion_basis IN ('mutual','admin'))),
 ADD CONSTRAINT chk_report_version CHECK(content_version>=1),
 ADD CONSTRAINT chk_report_workflow CHECK(workflow_state IN ('awaiting_requester','needs_review','clarification','completed','rejected')),
 ADD CONSTRAINT chk_report_response CHECK(requester_response IS NULL OR requester_response IN ('received','did_not_donate','not_sure')),
 ADD CONSTRAINT chk_report_hold CHECK((hold_started_at IS NOT NULL OR (hold_released_at IS NULL AND hold_released_by IS NULL AND hold_release_basis IS NULL AND hold_release_type IS NULL)) AND (hold_released_at IS NULL OR hold_released_at>=hold_started_at));
-- next statement
ALTER TABLE blood_requests ADD UNIQUE KEY uq_request_renewal(renewed_from_id),
 ADD CONSTRAINT fk_request_renewal FOREIGN KEY(renewed_from_id) REFERENCES blood_requests(id) ON DELETE SET NULL,
 ADD CONSTRAINT chk_request_positive_quantity CHECK(quantity_units>0);
-- next statement
ALTER TABLE donation_correction_requests ADD INDEX idx_correction_unresolved(report_id,resolved_at);
-- next statement
ALTER TABLE chat_report_evidence ADD UNIQUE KEY uq_chat_evidence_message(report_id,message_id);
-- next statement
CREATE TABLE compatibility_pairs (
 recipient_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
 donor_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
 preference_order TINYINT UNSIGNED NOT NULL,
 PRIMARY KEY(recipient_type,donor_type),
 UNIQUE KEY uq_compatibility_order(recipient_type,preference_order),
 CONSTRAINT chk_compatibility_order CHECK(preference_order BETWEEN 1 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- next statement
INSERT INTO compatibility_pairs(recipient_type,donor_type,preference_order)
 SELECT m.recipient_type,TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(m.allowed_donor_types,',',n.pos),',',-1)),n.pos
 FROM compatibility_matrix m JOIN
 (SELECT 1 pos UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8) n
 ON n.pos<=1+LENGTH(m.allowed_donor_types)-LENGTH(REPLACE(m.allowed_donor_types,',',''));
-- next statement
-- Preserve existing nullable SET NULL context FK and deletion behavior.
-- Cross-record identity is additionally validated on both sides of the relationship.
CREATE TRIGGER report_context_identity_insert BEFORE INSERT ON donation_reports FOR EACH ROW
BEGIN
 IF NEW.offer_context_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM donation_chat_contexts c WHERE c.id=NEW.offer_context_id AND c.match_id=NEW.match_id) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Donation context must belong to the report match';
 END IF;
END;
-- next statement
CREATE TRIGGER report_context_identity_update BEFORE UPDATE ON donation_reports FOR EACH ROW
BEGIN
 IF NEW.offer_context_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM donation_chat_contexts c WHERE c.id=NEW.offer_context_id AND c.match_id=NEW.match_id) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Donation context must belong to the report match';
 END IF;
END;
-- next statement
CREATE TRIGGER context_report_identity_update BEFORE UPDATE ON donation_chat_contexts FOR EACH ROW
BEGIN
 IF NEW.match_id<>OLD.match_id AND EXISTS(SELECT 1 FROM donation_reports d WHERE d.offer_context_id=OLD.id AND d.match_id<>NEW.match_id) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cannot move a referenced donation context to another match';
 END IF;
END;
