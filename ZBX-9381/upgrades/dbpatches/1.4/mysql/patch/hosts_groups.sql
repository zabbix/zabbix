CREATE TABLE hosts_groups_tmp (
	hostgroupid		bigint unsigned		NOT NULL auto_increment,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostgroupid)
) ENGINE=InnoDB;
CREATE INDEX hosts_groups_groups_1 on hosts_groups_tmp (hostid,groupid);

insert into hosts_groups_tmp select NULL,hostid,groupid from hosts_groups;
drop table hosts_groups;
alter table hosts_groups_tmp rename hosts_groups;


CREATE TABLE hosts_groups_tmp (
	hostgroupid		bigint unsigned	DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostgroupid)
) ENGINE=InnoDB;
CREATE INDEX hosts_groups_groups_1 on hosts_groups_tmp (hostid,groupid);

insert into hosts_groups_tmp select * from hosts_groups;
drop table hosts_groups;
alter table hosts_groups_tmp rename hosts_groups;
