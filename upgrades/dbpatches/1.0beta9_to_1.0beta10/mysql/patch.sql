--
-- Table structure for table 'stats'
--

CREATE TABLE stats (
  itemid                int(4)          DEFAULT '0' NOT NULL,
  year                  int(4)          DEFAULT '0' NOT NULL,
  month                 int(4)          DEFAULT '0' NOT NULL,
  day                   int(4)          DEFAULT '0' NOT NULL,
  hour                  int(4)          DEFAULT '0' NOT NULL,
  value                 double(16,4)    DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,year,month,day,hour),
) type=InnoDB;
