DROP TABLE node_cksum;
CREATE TABLE node_cksum (
	nodeid		integer		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	recordid		bigint unsigned		DEFAULT '0'	NOT NULL,
	cksumtype		integer		DEFAULT '0'	NOT NULL,
	cksum		text			NOT NULL,
	sync		char(128)		DEFAULT ''	NOT NULL
) ENGINE=InnoDB;
CREATE INDEX node_cksum_1 on node_cksum (nodeid,cksumtype,tablename,recordid);
