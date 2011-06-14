CREATE TABLE globalvars (
	globalvarid              bigint                                    NOT NULL,
	snmp_lastsize            integer         WITH DEFAULT '0'          NOT NULL,
	PRIMARY KEY (globalvarid)
);

INSERT INTO globalvars VALUES (1,0);
