DROP INDEX maintenances_hosts_1;
ALTER TABLE ONLY maintenances_hosts ALTER maintenanceid DROP DEFAULT;
ALTER TABLE ONLY maintenances_hosts ALTER hostid DROP DEFAULT;
DELETE FROM maintenances_hosts WHERE maintenanceid NOT IN (SELECT maintenanceid FROM maintenances);
DELETE FROM maintenances_hosts WHERE hostid NOT IN (SELECT hostid FROM hosts);
CREATE UNIQUE INDEX maintenances_hosts_1 ON maintenances_hosts (maintenanceid,hostid);
ALTER TABLE ONLY maintenances_hosts ADD CONSTRAINT c_maintenances_hosts_1 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid) ON DELETE CASCADE;
ALTER TABLE ONLY maintenances_hosts ADD CONSTRAINT c_maintenances_hosts_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
