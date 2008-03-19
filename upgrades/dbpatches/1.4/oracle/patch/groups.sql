CREATE TABLE groups (
        groupid         number(20)              DEFAULT '0'     NOT NULL,
        name            varchar2(64)            DEFAULT ''      ,
        PRIMARY KEY (groupid)
);
CREATE INDEX groups_1 on groups (name);

insert into groups_tmp select * from groups;
drop table groups;
alter table groups_tmp rename groups;
