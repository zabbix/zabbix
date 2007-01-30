CREATE TABLE autoreg (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	priority		integer		DEFAULT '0'	NOT NULL,
	pattern		varchar(255)		DEFAULT ''	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
);
