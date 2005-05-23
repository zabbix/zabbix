--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
  itemid                int(4)          DEFAULT '0' NOT NULL,
  clock                 int(4)          DEFAULT '0' NOT NULL,
  value                 varchar(255)    DEFAULT '' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;
