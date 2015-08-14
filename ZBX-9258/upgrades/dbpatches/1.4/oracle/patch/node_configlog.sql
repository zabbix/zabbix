CREATE TABLE node_configlog (
	conflogid               number(20)              DEFAULT '0'     NOT NULL,
	nodeid          number(20)              DEFAULT '0'     NOT NULL,
	tablename               varchar2(64)            DEFAULT ''      ,
	recordid                number(20)              DEFAULT '0'     NOT NULL,
	operation               number(10)              DEFAULT '0'     NOT NULL,
	sync_master             number(10)              DEFAULT '0'     NOT NULL,
	sync_slave              number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (nodeid,conflogid)
);
CREATE INDEX node_configlog_configlog_1 on node_configlog (conflogid);
CREATE INDEX node_configlog_configlog_2 on node_configlog (nodeid,tablename);
