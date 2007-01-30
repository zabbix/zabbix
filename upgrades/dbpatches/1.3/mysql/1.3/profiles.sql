CREATE TABLE profiles (
	profileid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	idx		varchar(64)		DEFAULT ''	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	valuetype		integer		DEFAULT 0	NOT NULL,
	PRIMARY KEY (profileid)
);
CREATE UNIQUE INDEX profiles_1 on profiles (userid,idx);
