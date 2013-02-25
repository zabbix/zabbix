ALTER TABLE auditlog MODIFY auditid bigint unsigned NOT NULL,
		     MODIFY userid bigint unsigned NOT NULL;
DELETE FROM auditlog WHERE NOT userid IN (SELECT userid FROM users);
ALTER TABLE auditlog ADD CONSTRAINT c_auditlog_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
