CREATE TABLE profiles_tmp (
	profileid	bigint DEFAULT '0'	NOT NULL,
	userid		bigint DEFAULT '0'	NOT NULL,
	idx		varchar(64)		DEFAULT ''	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	valuetype	integer		DEFAULT 0	NOT NULL,
	PRIMARY KEY (profileid)
) with OIDS;
CREATE UNIQUE INDEX profiles_tmp_1 on profiles_tmp (userid,idx);

insert into profiles_tmp select NULL,userid,idx,value,valuetype from profiles;
drop table profiles;
alter table profiles_tmp rename to profiles;

CREATE TABLE profiles_tmp (
	profileid	bigint DEFAULT '0'	NOT NULL,
	userid		bigint DEFAULT '0'	NOT NULL,
	idx		varchar(64)		DEFAULT ''	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	valuetype	integer		DEFAULT 0	NOT NULL,
	PRIMARY KEY (profileid)
) with OIDS;
CREATE UNIQUE INDEX profiles_1 on profiles_tmp (userid,idx);

insert into profiles_tmp select * from profiles;
drop table profiles;
alter table profiles_tmp rename to profiles;
