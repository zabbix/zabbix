CREATE TABLE groups (
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (groupid)
);
CREATE INDEX groups_1 on groups (name);
