CREATE TABLE node_cksum (
	cksumid         number(20)              DEFAULT '0'     NOT NULL,
	nodeid          number(20)              DEFAULT '0'     NOT NULL,
	tablename               varchar2(64)            DEFAULT ''      ,
	fieldname               varchar2(64)            DEFAULT ''      ,
	recordid                number(20)              DEFAULT '0'     NOT NULL,
	cksumtype               number(10)              DEFAULT '0'     NOT NULL,
	cksum           varchar2(32)            DEFAULT ''      ,
	PRIMARY KEY (cksumid)
);
CREATE INDEX node_cksum_cksum_1 on node_cksum (nodeid,tablename,fieldname,recordid,cksumtype);
