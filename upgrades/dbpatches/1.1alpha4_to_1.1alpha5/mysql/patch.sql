CREATE TABLE escalations (
  escalationid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid),
  UNIQUE (name)
) type=InnoDB;
