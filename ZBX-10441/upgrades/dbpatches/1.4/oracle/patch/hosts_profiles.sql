CREATE TABLE hosts_profiles_tmp (
	hostid          number(20)              DEFAULT '0'     NOT NULL,
	devicetype              varchar2(64)            DEFAULT ''      ,
	name            varchar2(64)            DEFAULT ''      ,
	os              varchar2(64)            DEFAULT ''      ,
	serialno                varchar2(64)            DEFAULT ''      ,
	tag             varchar2(64)            DEFAULT ''      ,
	macaddress              varchar2(64)            DEFAULT ''      ,
	hardware                varchar2(2048)          DEFAULT ''      ,
	software                varchar2(2048)          DEFAULT ''      ,
	contact         varchar2(2048)          DEFAULT ''      ,
	location                varchar2(2048)          DEFAULT ''      ,
	notes           varchar2(2048)          DEFAULT ''      ,
	PRIMARY KEY (hostid)
);

insert into hosts_profiles_tmp select * from hosts_profiles;
drop table hosts_profiles;
alter table hosts_profiles_tmp rename to hosts_profiles;
