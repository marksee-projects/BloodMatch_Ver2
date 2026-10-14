-- Preserve append-only protection for ordinary application operations.
-- Aged deletion is allowed only for the explicit user-run retention worker.
DROP TRIGGER IF EXISTS audit_log_block_delete;
-- next statement
CREATE TRIGGER audit_log_block_delete BEFORE DELETE ON audit_log FOR EACH ROW
BEGIN
 IF COALESCE(@bloodmatch_retention,0) <> 1 OR OLD.created_at >= DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)
 OR EXISTS(SELECT 1 FROM users u LEFT JOIN account_closures c ON c.user_id=u.id WHERE OLD.action='user.registered' AND OLD.actor_id=u.id AND (u.account_status='active' OR c.closed_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)))
 OR EXISTS(SELECT 1 FROM chat_reports r WHERE OLD.target_type='chat_report' AND r.id=OLD.target_id AND (r.status IN ('open','under_review') OR r.resolved_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)))
 OR EXISTS(SELECT 1 FROM donation_reports d WHERE OLD.target_type='donation_report' AND d.id=OLD.target_id AND (d.status='PENDING' OR COALESCE(d.confirmed_at,d.reported_at)>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)))
 OR EXISTS(SELECT 1 FROM blood_requests br WHERE OLD.target_type='blood_request' AND br.id=OLD.target_id AND (br.status='OPEN' OR br.updated_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR))) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit_log is append-only; aged retention requires reviewed worker and no open investigations';
 END IF;
END;
