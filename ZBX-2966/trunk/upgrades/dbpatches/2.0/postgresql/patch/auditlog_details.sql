ALTER TABLE ONLY auditlog_details ALTER auditdetailid DROP DEFAULT,
				  ALTER auditid DROP DEFAULT;
DELETE FROM auditlog_details WHERE NOT EXISTS (SELECT 1 FROM auditlog WHERE auditlog.auditid=auditlog_details.auditid);
ALTER TABLE ONLY auditlog_details ADD CONSTRAINT c_auditlog_details_1 FOREIGN KEY (auditid) REFERENCES auditlog (auditid) ON DELETE CASCADE;
