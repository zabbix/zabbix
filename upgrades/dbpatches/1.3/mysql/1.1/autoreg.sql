CREATE TABLE autoreg (
  id			int(4)		NOT NULL auto_increment,
  priority		int(4)		DEFAULT '0' NOT NULL,
  pattern		varchar(255)	DEFAULT '' NOT NULL,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (id)
) type=InnoDB;
