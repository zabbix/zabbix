ALTER TABLE auditlog_details ALTER COLUMN auditdetailid SET WITH DEFAULT NULL
/
REORG TABLE auditlog_details
/
ALTER TABLE auditlog_details ALTER COLUMN auditid SET WITH DEFAULT NULL
/
REORG TABLE auditlog_details
/
DELETE FROM auditlog_details WHERE NOT auditid IN (SELECT auditid FROM auditlog)
/
ALTER TABLE auditlog_details ADD CONSTRAINT c_auditlog_details_1 FOREIGN KEY (auditid) REFERENCES auditlog (auditid) ON DELETE CASCADE
/
