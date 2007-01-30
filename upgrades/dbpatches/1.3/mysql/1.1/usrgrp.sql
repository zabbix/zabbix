CREATE TABLE usrgrp (
  usrgrpid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (usrgrpid),
  UNIQUE (name)
) type=InnoDB;
