CREATE TABLE profiles_tmp (
	profileid               number(20)              DEFAULT '0'     NOT NULL,
	userid          number(20)              DEFAULT '0'     NOT NULL,
	idx             varchar2(64)            DEFAULT ''      ,
	value           varchar2(255)           DEFAULT ''      ,
	valuetype               number(10)              DEFAULT 0       NOT NULL,
	PRIMARY KEY (profileid)
);
CREATE UNIQUE INDEX profiles_1 on profiles_tmp (userid,idx);

insert into profiles_tmp select * from profiles;
drop trigger profiles_trigger;
drop sequence profiles_profileid;
drop table profiles;
alter table profiles_tmp rename to profiles;
