CREATE TABLE history (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		double(16,4)		DEFAULT '0.0000'	NOT NULL
);
CREATE INDEX history_1 on history (itemid,clock);
