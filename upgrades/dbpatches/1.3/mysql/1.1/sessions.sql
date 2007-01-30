CREATE TABLE sessions (
  sessionid		varchar(32)	NOT NULL DEFAULT '',
  userid		int(4)		NOT NULL DEFAULT '0',
  lastaccess		int(4)		NOT NULL DEFAULT '0',
  PRIMARY KEY (sessionid)
) type=InnoDB;
