CREATE TABLE users_groups (
  usrgrpid		int(4)		DEFAULT '0' NOT NULL,
  userid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (usrgrpid,userid)
) type=InnoDB;
