ALTER TABLE ONLY hosts ALTER hostid DROP DEFAULT,
		       ALTER proxy_hostid DROP DEFAULT,
		       ALTER proxy_hostid DROP NOT NULL,
		       ALTER maintenanceid DROP DEFAULT,
		       ALTER maintenanceid DROP NOT NULL;
UPDATE hosts SET proxy_hostid=NULL WHERE proxy_hostid=0;
UPDATE hosts SET maintenanceid=NULL WHERE maintenanceid=0;
ALTER TABLE ONLY hosts ADD CONSTRAINT c_hosts_1 FOREIGN KEY (proxy_hostid) REFERENCES hosts (hostid);
ALTER TABLE ONLY hosts ADD CONSTRAINT c_hosts_2 FOREIGN KEY (maintenanceid) REFERENCES maintenances (maintenanceid);
CREATE INDEX hosts_4 ON hosts (ip);
