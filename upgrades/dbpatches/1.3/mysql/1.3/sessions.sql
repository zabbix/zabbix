CREATE TABLE sessions (
	sessionid		varchar(32)		DEFAULT ''	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	lastaccess		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sessionid)
);
