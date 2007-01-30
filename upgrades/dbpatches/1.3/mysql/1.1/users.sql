CREATE TABLE users (
  userid		int(4)		NOT NULL auto_increment,
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		char(32)	DEFAULT '' NOT NULL,
  url			varchar(255)	DEFAULT '' NOT NULL,
  autologout		int(4)		DEFAULT '900' NOT NULL,
  lang			varchar(5)	DEFAULT 'en_gb' NOT NULL,
  refresh		int(4)		DEFAULT '30' NOT NULL,
  PRIMARY KEY (userid),
  UNIQUE (alias)
) type=InnoDB;
