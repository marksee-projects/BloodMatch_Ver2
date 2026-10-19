-- Accepted context identity is historical and has no application reassignment writer.
-- An immutable parent identity closes the insert/reassignment race in cross-row validation.
DROP TRIGGER context_report_identity_update;
-- next statement
CREATE TRIGGER context_report_identity_update BEFORE UPDATE ON donation_chat_contexts FOR EACH ROW
BEGIN
 IF NEW.match_id<>OLD.match_id THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Accepted donation context match identity is immutable';
 END IF;
END;
