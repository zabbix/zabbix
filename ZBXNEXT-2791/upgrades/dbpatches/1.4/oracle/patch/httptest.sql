CREATE TABLE httptest (
	httptestid              number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(64)            DEFAULT ''      ,
	applicationid           number(20)              DEFAULT '0'     NOT NULL,
	lastcheck               number(10)              DEFAULT '0'     NOT NULL,
	nextcheck               number(10)              DEFAULT '0'     NOT NULL,
	curstate                number(10)              DEFAULT '0'     NOT NULL,
	curstep         number(10)              DEFAULT '0'     NOT NULL,
	lastfailedstep          number(10)              DEFAULT '0'     NOT NULL,
	delay           number(10)              DEFAULT '60'    NOT NULL,
	status          number(10)              DEFAULT '0'     NOT NULL,
	macros          varchar2(2048)          DEFAULT ''      ,
	agent           varchar2(255)           DEFAULT ''      ,
	time            number(20,4)            DEFAULT '0'     NOT NULL,
	error           varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (httptestid)
);
