ALTER TABLE ONLY hosts_templates ALTER hosttemplateid DROP DEFAULT,
				 ALTER hostid DROP DEFAULT,
				 ALTER templateid DROP DEFAULT;
DELETE FROM hosts_templates WHERE NOT hostid IN (SELECT hostid FROM hosts);
DELETE FROM hosts_templates WHERE NOT templateid IN (SELECT hostid FROM hosts);
ALTER TABLE ONLY hosts_templates ADD CONSTRAINT c_hosts_templates_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY hosts_templates ADD CONSTRAINT c_hosts_templates_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid) ON DELETE CASCADE;
