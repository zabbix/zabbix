CREATE TABLE conditions (
	conditionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	actionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	conditiontype		integer		DEFAULT '0'	NOT NULL,
	operator		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (conditionid)
);
CREATE INDEX conditions_1 on conditions (actionid);
