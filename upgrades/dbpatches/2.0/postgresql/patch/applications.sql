ALTER TABLE ONLY applications ALTER applicationid DROP DEFAULT,
			      ALTER hostid DROP DEFAULT,
			      ALTER templateid DROP DEFAULT,
			      ALTER templateid DROP NOT NULL;
DELETE FROM applications WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=applications.hostid);
UPDATE applications SET templateid=NULL WHERE templateid=0;
UPDATE applications a1 SET templateid=NULL WHERE a1.templateid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM applications a2 WHERE a2.applicationid=a1.templateid);
ALTER TABLE ONLY applications ADD CONSTRAINT c_applications_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY applications ADD CONSTRAINT c_applications_2 FOREIGN KEY (templateid) REFERENCES applications (applicationid) ON DELETE CASCADE;
