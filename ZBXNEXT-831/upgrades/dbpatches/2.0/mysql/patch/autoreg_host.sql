DROP INDEX autoreg_host_1 ON autoreg_host;
CREATE INDEX autoreg_host_1 ON autoreg_host (proxy_hostid,host);
ALTER TABLE autoreg_host MODIFY autoreg_hostid bigint unsigned NOT NULL,
			 MODIFY proxy_hostid bigint unsigned NULL,
			 ADD listen_ip varchar(39) DEFAULT '' NOT NULL,
			 ADD listen_port integer DEFAULT '0' NOT NULL,
			 ADD listen_dns varchar(64) DEFAULT '' NOT NULL;
UPDATE autoreg_host SET proxy_hostid=NULL WHERE proxy_hostid=0;
DELETE FROM autoreg_host WHERE proxy_hostid IS NOT NULL AND proxy_hostid NOT IN (SELECT hostid FROM hosts);
ALTER TABLE autoreg_host ADD CONSTRAINT c_autoreg_host_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
