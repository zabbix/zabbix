CREATE TABLE sysmaps_elements (
  selementid		int(4)		NOT NULL auto_increment,
  sysmapid		int(4)		DEFAULT '0' NOT NULL,
  elementid		int(4)		DEFAULT '0' NOT NULL,
  elementtype		int(4)		DEFAULT '0' NOT NULL,
  icon			varchar(32)	DEFAULT 'Server' NOT NULL,
  icon_on		varchar(32)	DEFAULT 'Server' NOT NULL,
  label			varchar(128)	DEFAULT '' NOT NULL,
  label_location	int(1)		DEFAULT NULL,
  x			int(4)		DEFAULT '0' NOT NULL,
  y			int(4)		DEFAULT '0' NOT NULL,
  url			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (selementid)
) type=InnoDB;
