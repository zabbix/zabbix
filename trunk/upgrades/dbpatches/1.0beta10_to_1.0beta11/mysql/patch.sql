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

insert into usrgrp (usrgrpid, name) values (NULL, 'UNIX administrators');  
insert into usrgrp (usrgrpid, name) values (NULL, 'Database administrators');
insert into usrgrp (usrgrpid, name) values (NULL, 'Network administrators');
insert into usrgrp (usrgrpid, name) values (NULL, 'Security specialists');
insert into usrgrp (usrgrpid, name) values (NULL, 'WEB administrators');
insert into usrgrp (usrgrpid, name) values (NULL, 'Head of IT department');  
insert into usrgrp (usrgrpid, name) values (NULL, 'Zabbix administrators');  

alter table items add delta int(1)  DEFAULT '0' NOT NULL;
alter table items add prevorgvalue double(16,4)  DEFAULT NULL;

alter table actions add recipient int(1)  DEFAULT '0' NOT NULL;
