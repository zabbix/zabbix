alter table services modify goodsla double(3,2) default '99.9' not null;
alter table services add sortorder int(4) default '0' not null;

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
