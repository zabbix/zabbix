alter table config add work_period varchar(100) DEFAULT '1-5,00:00-24:00' NOT NULL;
alter table graphs add show_work_period int(1) DEFAULT '1' NOT NULL;
alter table graphs add show_triggers int(1) DEFAULT '1' NOT NULL;

alter table profiles change     value value     varchar(255)	DEFAULT '' NOT NULL;
alter table profiles add        valuetype	int(4)		DEFAULT 0 NOT NULL;

--
-- Table structure for table 'applications'
--

CREATE TABLE applications (
        applicationid           int(4)          NOT NULL auto_increment,
        hostid                  int(4)          DEFAULT '0' NOT NULL,
        name                    varchar(255)    DEFAULT '' NOT NULL,
        templateid              int(4)          DEFAULT '0' NOT NULL,
        PRIMARY KEY     (applicationid),
        KEY             hostid (hostid),
        KEY             templateid (templateid),
        UNIQUE          appname (hostid,name)
) type=InnoDB;

--
-- Table structure for table 'items_applications'
--

CREATE TABLE items_applications (
	applicationid		int(4)		DEFAULT '0' NOT NULL,
	itemid			int(4)		DEFAULT '0' NOT NULL,
	PRIMARY KEY (applicationid,itemid)
) type=InnoDB;

alter table audit rename auditlog;
