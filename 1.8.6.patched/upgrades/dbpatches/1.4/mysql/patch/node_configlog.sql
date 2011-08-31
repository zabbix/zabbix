CREATE TABLE node_configlog (
	conflogid		bigint unsigned		DEFAULT '0'	NOT NULL,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	recordid		bigint unsigned		DEFAULT '0'	NOT NULL,
	operation		integer		DEFAULT '0'	NOT NULL,
	sync_master		integer		DEFAULT '0'	NOT NULL,
	sync_slave		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (nodeid,conflogid)
) ENGINE=InnoDB;
CREATE INDEX node_configlog_configlog_1 on node_configlog (conflogid);
CREATE INDEX node_configlog_configlog_2 on node_configlog (nodeid,tablename);
