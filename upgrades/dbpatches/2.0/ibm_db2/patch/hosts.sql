ALTER TABLE hosts ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN proxy_hostid SET WITH DEFAULT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN proxy_hostid DROP NOT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN maintenanceid SET WITH DEFAULT NULL
/
REORG TABLE hosts
/
ALTER TABLE hosts ALTER COLUMN maintenanceid DROP NOT NULL
/
REORG TABLE hosts
/
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0
/
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0
/
ALTER TABLE hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid)
/
REORG TABLE hosts
/
ALTER TABLE hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid)
/
REORG TABLE hosts
/
CREATE INDEX hosts_4 ON hosts (ip)
/
