CREATE TABLE services_times (
	timeid		number(20)	DEFAULT '0'	NOT NULL,
	serviceid	number(20)	DEFAULT '0'	NOT NULL,
	type		number(10)	DEFAULT '0'	NOT NULL,
	ts_from		number(10)	DEFAULT '0'	NOT NULL,
	ts_to		number(10)	DEFAULT '0'	NOT NULL,
	note		varchar2(255)	DEFAULT '',
	PRIMARY KEY (timeid)
);
CREATE INDEX services_times_times_1 on services_times (serviceid,type,ts_from,ts_to);
