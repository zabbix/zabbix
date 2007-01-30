CREATE TABLE history_log (
  id			int(4)		NOT NULL auto_increment,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  timestamp		int(4)		DEFAULT '0' NOT NULL,
  source		varchar(64)	DEFAULT '' NOT NULL,
  severity		int(4)		DEFAULT '0' NOT NULL,
  value			text		DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  KEY itemidclock (itemid, clock)
) type=InnoDB;
