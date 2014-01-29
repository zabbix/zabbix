CREATE TABLE httpstepitem (
	httpstepitemid	bigint DEFAULT '0'	NOT NULL,
	httpstepid	bigint DEFAULT '0'	NOT NULL,
	itemid		bigint DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (httpstepitemid)
) with OIDS;
CREATE UNIQUE INDEX httpstepitem_httpstepitem_1 on httpstepitem (httpstepid,itemid);
