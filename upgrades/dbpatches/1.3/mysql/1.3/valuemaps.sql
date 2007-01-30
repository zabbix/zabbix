CREATE TABLE valuemaps (
	valuemapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (valuemapid)
);
CREATE INDEX valuemaps_1 on valuemaps (name);
