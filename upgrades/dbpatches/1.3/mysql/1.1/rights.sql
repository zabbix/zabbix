CREATE TABLE rights (
  rightid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  name			char(255)	DEFAULT '' NOT NULL,
  permission		char(1)		DEFAULT '' NOT NULL,
  id			int(4),
  PRIMARY KEY (rightid),
  KEY (userid)
) type=InnoDB;
