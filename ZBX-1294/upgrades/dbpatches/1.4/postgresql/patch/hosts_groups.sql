CREATE TABLE hosts_groups_tmp (
	hostgroupid	bigint DEFAULT '0'	NOT NULL,
	hostid		bigint DEFAULT '0'	NOT NULL,
	groupid		bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostgroupid)
) with OIDS;
CREATE INDEX hosts_groups_tmp_1 on hosts_groups_tmp (hostid,groupid);

insert into hosts_groups_tmp select NULL,hostid,groupid from hosts_groups;
drop table hosts_groups;
alter table hosts_groups_tmp rename to hosts_groups;


CREATE TABLE hosts_groups_tmp (
	hostgroupid	bigint DEFAULT '0'	NOT NULL,
	hostid		bigint DEFAULT '0'	NOT NULL,
	groupid		bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostgroupid)
) with OIDS;
CREATE INDEX hosts_groups_1 on hosts_groups_tmp (hostid,groupid);

insert into hosts_groups_tmp select * from hosts_groups;
drop table hosts_groups;
alter table hosts_groups_tmp rename to hosts_groups;
