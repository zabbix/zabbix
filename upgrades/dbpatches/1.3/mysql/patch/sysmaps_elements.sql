CREATE TABLE sysmaps_elements_tmp (
	selementid		bigint unsigned		DEFAULT '0'	NOT NULL,
	sysmapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	elementid		bigint unsigned		DEFAULT '0'	NOT NULL,
	elementtype		integer		DEFAULT '0'	NOT NULL,
	iconid_off		bigint unsigned		DEFAULT '0'	NOT NULL,
	iconid_on		bigint unsigned		DEFAULT '0'	NOT NULL,
	label		varchar(128)		DEFAULT ''	NOT NULL,
	label_location		integer			NULL,
	x		integer		DEFAULT '0'	NOT NULL,
	y		integer		DEFAULT '0'	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (selementid)
);

insert into sysmaps_elements_tmp select * from sysmaps_elements;
drop table sysmaps_elements;
alter table sysmaps_elements_tmp rename sysmaps_elements;
