ALTER TABLE hosts_templates MODIFY hosttemplateid bigint unsigned NOT NULL,
			    MODIFY hostid bigint unsigned NOT NULL,
			    MODIFY templateid bigint unsigned NOT NULL;
DELETE FROM hosts_templates WHERE NOT hostid IN (SELECT hostid FROM hosts);
DELETE FROM hosts_templates WHERE NOT templateid IN (SELECT hostid FROM hosts);
ALTER TABLE hosts_templates ADD CONSTRAINT c_hosts_templates_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE hosts_templates ADD CONSTRAINT c_hosts_templates_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid) ON DELETE CASCADE;
