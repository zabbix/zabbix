CREATE TABLE dservices (
	dserviceid              number(20)              DEFAULT '0'     NOT NULL,
	dhostid         number(20)              DEFAULT '0'     NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	key_            varchar2(255)           DEFAULT '0'     ,
	value           varchar2(255)           DEFAULT '0'     ,
	port            number(10)              DEFAULT '0'     NOT NULL,
	status          number(10)              DEFAULT '0'     NOT NULL,
	lastup          number(10)              DEFAULT '0'     NOT NULL,
	lastdown                number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (dserviceid)
);
