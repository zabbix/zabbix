--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
  id			int(4)		NOT NULL auto_increment,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  KEY itemidclock (itemid, clock)
) ENGINE=InnoDB;


alter table media add	period		varchar(100)	DEFAULT '1-7,00:00-23:59' NOT NULL;
alter table screens_items add	colspan		int(4)	DEFAULT '0' NOT NULL;
alter table items add	lastlogsize		int(4)	DEFAULT '0' NOT NULL;
