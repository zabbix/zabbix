ALTER TABLE auditlog_details MODIFY auditdetailid DEFAULT NULL;
ALTER TABLE auditlog_details MODIFY auditid DEFAULT NULL;
DELETE FROM auditlog_details WHERE NOT auditid IN (SELECT auditid FROM auditlog);
ALTER TABLE auditlog_details ADD CONSTRAINT c_auditlog_details_1 FOREIGN KEY (auditid) REFERENCES auditlog (auditid) ON DELETE CASCADE;
