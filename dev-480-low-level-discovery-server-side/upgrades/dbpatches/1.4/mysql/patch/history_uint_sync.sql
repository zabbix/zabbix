CREATE TABLE history_uint_sync (
	id		serial			,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE INDEX history_uint_sync_1 on history_uint_sync (nodeid,id);
