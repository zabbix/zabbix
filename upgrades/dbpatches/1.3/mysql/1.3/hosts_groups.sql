CREATE TABLE hosts_groups (
	hostgroupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostgroupid)
);
CREATE INDEX hosts_groups_groups_1 on hosts_groups (hostid,groupid);
