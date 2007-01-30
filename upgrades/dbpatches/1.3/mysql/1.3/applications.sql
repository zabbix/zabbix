CREATE TABLE applications (
	applicationid		bigint unsigned		DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(255)		DEFAULT ''	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (applicationid)
);
CREATE INDEX applications_1 on applications (templateid);
CREATE UNIQUE INDEX applications_2 on applications (hostid,name);
