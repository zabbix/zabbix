CREATE TABLE profiles_tmp (
	profileid		bigint unsigned		NOT NULL auto_increment,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	idx		varchar(64)		DEFAULT ''	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	valuetype		integer		DEFAULT 0	NOT NULL,
	PRIMARY KEY (profileid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX profiles_1 on profiles_tmp (userid,idx);

insert into profiles_tmp select NULL,userid,idx,value,valuetype from profiles;
drop table profiles;
alter table profiles_tmp rename profiles;

CREATE TABLE profiles_tmp (
	profileid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	idx		varchar(64)		DEFAULT ''	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	valuetype		integer		DEFAULT 0	NOT NULL,
	PRIMARY KEY (profileid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX profiles_1 on profiles_tmp (userid,idx);

insert into profiles_tmp select * from profiles;
drop table profiles;
alter table profiles_tmp rename profiles;
