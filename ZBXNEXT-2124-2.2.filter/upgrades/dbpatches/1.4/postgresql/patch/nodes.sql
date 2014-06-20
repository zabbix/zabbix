CREATE TABLE nodes (
	nodeid		integer		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT '0'	NOT NULL,
	timezone	integer		DEFAULT '0'	NOT NULL,
	ip		varchar(15)		DEFAULT ''	NOT NULL,
	port		integer		DEFAULT '10051'	NOT NULL,
	slave_history	integer		DEFAULT '30'	NOT NULL,
	slave_trends	integer		DEFAULT '365'	NOT NULL,
	event_lastid	bigint DEFAULT '0'	NOT NULL,
	history_lastid	bigint DEFAULT '0'	NOT NULL,
	history_str_lastid	bigint DEFAULT '0'	NOT NULL,
	history_uint_lastid	bigint DEFAULT '0'	NOT NULL,
	nodetype		integer		DEFAULT '0'	NOT NULL,
	masterid		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (nodeid)
) with OIDS;
