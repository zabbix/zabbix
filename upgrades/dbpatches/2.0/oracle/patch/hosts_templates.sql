DELETE FROM hosts_templates WHERE hostid NOT IN (SELECT hostid FROM hosts);
DELETE FROM hosts_templates WHERE templateid NOT IN (SELECT hostid FROM hosts);

CREATE TABLE t_hosts_templates (
	hosttemplateid           number(20)                                NOT NULL,
	hostid                   number(20)                                NOT NULL,
	templateid               number(20)                                NOT NULL
);

INSERT INTO t_hosts_templates (SELECT hosttemplateid, hostid, templateid FROM hosts_templates);

DROP TABLE hosts_templates;

CREATE TABLE hosts_templates (
	hosttemplateid           number(20)                                NOT NULL,
	hostid                   number(20)                                NOT NULL,
	templateid               number(20)                                NOT NULL,
	PRIMARY KEY (hosttemplateid)
);
CREATE UNIQUE INDEX hosts_templates_1 ON hosts_templates (hostid,templateid);
CREATE INDEX hosts_templates_2 ON hosts_templates (templateid);
ALTER TABLE hosts_templates ADD CONSTRAINT c_hosts_templates_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
ALTER TABLE hosts_templates ADD CONSTRAINT c_hosts_templates_2 FOREIGN KEY (templateid) REFERENCES hosts (hostid) ON DELETE CASCADE;

INSERT INTO hosts_templates (SELECT hosttemplateid, hostid, templateid FROM t_hosts_templates);

DROP TABLE t_hosts_templates;
