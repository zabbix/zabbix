CREATE TABLE housekeeper (
	housekeeperid		bigint unsigned		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	field		varchar(64)		DEFAULT ''	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (housekeeperid)
);
