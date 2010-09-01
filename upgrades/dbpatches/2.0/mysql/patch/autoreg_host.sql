ALTER TABLE autoreg_host MODIFY autoreg_hostid bigint unsigned NOT NULL,
			 MODIFY proxy_hostid bigint unsigned NULL;
UPDATE autoreg_host SET proxy_hostid=NULL WHERE proxy_hostid=0;
DELETE FROM autoreg_host WHERE NOT proxy_hostid IS NULL AND NOT proxy_hostid IN (SELECT hostid FROM hosts);
ALTER TABLE autoreg_host ADD CONSTRAINT c_autoreg_host_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
