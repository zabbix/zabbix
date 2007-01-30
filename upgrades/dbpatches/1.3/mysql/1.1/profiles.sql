CREATE TABLE profiles (
  profileid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  idx			varchar(64)	DEFAULT '' NOT NULL,
  value			varchar(255)	DEFAULT '' NOT NULL,
  valuetype		int(4)		DEFAULT 0 NOT NULL,
  PRIMARY KEY (profileid),
--  KEY (userid),
  UNIQUE (userid,idx)
) type=InnoDB;
