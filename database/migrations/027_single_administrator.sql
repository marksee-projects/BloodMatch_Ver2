-- Apply after reviewed account cleanup leaves exactly the designated administrator.
CREATE TRIGGER users_single_admin_insert BEFORE INSERT ON users FOR EACH ROW
BEGIN
 IF NEW.role='admin' AND LOWER(NEW.email)<>'markseejr@gmail.com' THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Only the designated administrator may hold the admin role';
 END IF;
END;
-- next statement
CREATE TRIGGER users_single_admin_update BEFORE UPDATE ON users FOR EACH ROW
BEGIN
 IF (NEW.role='admin' AND LOWER(NEW.email)<>'markseejr@gmail.com') OR
 (LOWER(OLD.email)='markseejr@gmail.com' AND (NEW.role<>'admin' OR NEW.account_status<>'active' OR LOWER(NEW.email)<>'markseejr@gmail.com')) THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Administrator responsibility is locked; use a reviewed transfer';
 END IF;
END;
-- next statement
CREATE TRIGGER users_single_admin_delete BEFORE DELETE ON users FOR EACH ROW
BEGIN
 IF LOWER(OLD.email)='markseejr@gmail.com' THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cannot delete the designated administrator';
 END IF;
END;
