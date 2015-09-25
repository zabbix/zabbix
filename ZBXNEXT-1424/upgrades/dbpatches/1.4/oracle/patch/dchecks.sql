CREATE TABLE dchecks (
	dcheckid                number(20)              DEFAULT '0'     NOT NULL,
	druleid         number(20)              DEFAULT '0'     NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	key_            varchar2(255)           DEFAULT '0'     ,
	snmp_community          varchar2(255)           DEFAULT '0'     ,
	ports           varchar2(255)           DEFAULT '0'     ,
	PRIMARY KEY (dcheckid)
);
