ALTER TABLE ONLY auditlog ALTER auditid DROP DEFAULT,
			  ALTER userid DROP DEFAULT;
DELETE FROM auditlog WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=auditlog.userid);
ALTER TABLE ONLY auditlog ADD CONSTRAINT c_auditlog_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
