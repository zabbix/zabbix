ALTER TABLE ONLY hosts_templates ALTER hosttemplateid DROP DEFAULT,
				 ALTER hostid DROP DEFAULT,
				 ALTER templateid DROP DEFAULT;
DELETE FROM hosts_templates WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=hosts_templates.hostid);
DELETE FROM hosts_templates WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=hosts_templates.templateid);
ALTER TABLE ONLY hosts_templates ADD CONSTRAINT c_hosts_templates_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE ONLY hosts_templates ADD CONSTRAINT c_hosts_templates_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid) ON DELETE CASCADE;
