ALTER TABLE ONLY maintenances_hosts ALTER maintenance_hostid DROP DEFAULT,
				    ALTER maintenanceid DROP DEFAULT,
				    ALTER hostid DROP DEFAULT;
DROP INDEX maintenances_hosts_1;
DELETE FROM maintenances_hosts WHERE NOT EXISTS (SELECT 1 FROM maintenances WHERE maintenances.maintenanceid=maintenances_hosts.maintenanceid);
DELETE FROM maintenances_hosts WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=maintenances_hosts.hostid);
CREATE UNIQUE INDEX maintenances_hosts_1 ON maintenances_hosts (maintenanceid,hostid);
ALTER TABLE ONLY maintenances_hosts ADD CONSTRAINT c_maintenances_hosts_1 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid) ON DELETE CASCADE;
ALTER TABLE ONLY maintenances_hosts ADD CONSTRAINT c_maintenances_hosts_2 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
