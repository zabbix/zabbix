CREATE TABLE service_alarms (
	servicealarmid		bigint unsigned		DEFAULT '0'	NOT NULL,
	serviceid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (servicealarmid)
);
CREATE INDEX service_alarms_1 on service_alarms (serviceid,clock);
CREATE INDEX service_alarms_2 on service_alarms (clock);
