CREATE TABLE users_groups_tmp (
	id		bigint DEFAULT '0'	NOT NULL,
	usrgrpid	bigint DEFAULT '0'	NOT NULL,
	userid		bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
) with OIDS;
CREATE INDEX users_groups_tmp_1 on users_groups_tmp (usrgrpid,userid);

insert into users_groups_tmp select NULL,usrgrpid,userid from users_groups;
drop table users_groups;
alter table users_groups_tmp rename to users_groups;

CREATE TABLE users_groups_tmp (
	id		bigint DEFAULT '0'	NOT NULL,
	usrgrpid	bigint DEFAULT '0'	NOT NULL,
	userid		bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
) with OIDS;
CREATE INDEX users_groups_1 on users_groups_tmp (usrgrpid,userid);

insert into users_groups_tmp select * from users_groups;
drop table users_groups;
alter table users_groups_tmp rename to users_groups;
