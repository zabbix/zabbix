DROP TABLE node_cksum;
CREATE TABLE node_cksum (
	nodeid		number(10)		DEFAULT '0'	NOT NULL,
	tablename		nvarchar2(64)		DEFAULT ''	,
	recordid		number(20)		DEFAULT '0'	NOT NULL,
	cksumtype		number(10)		DEFAULT '0'	NOT NULL,
	cksum		nclob		DEFAULT ''	,
	sync		nvarchar2(128)		DEFAULT ''	
);
CREATE INDEX node_cksum_1 on node_cksum (nodeid,cksumtype,tablename,recordid);
