--
-- Table structure for table 'usrgrp'
--

CREATE TABLE usrgrp (
  usrgrpid		serial,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (usrgrpid)
);

CREATE UNIQUE INDEX usrgrp_name on usrgrp (name);

--
-- Table structure for table 'users_groups'
--

CREATE TABLE users_groups (
  usrgrpid		int4		DEFAULT '0' NOT NULL,
  userid		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (usrgrpid,userid),
  FOREIGN KEY (usrgrpid) REFERENCES usrgrp,
  FOREIGN KEY (userid) REFERENCES users
);
