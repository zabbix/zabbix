CREATE TABLE drules (
	druleid         number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(255)           DEFAULT ''      ,
	iprange         varchar2(255)           DEFAULT ''      ,
	delay           number(10)              DEFAULT '0'     NOT NULL,
	nextcheck               number(10)              DEFAULT '0'     NOT NULL,
	status          number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (druleid)
);
