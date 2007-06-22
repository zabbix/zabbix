CREATE TABLE hosts_templates_tmp (
	hosttemplateid	bigint DEFAULT '0'	NOT NULL,
	hostid		bigint DEFAULT '0'	NOT NULL,
	templateid	bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hosttemplateid)
) with OIDS;
CREATE UNIQUE INDEX hosts_templates_1 on hosts_templates_tmp (hostid,templateid);

insert into hosts_templates_tmp select NULL,hostid,templateid from  hosts_templates;
drop table  hosts_templates;
alter table hosts_templates_tmp rename  to hosts_templates;

CREATE TABLE hosts_templates_tmp (
	hosttemplateid	bigint DEFAULT '0'	NOT NULL,
	hostid		bigint DEFAULT '0'	NOT NULL,
	templateid	bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hosttemplateid)
) with OIDS;
CREATE UNIQUE INDEX hosts_templates_1 on hosts_templates_tmp (hostid,templateid);

insert into hosts_templates_tmp select * from  hosts_templates;
drop table  hosts_templates;
alter table hosts_templates_tmp rename to hosts_templates;
