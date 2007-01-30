CREATE TABLE history_text (
  id			int(4)		NOT NULL auto_increment,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			text		DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  KEY itemidclock (itemid, clock)
) type=InnoDB;
