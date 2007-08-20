CREATE TABLE httpstep (
	httpstepid	bigint DEFAULT '0'	NOT NULL,
	httptestid	bigint DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	no		integer		DEFAULT '0'	NOT NULL,
	url		varchar(128)		DEFAULT ''	NOT NULL,
	timeout		integer		DEFAULT '30'	NOT NULL,
	posts		text		DEFAULT ''	NOT NULL,
	required	varchar(255)		DEFAULT ''	NOT NULL,
	status_codes	varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (httpstepid)
) with OIDS;
CREATE INDEX httpstep_httpstep_1 on httpstep (httptestid);
