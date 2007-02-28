CREATE TABLE node_cksum (
	cksumid		bigint unsigned		DEFAULT '0'	NOT NULL,
	nodeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	fieldname		varchar(64)		DEFAULT ''	NOT NULL,
	recordid		bigint unsigned		DEFAULT '0'	NOT NULL,
	cksumtype		integer		DEFAULT '0'	NOT NULL,
	cksum		char(32)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (cksumid)
) ENGINE=InnoDB;
CREATE INDEX node_cksum_cksum_1 on node_cksum (nodeid,tablename,fieldname,recordid,cksumtype);
