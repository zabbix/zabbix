CREATE TABLE history_str_sync (
	id		serial			,
	nodeid		bigint DEFAULT '0'	NOT NULL,
	itemid		bigint DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (id)
) with OIDS;
CREATE INDEX history_str_sync_1 on history_str_sync (nodeid,id);
