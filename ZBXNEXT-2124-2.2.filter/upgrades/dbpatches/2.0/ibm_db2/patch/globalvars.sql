CREATE TABLE globalvars (
	globalvarid              bigint                                    NOT NULL,
	snmp_lastsize            integer         WITH DEFAULT '0'          NOT NULL,
	PRIMARY KEY (globalvarid)
)
/
