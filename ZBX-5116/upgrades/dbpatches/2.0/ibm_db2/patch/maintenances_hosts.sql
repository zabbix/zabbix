ALTER TABLE maintenances_hosts ALTER COLUMN maintenance_hostid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_hosts
/
ALTER TABLE maintenances_hosts ALTER COLUMN maintenanceid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_hosts
/
ALTER TABLE maintenances_hosts ALTER COLUMN hostid SET WITH DEFAULT NULL
/
REORG TABLE maintenances_hosts
/
DROP INDEX maintenances_hosts_1
/
DELETE FROM maintenances_hosts WHERE maintenanceid NOT IN (SELECT maintenanceid FROM maintenances)
/
DELETE FROM maintenances_hosts WHERE hostid NOT IN (SELECT hostid FROM hosts)
/
CREATE UNIQUE INDEX maintenances_hosts_1 ON maintenances_hosts (maintenanceid,hostid)
/
ALTER TABLE maintenances_hosts ADD CONSTRAINT c_maintenances_hosts_1 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid) ON DELETE CASCADE
/
ALTER TABLE maintenances_hosts ADD CONSTRAINT c_maintenances_hosts_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE
/
