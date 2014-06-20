CREATE TABLE users_groups_tmp (
	id              number(20)              DEFAULT '0'     NOT NULL,
	usrgrpid                number(20)              DEFAULT '0'     NOT NULL,
	userid          number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX users_groups_1 on users_groups_tmp (usrgrpid,userid);

create sequence users_groups_tmp_id
start with 1
increment by 1
nomaxvalue;

create trigger users_groups_tmp_trigger
before insert on users_groups_tmp
for each row
begin
	select users_groups_tmp_id.nextval into :new.id from dual;
end;
/

insert into users_groups_tmp select NULL,usrgrpid,userid from users_groups;
drop trigger users_groups_tmp_trigger;
drop sequence users_groups_tmp_id;
drop table users_groups;
alter table users_groups_tmp rename to users_groups;
