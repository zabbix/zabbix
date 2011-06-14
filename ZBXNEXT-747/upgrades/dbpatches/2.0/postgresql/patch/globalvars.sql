CREATE TABLE globalvars (
	globalvarid              bigint                                    NOT NULL,
	snmp_lastsize            integer         DEFAULT '0'               NOT NULL,
	PRIMARY KEY (globalvarid)
) with OIDS;

INSERT INTO globalvars VALUES (1,0);
