CREATE TABLE history (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			double(16,4)	DEFAULT '0.0000' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;
