CREATE TABLE images (
  imageid		int(4)		NOT NULL auto_increment,
  imagetype		int(4)		DEFAULT '0' NOT NULL,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  image			longblob	DEFAULT '' NOT NULL,
  PRIMARY KEY (imageid),
  UNIQUE (imagetype, name)
) type=InnoDB;
