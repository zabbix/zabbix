CREATE TABLE history_uint_sync (
	id		serial			,
	nodeid		bigint DEFAULT '0'	NOT NULL,
	itemid		bigint DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
) with OIDS;
CREATE INDEX history_uint_sync_1 on history_uint_sync (nodeid,id);
