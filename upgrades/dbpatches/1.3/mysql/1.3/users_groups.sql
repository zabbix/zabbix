CREATE TABLE users_groups (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	usrgrpid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX users_groups_1 on users_groups (usrgrpid,userid);
