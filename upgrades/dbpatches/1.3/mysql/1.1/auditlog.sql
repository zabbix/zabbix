CREATE TABLE auditlog (
  auditid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  action		int(4)		DEFAULT '0' NOT NULL,
  resourcetype		int(4)		DEFAULT '0' NOT NULL,
  details		varchar(128)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (auditid),
  KEY (userid,clock),
  KEY (clock)
) type=InnoDB;
