DROP INDEX autoreg_host_1
/
REORG TABLE autoreg_host
/
CREATE INDEX autoreg_host_1 ON autoreg_host (proxy_hostid,host)
/
REORG TABLE autoreg_host
/
ALTER TABLE autoreg_host ALTER COLUMN autoreg_hostid SET WITH DEFAULT NULL
/
REORG TABLE autoreg_host
/
ALTER TABLE autoreg_host ALTER COLUMN proxy_hostid SET WITH DEFAULT NULL
/
REORG TABLE autoreg_host
/
ALTER TABLE autoreg_host ALTER COLUMN proxy_hostid DROP NOT NULL
/
REORG TABLE autoreg_host
/
ALTER TABLE autoreg_host ADD listen_ip varchar(39) WITH DEFAULT '' NOT NULL
/
REORG TABLE autoreg_host
/
ALTER TABLE autoreg_host ADD listen_port integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE autoreg_host
/
ALTER TABLE autoreg_host ADD listen_dns varchar(64) WITH DEFAULT '' NOT NULL
/
REORG TABLE autoreg_host
/
UPDATE autoreg_host SET proxy_hostid=NULL WHERE proxy_hostid=0
/
DELETE FROM autoreg_host WHERE proxy_hostid IS NOT NULL AND proxy_hostid NOT IN (SELECT hostid FROM hosts)
/
ALTER TABLE autoreg_host ADD CONSTRAINT c_autoreg_host_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
