
alter table graphs add graphtype	int2 DEFAULT '0' NOT NULL;
alter table items  add delay_flex       varchar(255) DEFAULT "" NOT NULL;

--
-- Table structure for table 'services_times'
--

CREATE TABLE services_times (
	timeid		serial,
	serviceid	int4		DEFAULT '0' NOT NULL,
	type		int2		DEFAULT '0' NOT NULL,
	ts_from		int4		DEFAULT '0' NOT NULL,
	ts_to		int4		DEFAULT '0' NOT NULL,
	note		varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (timeid)
) type=InnoDB;

CREATE UNIQUE INDEX services_times_uniq on services_times (serviceid,type,ts_from,ts_to);

