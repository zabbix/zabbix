--
-- Table structure for table 'usrgrp'
--

CREATE TABLE usrgrp (
  usrgrpid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (usrgrpid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'users_groups'
--

CREATE TABLE users_groups (
  usrgrpid		int(4)		DEFAULT '0' NOT NULL,
  userid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (usrgrpid,userid)
) type=InnoDB;
