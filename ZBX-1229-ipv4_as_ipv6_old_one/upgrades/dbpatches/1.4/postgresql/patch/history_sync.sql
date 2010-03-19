CREATE TABLE history_sync (
	id		serial			,
	nodeid		bigint DEFAULT '0'	NOT NULL,
	itemid		bigint DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		numeric(16,4)		DEFAULT '0.0000'	NOT NULL,
	PRIMARY KEY (id)
) with OIDS;
CREATE INDEX history_sync_1 on history_sync (nodeid,id);
