CREATE TABLE groups_tmp (
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (groupid)
) ENGINE=InnoDB;
CREATE INDEX groups_1 on groups_tmp (name);

insert into groups_tmp select * from groups;
drop table groups;
alter table groups_tmp rename groups;
