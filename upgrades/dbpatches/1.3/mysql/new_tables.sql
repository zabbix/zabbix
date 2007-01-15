CREATE TABLE httptest (
	httptestid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	applicationid		bigint unsigned		DEFAULT '0'	NOT NULL,
	nextcheck		integer		DEFAULT '0'	NOT NULL,
	delay		integer		DEFAULT '60'	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (httptestid)
);
CREATE INDEX httptest_httptest_1 on httptest (httptestid);

CREATE TABLE httpstep (
	httpstepid		bigint unsigned		DEFAULT '0'	NOT NULL,
	httptestid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	no		integer		DEFAULT '0'	NOT NULL,
	url		varchar(128)		DEFAULT ''	NOT NULL,
	timeout		integer		DEFAULT '30'	NOT NULL,
	posts		blob		DEFAULT ''	NOT NULL,
	required		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (httpstepid)
);
CREATE INDEX httpstep_httpstep_1 on httpstep (httptestid);

CREATE TABLE httpstepitem (
	httpstepitemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	httpstepid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (httpstepitemid)
);
CREATE UNIQUE INDEX httpstepitem_httpstepitem_1 on httpstepitem (httpstepid,itemid);

CREATE TABLE httpmacro (
	httpmacroid		bigint unsigned		DEFAULT '0'	NOT NULL,
	httptestid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT '0'	NOT NULL,
	macro		varchar(64)		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT '0'	NOT NULL,
	note		blob		DEFAULT ''	NOT NULL,
	PRIMARY KEY (httpmacroid)
);
CREATE UNIQUE INDEX httpmacro_httpmacro_1 on httpmacro (httptestid,name);

CREATE TABLE nodes (
	nodeid		integer		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT '0'	NOT NULL,
	timezone		integer		DEFAULT '0'	NOT NULL,
	ip		varchar(15)		DEFAULT ''	NOT NULL,
	port		integer		DEFAULT '10051'	NOT NULL,
	slave_history		integer		DEFAULT '30'	NOT NULL,
	slave_trends		integer		DEFAULT '365'	NOT NULL,
	event_lastid		bigint unsigned		DEFAULT '0'	NOT NULL,
	events_eventid		bigint unsigned		DEFAULT '0'	NOT NULL,
	alerts_alertid		bigint unsigned		DEFAULT '0'	NOT NULL,
	history_lastid		bigint unsigned		DEFAULT '0'	NOT NULL,
	history_str_lastid		bigint unsigned		DEFAULT '0'	NOT NULL,
	history_uint_lastid		bigint unsigned		DEFAULT '0'	NOT NULL,
	nodetype		integer		DEFAULT '0'	NOT NULL,
	masterid		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (nodeid)
);
CREATE TABLE node_cksum (
	cksumid		bigint unsigned		DEFAULT '0'	NOT NULL,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	fieldname		varchar(64)		DEFAULT ''	NOT NULL,
	recordid		bigint unsigned		DEFAULT '0'	NOT NULL,
	cksumtype		integer		DEFAULT '0'	NOT NULL,
	cksum		char(32)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (cksumid)
);
CREATE INDEX node_cksum_cksum_1 on node_cksum (nodeid,tablename,fieldname,recordid,cksumtype);

CREATE TABLE node_configlog (
	conflogid		bigint unsigned		DEFAULT '0'	NOT NULL,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	recordid		bigint unsigned		DEFAULT '0'	NOT NULL,
	operation		integer		DEFAULT '0'	NOT NULL,
	sync_master		integer		DEFAULT '0'	NOT NULL,
	sync_slave		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (nodeid,conflogid)
);
CREATE INDEX node_configlog_configlog_1 on node_configlog (conflogid);
CREATE INDEX node_configlog_configlog_2 on node_configlog (nodeid,tablename);

CREATE TABLE history_str_sync (
	id		serial			,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_str_sync_1 on history_str_sync (nodeid,id);

CREATE TABLE history_sync (
	id		serial			,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		double(16,4)		DEFAULT '0.0000'	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_sync_1 on history_sync (nodeid,id);

CREATE TABLE history_uint_sync (
	id		serial			,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_uint_sync_1 on history_uint_sync (nodeid,id);

CREATE TABLE services_times (
	timeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	serviceid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	ts_from		integer		DEFAULT '0'	NOT NULL,
	ts_to		integer		DEFAULT '0'	NOT NULL,
	note		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (timeid)
);
CREATE INDEX services_times_times_1 on services_times (serviceid,type,ts_from,ts_to);
