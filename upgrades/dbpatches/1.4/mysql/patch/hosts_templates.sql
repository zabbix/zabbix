CREATE TABLE hosts_templates_tmp (
	hosttemplateid		bigint unsigned		NOT NULL auto_increment,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hosttemplateid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX hosts_templates_1 on hosts_templates_tmp (hostid,templateid);

insert into hosts_templates_tmp select NULL,hostid,templateid from  hosts_templates;
drop table  hosts_templates;
alter table hosts_templates_tmp rename  hosts_templates;

CREATE TABLE hosts_templates_tmp (
	hosttemplateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hosttemplateid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX hosts_templates_1 on hosts_templates_tmp (hostid,templateid);

insert into hosts_templates_tmp select * from  hosts_templates;
drop table  hosts_templates;
alter table hosts_templates_tmp rename  hosts_templates;
