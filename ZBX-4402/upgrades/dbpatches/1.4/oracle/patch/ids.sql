CREATE TABLE ids (
	nodeid          number(10)              DEFAULT '0'     NOT NULL,
	table_name              varchar2(64)            DEFAULT ''      ,
	field_name              varchar2(64)            DEFAULT ''      ,
	nextid          number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (nodeid,table_name,field_name)
);
