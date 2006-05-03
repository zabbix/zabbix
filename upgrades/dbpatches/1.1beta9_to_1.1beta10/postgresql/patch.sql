ALTER TABLE config ADD work_period varchar(100) DEFAULT '1-5,00:00-24:00' NOT NULL;
ALTER TABLE graphs ADD show_work_period int2 DEFAULT '1' NOT NULL;
ALTER TABLE graphs ADD show_triggers int2 DEFAULT '1' NOT NULL;

ALTER TABLE profiles ALTER COLUMN	value		TYPE	varchar(255);
ALTER TABLE profiles ADD COLUMN		valuetype	int4            DEFAULT 0 NOT NULL;

--
-- Table structure for table 'applications'
--

CREATE TABLE applications (
        applicationid           serial,
        hostid                  int4		DEFAULT '0' NOT NULL,
        name                    varchar(255)	DEFAULT '' NOT NULL,
        templateid              int4		DEFAULT '0' NOT NULL,
        PRIMARY KEY (applicationid),
        FOREIGN KEY hostid (hostid) REFERENCES hosts
);
CREATE UNIQUE INDEX applications_hostid_key on items (hostid,name);

--
-- Table structure for table 'items_applications'
--

CREATE TABLE items_applications (
        applicationid           int4          DEFAULT '0' NOT NULL,
        itemid                  int4          DEFAULT '0' NOT NULL,
        PRIMARY KEY (applicationid,itemid),
	FOREIGN KEY (applicationid) REFERENCES applications,
	FOREIGN KEY (itemid) REFERENCES items 
);

