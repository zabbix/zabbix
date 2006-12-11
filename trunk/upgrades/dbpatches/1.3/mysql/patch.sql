--
-- Table structure for table 'nodes'
--

CREATE TABLE nodes (
  nodeid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  timezone		int(4)		DEFAULT '0' NOT NULL,
  ip			varchar(15)	DEFAULT '' NOT NULL,
  port			int(4)		DEFAULT '0' NOT NULL,
  slave_history		int(4)		DEFAULT '0' NOT NULL,
  slave_trends		int(4)		DEFAULT '0' NOT NULL,
  nodetype		int(4)		DEFAULT '0' NOT NULL,
  masterid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (nodeid)
) type=InnoDB;

--
-- Table structure for table 'node_cksum'
--

CREATE TABLE node_cksum (
  cksumid		int(4)		NOT NULL auto_increment,
  nodeid		int(4)		DEFAULT '0' NOT NULL,
  tablename		varchar(64)	DEFAULT '' NOT NULL,
  fieldname		varchar(64)	DEFAULT '' NOT NULL,
  recordid		int(4)		DEFAULT '0' NOT NULL,
  cksumtype		int(4)		DEFAULT '0' NOT NULL,
  cksum			char(32)	DEFAULT '' NOT NULL,
  PRIMARY KEY (cksumid),
  KEY (nodeid,tablename,fieldname,recordid,cksumtype)
) type=InnoDB;

--
-- Table structure for table 'node_configlog'
--

CREATE TABLE node_configlog (
  conflogid		int(4)		NOT NULL auto_increment,
  nodeid		int(4)		DEFAULT '0' NOT NULL,
  tablename		varchar(64)	DEFAULT '' NOT NULL,
  recordid		int(4)		DEFAULT '0' NOT NULL,
  operation		int(4)		DEFAULT '0' NOT NULL,
  sync_master		int(4)		DEFAULT '0' NOT NULL,
  sync_slave		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (conflogid),
  KEY (nodeid,tablename)
) type=InnoDB;

--- Add configid to table config
CREATE TABLE config_tmp (
  configid		int(4)		NOT NULL auto_increment,
  alert_history		int(4)		DEFAULT '0' NOT NULL,
  alarm_history		int(4)		DEFAULT '0' NOT NULL,
  refresh_unsupported	int(4)		DEFAULT '0' NOT NULL,
  work_period		varchar(100)	DEFAULT '1-5,00:00-24:00' NOT NULL,
  PRIMARY KEY (configid)
) type=InnoDB;

insert into config_tmp (select null,alert_history,alarm_history,refresh_unsupported,work_period from config);
drop table config;
alter table config_tmp rename config;

-- Fix indexes of table groups
CREATE TABLE groups_tmp (
  groupid               int(4)          NOT NULL auto_increment,
  name                  varchar(64)     DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid),
  KEY (name)
) type=InnoDB;

insert into groups_tmp (select * from groups);
drop table groups;
alter table groups_tmp rename groups;

 -- Fix indexes of table hosts
CREATE TABLE hosts_tmp (
        hostid          int(4)          NOT NULL auto_increment,
        host            varchar(64)     DEFAULT '' NOT NULL,
        useip           int(1)          DEFAULT '1' NOT NULL,
        ip              varchar(15)     DEFAULT '127.0.0.1' NOT NULL,
        port            int(4)          DEFAULT '0' NOT NULL,
        status          int(4)          DEFAULT '0' NOT NULL,
-- If status=UNREACHABLE, host will not be checked until this time
        disable_until   int(4)          DEFAULT '0' NOT NULL,
        error           varchar(128)    DEFAULT '' NOT NULL,
        available       int(4)          DEFAULT '0' NOT NULL,
        errors_from     int(4)          DEFAULT '0' NOT NULL,
        templateid      int(4)          DEFAULT '0' NOT NULL,
        PRIMARY KEY     (hostid),
--      UNIQUE          (host),
        KEY             (host),
        KEY             (status)
) type=InnoDB;

insert into hosts_tmp (select * from hosts);
drop table hosts;
alter table hosts_tmp rename hosts;

-- Add hostgroupid to table hosts_groups

CREATE TABLE hosts_groups_tmp (
  hostgroupid		int(4)		NOT NULL auto_increment,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  groupid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostgroupid),
  KEY (hostid,groupid)
) type=InnoDB;

insert into hosts_groups_tmp (select null,hostid,groupid from hosts_groups);
drop table hosts_groups;
alter table hosts_groups_tmp rename hosts_groups;


-- Add itemappid to table items_applications
CREATE TABLE items_applications_tmp (
	itemappid		int(4)		NOT NULL auto_increment,
	applicationid		int(4)		DEFAULT '0' NOT NULL,
	itemid			int(4)		DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemappid),
	KEY (applicationid,itemid)
) type=InnoDB;

insert into items_applications_tmp (select null,applicationid,itemid from items_applications);
drop table items_applications;
alter table items_applications_tmp rename items_applications;

-- Add triggerdepid to table trigger_depends

CREATE TABLE trigger_depends_tmp (
	triggerdepid	int(4) NOT NULL auto_increment,
	triggerid_down	int(4) DEFAULT '0' NOT NULL,
	triggerid_up	int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(triggerdepid),
	KEY		(triggerid_down, triggerid_up),
	KEY		(triggerid_up)
) type=InnoDB;

insert into trigger_depends_tmp (select null,triggerid_down,triggerid_up from trigger_depends);
drop table trigger_depends;
alter table trigger_depends_tmp rename trigger_depends;

-- Fixed indexes of users
CREATE TABLE users_tmp (
  userid                int(4)          NOT NULL auto_increment,
  alias                 varchar(100)    DEFAULT '' NOT NULL,
  name                  varchar(100)    DEFAULT '' NOT NULL,
  surname               varchar(100)    DEFAULT '' NOT NULL,
  passwd                char(32)        DEFAULT '' NOT NULL,
  url                   varchar(255)    DEFAULT '' NOT NULL,
  autologout            int(4)          DEFAULT '900' NOT NULL,
  lang                  varchar(5)      DEFAULT 'en_gb' NOT NULL,
  refresh               int(4)          DEFAULT '30' NOT NULL,
  PRIMARY KEY (userid),
--  UNIQUE (alias)
  KEY (alias)
) type=InnoDB;

insert into users_tmp (select * from users);
drop table users;
alter table users_tmp rename users;

-- Add id to table users_groups

CREATE TABLE users_groups_tmp (
  id			int(4)		NOT NULL auto_increment,
  usrgrpid		int(4)		DEFAULT '0' NOT NULL,
  userid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (id),
  KEY (usrgrpid,userid)
) type=InnoDB;

insert into users_groups_tmp (select null,usrgrpid,userid from users_groups);
drop table users_groups;
alter table users_groups_tmp rename users_groups;

-- Ger rid of NULLs
alter table sysmaps_elements modify label_location        int(1)          DEFAULT '0' NOT NULL;

alter table graphs add graphtype	int(2) DEFAULT '0' NOT NULL;
alter table items  add delay_flex       varchar(255) DEFAULT '' NOT NULL;

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

