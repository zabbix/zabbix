CREATE TABLE services_tmp (
	serviceid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	algorithm		integer		DEFAULT '0'	NOT NULL,
	triggerid		bigint unsigned			,
	showsla		integer		DEFAULT '0'	NOT NULL,
	goodsla		double(5,2)		DEFAULT '99.9'	NOT NULL,
	sortorder		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (serviceid)
) ENGINE=InnoDB;

insert into services_tmp select * from services;
drop table services;
alter table services_tmp rename services;
