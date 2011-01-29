drop table node_cksum;

CREATE TABLE node_cksum (
	nodeid		number(10)	DEFAULT '0'	NOT NULL,
	tablename	varchar2(64)	DEFAULT '',
	recordid	number(20)	DEFAULT '0'	NOT NULL,
	cksumtype	number(10)	DEFAULT '0'	NOT NULL,
	cksum		clob		DEFAULT ''	NOT NULL,
	sync		varchar2(128)	DEFAULT ''	
);
CREATE INDEX node_cksum_cksum_1 on node_cksum (nodeid,tablename,recordid,cksumtype);
