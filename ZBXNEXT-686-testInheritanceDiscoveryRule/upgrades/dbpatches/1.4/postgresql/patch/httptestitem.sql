CREATE TABLE httptestitem (
	httptestitemid	bigint DEFAULT '0'	NOT NULL,
	httptestid	bigint DEFAULT '0'	NOT NULL,
	itemid		bigint DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (httptestitemid)
) with OIDS;
