CREATE TABLE globalvars (
	globalvarid              bigint                                    NOT NULL,
	snmp_lastsize            integer         DEFAULT '0'               NOT NULL,
	PRIMARY KEY (globalvarid)
);
