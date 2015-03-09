CREATE TABLE dhosts (
	dhostid         number(20)              DEFAULT '0'     NOT NULL,
	druleid         number(20)              DEFAULT '0'     NOT NULL,
	ip              varchar2(15)            DEFAULT ''      ,
	status          number(10)              DEFAULT '0'     NOT NULL,
	lastup          number(10)              DEFAULT '0'     NOT NULL,
	lastdown                number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (dhostid)
);
