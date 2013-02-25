CREATE TABLE groups_tmp (
	groupid         number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(64)            DEFAULT ''      ,
	PRIMARY KEY (groupid)
);
CREATE INDEX groups_1 on groups_tmp (name);

insert into groups_tmp select groupid,name from groups;
drop trigger groups_trigger;
drop sequence groups_groupid;
drop table groups;
alter table groups_tmp rename to groups;
