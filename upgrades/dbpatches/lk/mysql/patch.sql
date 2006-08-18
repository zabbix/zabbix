alter table graphs add graphtype	int(2) DEFAULT '0' NOT NULL;
alter table items  add delay_flex       varchar(255) DEFAULT "" NOT NULL;

--
-- Table structure for table 'services_times'
--

CREATE TABLE services_times (
	timeid		int(4)		NOT NULL auto_increment,
	serviceid	int(4)          DEFAULT '0' NOT NULL,
	type		int(2)		DEFAULT '0' NOT NULL,
	ts_from		int(4)		DEFAULT '0' NOT NULL,
	ts_to		int(4)		DEFAULT '0' NOT NULL,
	note		varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (timeid),
	UNIQUE (serviceid,type,ts_from,ts_to)
) type=InnoDB;

