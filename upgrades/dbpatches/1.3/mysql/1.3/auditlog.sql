CREATE TABLE auditlog (
	auditid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	action		integer		DEFAULT '0'	NOT NULL,
	resourcetype		integer		DEFAULT '0'	NOT NULL,
	details		varchar(128)		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (auditid)
);
CREATE INDEX auditlog_1 on auditlog (userid,clock);
CREATE INDEX auditlog_2 on auditlog (clock);
