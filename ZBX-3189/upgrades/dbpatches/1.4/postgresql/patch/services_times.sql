CREATE TABLE services_times (
	timeid		bigint DEFAULT '0'	NOT NULL,
	serviceid	bigint DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	ts_from		integer		DEFAULT '0'	NOT NULL,
	ts_to		integer		DEFAULT '0'	NOT NULL,
	note		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (timeid)
) with OIDS;
CREATE INDEX services_times_times_1 on services_times (serviceid,type,ts_from,ts_to);
