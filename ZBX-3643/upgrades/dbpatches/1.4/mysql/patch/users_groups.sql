CREATE TABLE users_groups_tmp (
	id		bigint unsigned		NOT NULL auto_increment,
	usrgrpid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE INDEX users_groups_1 on users_groups_tmp (usrgrpid,userid);

insert into users_groups_tmp select NULL,usrgrpid,userid from users_groups;
drop table users_groups;
alter table users_groups_tmp rename users_groups;

CREATE TABLE users_groups_tmp (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	usrgrpid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE INDEX users_groups_1 on users_groups_tmp (usrgrpid,userid);

insert into users_groups_tmp select * from users_groups;
drop table users_groups;
alter table users_groups_tmp rename users_groups;
