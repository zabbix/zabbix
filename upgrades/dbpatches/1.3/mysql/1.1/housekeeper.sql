CREATE TABLE housekeeper (
  housekeeperid		int(4)		NOT NULL auto_increment,
  tablename		varchar(64)	DEFAULT '' NOT NULL,
  field			varchar(64)	DEFAULT '' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (housekeeperid)
) type=InnoDB;
