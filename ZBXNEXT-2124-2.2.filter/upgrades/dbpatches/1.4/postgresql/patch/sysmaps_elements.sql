CREATE TABLE sysmaps_elements_tmp (
	selementid	bigint DEFAULT '0'	NOT NULL,
	sysmapid	bigint DEFAULT '0'	NOT NULL,
	elementid	bigint DEFAULT '0'	NOT NULL,
	elementtype	integer		DEFAULT '0'	NOT NULL,
	iconid_off	bigint DEFAULT '0'	NOT NULL,
	iconid_on	bigint DEFAULT '0'	NOT NULL,
	iconid_unknown	bigint DEFAULT '0'	NOT NULL,
	label		varchar(128)		DEFAULT ''	NOT NULL,
	label_location	integer			NULL,
	x		integer		DEFAULT '0'	NOT NULL,
	y		integer		DEFAULT '0'	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (selementid)
) with OIDS;

insert into sysmaps_elements_tmp select s.selementid,s.sysmapid,s.elementid,s.elementtype,i1.imageid,i2.imageid,i1.imageid,s.label,s.label_location,s.x,s.y,s.url from sysmaps_elements s,images i1,images i2 where s.icon=i1.name and s.icon_on=i2.name;
drop table sysmaps_elements;
alter table sysmaps_elements_tmp rename to sysmaps_elements;
