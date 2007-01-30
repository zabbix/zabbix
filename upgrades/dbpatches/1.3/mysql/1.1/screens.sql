CREATE TABLE screens (
  screenid		int(4)		NOT NULL auto_increment,
  name			varchar(255)	DEFAULT 'Screen' NOT NULL,
  hsize			int(4)		DEFAULT '1' NOT NULL,
  vsize			int(4)		DEFAULT '1' NOT NULL,
  PRIMARY KEY  (screenid)
) TYPE=InnoDB;
