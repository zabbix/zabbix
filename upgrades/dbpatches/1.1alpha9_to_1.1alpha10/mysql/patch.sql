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
) type=InnoDB;
