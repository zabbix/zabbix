ALTER TABLE ONLY autoreg_host ALTER autoreg_hostid DROP DEFAULT,
			      ALTER proxy_hostid DROP DEFAULT,
			      ALTER proxy_hostid DROP NOT NULL;
UPDATE autoreg_host SET proxy_hostid=NULL WHERE proxy_hostid=0;
DELETE FROM autoreg_host WHERE NOT proxy_hostid IS NULL AND NOT proxy_hostid IN (SELECT hostid FROM hosts);
ALTER TABLE ONLY autoreg_host ADD CONSTRAINT c_autoreg_host_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
