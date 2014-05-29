CREATE TABLE httptest (
	httptestid	bigint DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	applicationid	bigint DEFAULT '0'	NOT NULL,
	lastcheck	integer		DEFAULT '0'	NOT NULL,
	nextcheck	integer		DEFAULT '0'	NOT NULL,
	curstate	integer		DEFAULT '0'	NOT NULL,
	curstep		integer		DEFAULT '0'	NOT NULL,
	lastfailedstep	integer		 DEFAULT '0'	NOT NULL,
	delay		integer		DEFAULT '60'	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	macros		text		DEFAULT ''	NOT NULL,
	agent		varchar(255)		DEFAULT ''	NOT NULL,
	time		numeric(16,4)		DEFAULT '0'	NOT NULL,
	error		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (httptestid)
) with OIDS;
CREATE INDEX httptest_httptest_1 on httptest (httptestid);
