CREATE TABLE history_str (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL
);
CREATE INDEX history_str_1 on history_str (itemid,clock);
