CREATE TABLE rights (
	rightid		bigint unsigned		DEFAULT '0'	NOT NULL,
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	permission		integer		DEFAULT '0'	NOT NULL,
	id		bigint unsigned			,
	PRIMARY KEY (rightid)
);
CREATE INDEX rights_1 on rights (groupid);
