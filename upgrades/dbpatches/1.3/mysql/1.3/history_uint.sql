CREATE TABLE history_uint (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		bigint unsigned		DEFAULT '0'	NOT NULL
);
CREATE INDEX history_uint_1 on history_uint (itemid,clock);
