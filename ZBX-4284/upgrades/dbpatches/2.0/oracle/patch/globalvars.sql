CREATE TABLE globalvars (
	globalvarid              number(20)                                NOT NULL,
	snmp_lastsize            number(10)      DEFAULT '0'               NOT NULL,
	PRIMARY KEY (globalvarid)
);
