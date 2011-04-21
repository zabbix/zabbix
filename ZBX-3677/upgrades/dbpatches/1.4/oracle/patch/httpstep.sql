CREATE TABLE httpstep (
	httpstepid              number(20)              DEFAULT '0'     NOT NULL,
	httptestid              number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(64)            DEFAULT ''      ,
	no              number(10)              DEFAULT '0'     NOT NULL,
	url             varchar2(128)           DEFAULT ''      ,
	timeout         number(10)              DEFAULT '30'    NOT NULL,
	posts           varchar2(2048)          DEFAULT ''      ,
	required                varchar2(255)           DEFAULT ''      ,
	status_codes            varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (httpstepid)
);
CREATE INDEX httpstep_httpstep_1 on httpstep (httptestid);
