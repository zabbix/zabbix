CREATE TABLE hosts_profiles_tmp (
	hostid		bigint DEFAULT '0'	NOT NULL,
	devicetype	varchar(64)		DEFAULT ''	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	os		varchar(64)		DEFAULT ''	NOT NULL,
	serialno	varchar(64)		DEFAULT ''	NOT NULL,
	tag		varchar(64)		DEFAULT ''	NOT NULL,
	macaddress	varchar(64)		DEFAULT ''	NOT NULL,
	hardware	text		DEFAULT ''	NOT NULL,
	software	text		DEFAULT ''	NOT NULL,
	contact		text		DEFAULT ''	NOT NULL,
	location	text		DEFAULT ''	NOT NULL,
	notes		text		DEFAULT ''	NOT NULL,
	PRIMARY KEY (hostid)
) with OIDS;

insert into hosts_profiles_tmp select * from hosts_profiles;
drop table hosts_profiles;
alter table hosts_profiles_tmp rename to hosts_profiles;
