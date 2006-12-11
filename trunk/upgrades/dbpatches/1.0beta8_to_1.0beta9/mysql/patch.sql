alter table services modify goodsla double(3,2) default '99.9' not null;
alter table services add sortorder int(4) default '0' not null;

alter table graphs_items add drawtype int(4) default '0' not null;

alter table actions add scope int(4) default '0' not null;
alter table actions add severity int(4) default '0' not null;

--
-- Table structure for table 'screens'
--

CREATE TABLE screens (
  screenid              int(4)          NOT NULL auto_increment,
  name                  varchar(255)    DEFAULT 'Screen' NOT NULL,
  cols                  int(4)          DEFAULT '1' NOT NULL,
  rows                  int(4)          DEFAULT '1' NOT NULL,
  PRIMARY KEY  (screenid)
) TYPE=InnoDB;

--
-- Table structure for table 'screens_items'
--

CREATE TABLE screens_items (
  screenitemid          int(4)          NOT NULL auto_increment,
  screenid              int(4)          DEFAULT '0' NOT NULL,
  graphid               int(4)          DEFAULT '0' NOT NULL,
  width                 int(4)          DEFAULT '320' NOT NULL,
  height                int(4)          DEFAULT '200' NOT NULL,
  x                     int(4)          DEFAULT '0' NOT NULL,
  y                     int(4)          DEFAULT '0' NOT NULL,
  PRIMARY KEY  (screenitemid)
) TYPE=InnoDB;

alter table config drop smtp_server;
alter table config drop smtp_helo;
alter table config drop smtp_email;

CREATE TABLE media_type (
	mediatypeid     int(4) NOT NULL auto_increment,
	type            int(4)          DEFAULT '0' NOT NULL,
	description     varchar(100)    DEFAULT '' NOT NULL,
	smtp_server     varchar(255)    DEFAULT '' NOT NULL,
	smtp_helo       varchar(255)    DEFAULT '' NOT NULL,
	smtp_email      varchar(255)    DEFAULT '' NOT NULL,
	exec_path	varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY     (mediatypeid)
) type=InnoDB;

alter table media drop type;
alter table media add mediatypeid     int(4) DEFAULT '1' NOT NULL;

alter table alerts drop type;
alter table alerts add mediatypeid     int(4) DEFAULT '1' NOT NULL;

insert into media_type (mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path) values (1,0,'Email','localhost','localhost','zabbix@localhost','');

alter table media add severity     int(4) DEFAULT '63' NOT NULL;
