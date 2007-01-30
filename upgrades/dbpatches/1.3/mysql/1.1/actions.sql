CREATE TABLE actions (
  actionid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
--delay			int(4)		DEFAULT '0' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		blob		DEFAULT '' NOT NULL,
--nextcheck		int(4)		DEFAULT '0' NOT NULL,
  recipient		int(1)		DEFAULT '0' NOT NULL,
  maxrepeats		int(4)		DEFAULT '0' NOT NULL,
  repeatdelay		int(4)		DEFAULT '600' NOT NULL,
  source		int(1)		DEFAULT '0' NOT NULL,
  actiontype		int(1)		DEFAULT '0' NOT NULL,
  status		int(1)		DEFAULT '0' NOT NULL,
  scripts		blob		DEFAULT '' NOT NULL,
  PRIMARY KEY (actionid)
) type=InnoDB;
