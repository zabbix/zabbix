CREATE TABLE history_text (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		text		DEFAULT ''	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_text_1 on history_text (itemid,clock);
