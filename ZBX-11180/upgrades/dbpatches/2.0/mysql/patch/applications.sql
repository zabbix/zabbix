ALTER TABLE applications MODIFY applicationid bigint unsigned NOT NULL,
			 MODIFY hostid bigint unsigned NOT NULL,
			 MODIFY templateid bigint unsigned NULL;
DELETE FROM applications WHERE NOT hostid IN (SELECT hostid FROM hosts);
UPDATE applications SET templateid=NULL WHERE templateid=0;
CREATE TEMPORARY TABLE tmp_applications_applicationid (applicationid bigint unsigned PRIMARY KEY);
INSERT INTO tmp_applications_applicationid (applicationid) (SELECT applicationid FROM applications);
UPDATE applications SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT applicationid FROM tmp_applications_applicationid);
DROP TABLE tmp_applications_applicationid;
ALTER TABLE applications ADD CONSTRAINT c_applications_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE applications ADD CONSTRAINT c_applications_2 FOREIGN KEY (templateid) REFERENCES applications (applicationid) ON DELETE CASCADE;
