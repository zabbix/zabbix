CREATE TABLE groups (
  groupid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid),
  UNIQUE (name)
) type=InnoDB;
