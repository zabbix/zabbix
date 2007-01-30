CREATE TABLE conditions (
  conditionid		int(4)		NOT NULL auto_increment,
  actionid		int(4)		DEFAULT '0' NOT NULL,
  conditiontype		int(4)		DEFAULT '0' NOT NULL,
  operator		int(1)		DEFAULT '0' NOT NULL,
  value			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (conditionid),
  KEY (actionid)
) type=InnoDB;
