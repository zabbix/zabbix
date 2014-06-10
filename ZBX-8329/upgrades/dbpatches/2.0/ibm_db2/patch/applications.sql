ALTER TABLE applications ALTER COLUMN applicationid SET WITH DEFAULT NULL
/
REORG TABLE applications
/
ALTER TABLE applications ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE applications
/
ALTER TABLE applications ALTER COLUMN templateid SET WITH DEFAULT NULL
/
REORG TABLE applications
/
ALTER TABLE applications ALTER COLUMN templateid DROP NOT NULL
/
REORG TABLE applications
/
DELETE FROM applications WHERE NOT hostid IN (SELECT hostid FROM hosts)
/
UPDATE applications SET templateid=NULL WHERE templateid=0
/
UPDATE applications SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT applicationid FROM applications)
/
ALTER TABLE applications ADD CONSTRAINT c_applications_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
ALTER TABLE applications ADD CONSTRAINT c_applications_2 FOREIGN KEY (templateid) REFERENCES applications (applicationid) ON DELETE CASCADE
/
