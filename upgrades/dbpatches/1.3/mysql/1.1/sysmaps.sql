CREATE TABLE sysmaps (
  sysmapid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int(4)		DEFAULT '0' NOT NULL,
  height		int(4)		DEFAULT '0' NOT NULL,
  background		varchar(64)	DEFAULT '' NOT NULL,
  label_type		int(4)		DEFAULT '0' NOT NULL,
  label_location	int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (sysmapid),
  UNIQUE (name)
) type=InnoDB;
