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

insert into usrgrp (usrgrpid, name) values (NULL, 'UNIX administrators');  
insert into usrgrp (usrgrpid, name) values (NULL, 'Database administrators');
insert into usrgrp (usrgrpid, name) values (NULL, 'Network administrators');
insert into usrgrp (usrgrpid, name) values (NULL, 'Security specialists');
insert into usrgrp (usrgrpid, name) values (NULL, 'WEB administrators');
insert into usrgrp (usrgrpid, name) values (NULL, 'Head of IT department');  
insert into usrgrp (usrgrpid, name) values (NULL, 'Zabbix administrators');  

alter table items add delta int4  DEFAULT '0' NOT NULL;
alter table items add prevorgvalue float8  DEFAULT NULL;

alter table actions add recipient int4  DEFAULT '0' NOT NULL;
