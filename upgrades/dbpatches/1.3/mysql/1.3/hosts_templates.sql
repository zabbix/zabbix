CREATE TABLE hosts_templates (
	hosttemplateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hosttemplateid)
);
CREATE UNIQUE INDEX hosts_templates_1 on hosts_templates (hostid,templateid);
