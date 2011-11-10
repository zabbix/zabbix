ALTER TABLE auditlog_details MODIFY auditdetailid DEFAULT NULL;
ALTER TABLE auditlog_details MODIFY auditid DEFAULT NULL;
ALTER TABLE auditlog_details MODIFY oldvalue nvarchar2(2048) DEFAULT '';
ALTER TABLE auditlog_details MODIFY newvalue nvarchar2(2048) DEFAULT '';
DELETE FROM auditlog_details WHERE NOT auditid IN (SELECT auditid FROM auditlog);
ALTER TABLE auditlog_details ADD CONSTRAINT c_auditlog_details_1 FOREIGN KEY (auditid) REFERENCES auditlog (auditid) ON DELETE CASCADE;
