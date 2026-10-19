-- Additive workflow only. Apply manually after the approval/test checkpoint.
-- No historical donation occurrence dates or mutual acknowledgments are invented.
ALTER TABLE matches ADD COLUMN responded_at DATETIME NULL;
-- Recover only actual recorded response events, never creation/update timestamps.
UPDATE matches m SET responded_at=(SELECT MAX(a.created_at) FROM audit_log a
 WHERE a.action='match.responded' AND a.actor_id=m.donor_id AND a.target_type='blood_request'
 AND a.target_id=CAST(m.request_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
 AND CONVERT(JSON_UNQUOTE(JSON_EXTRACT(a.context,'$.match_id')) USING utf8mb4) COLLATE utf8mb4_unicode_ci=CAST(m.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci);
ALTER TABLE donation_reports
 ADD COLUMN occurred_at DATETIME NULL,
 ADD COLUMN content_version INT UNSIGNED NOT NULL DEFAULT 1,
 ADD COLUMN workflow_state VARCHAR(32) NOT NULL DEFAULT 'needs_review',
 ADD COLUMN requester_response VARCHAR(24) NULL,
 ADD COLUMN acknowledged_by BIGINT UNSIGNED NULL,
 ADD COLUMN acknowledged_at DATETIME NULL,
 ADD COLUMN acknowledged_version INT UNSIGNED NULL,
 ADD COLUMN reviewed_at DATETIME NULL,
 ADD COLUMN completion_basis VARCHAR(24) NULL,
 ADD COLUMN reminder_sent_at DATETIME NULL,
 ADD COLUMN escalated_at DATETIME NULL,
 ADD INDEX idx_report_followup (status,workflow_state,reported_at),
 ADD CONSTRAINT fk_report_acknowledger FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL;
UPDATE donation_reports SET workflow_state=CASE status WHEN 'CONFIRMED' THEN 'completed' WHEN 'REJECTED' THEN 'rejected' ELSE 'needs_review' END,
 completion_basis=CASE WHEN status='CONFIRMED' THEN 'legacy_admin' ELSE NULL END,
 reviewed_at=confirmed_at;

CREATE TABLE donation_report_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 report_id BIGINT UNSIGNED NOT NULL,
 content_version INT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 actor_reference BIGINT UNSIGNED NULL,
 event_type VARCHAR(48) NOT NULL,
 basis VARCHAR(500) NULL,
 snapshot_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_report_events (report_id,id),
 CONSTRAINT fk_report_event_report FOREIGN KEY (report_id) REFERENCES donation_reports(id) ON DELETE CASCADE,
 CONSTRAINT fk_report_event_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
 CHECK (JSON_VALID(snapshot_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE donation_report_holds (
 report_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 donor_id BIGINT UNSIGNED NOT NULL,
 started_at DATETIME NOT NULL,
 released_at DATETIME NULL,
 released_by BIGINT UNSIGNED NULL,
 release_basis VARCHAR(500) NULL,
 release_type VARCHAR(32) NULL,
 INDEX idx_report_hold_donor (donor_id,released_at),
 CONSTRAINT fk_report_hold_report FOREIGN KEY (report_id) REFERENCES donation_reports(id) ON DELETE CASCADE,
 CONSTRAINT fk_report_hold_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_report_hold_releaser FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Existing unresolved claims receive a distinct hold, not a donation credit.
INSERT INTO donation_report_holds(report_id,donor_id,started_at)
SELECT id,donor_id,reported_at FROM donation_reports WHERE status='PENDING';

CREATE TABLE donation_credits (
 report_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 match_id BIGINT UNSIGNED NOT NULL,
 donor_id BIGINT UNSIGNED NOT NULL,
 occurred_at DATETIME NOT NULL,
 recorded_at DATETIME NOT NULL,
 completion_basis VARCHAR(24) NOT NULL,
 UNIQUE KEY uq_donation_credit_match (match_id),
 INDEX idx_donation_credit_donor (donor_id,occurred_at),
 CONSTRAINT fk_credit_report FOREIGN KEY (report_id) REFERENCES donation_reports(id) ON DELETE CASCADE,
 CONSTRAINT fk_credit_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
 CONSTRAINT fk_credit_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
 CHECK (completion_basis IN ('mutual','admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
