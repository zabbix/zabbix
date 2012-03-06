DROP INDEX autoreg_host_1;
CREATE INDEX autoreg_host_1 ON autoreg_host (proxy_hostid,host);
ALTER TABLE ONLY autoreg_host ALTER autoreg_hostid DROP DEFAULT,
			      ALTER proxy_hostid DROP DEFAULT,
			      ALTER proxy_hostid DROP NOT NULL,
			      ADD listen_ip varchar(39) DEFAULT '' NOT NULL,
			      ADD listen_port integer DEFAULT '0' NOT NULL,
			      ADD listen_dns varchar(64) DEFAULT '' NOT NULL;
UPDATE autoreg_host SET proxy_hostid=NULL WHERE proxy_hostid=0;
DELETE FROM autoreg_host WHERE proxy_hostid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=autoreg_host.proxy_hostid);
ALTER TABLE ONLY autoreg_host ADD CONSTRAINT c_autoreg_host_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
