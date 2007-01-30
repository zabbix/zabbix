CREATE TABLE triggers (
	triggerid		bigint unsigned		DEFAULT '0'	NOT NULL,
	expression		varchar(255)		DEFAULT ''	NOT NULL,
	description		varchar(255)		DEFAULT ''	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	priority		integer		DEFAULT '0'	NOT NULL,
	lastchange		integer		DEFAULT '0'	NOT NULL,
	dep_level		integer		DEFAULT '0'	NOT NULL,
	comments		blob			,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (triggerid)
);
CREATE INDEX triggers_1 on triggers (status);
CREATE INDEX triggers_2 on triggers (value);
