CREATE TABLE sysmaps_elements_tmp (
	selementid              number(20)              DEFAULT '0'     NOT NULL,
	sysmapid                number(20)              DEFAULT '0'     NOT NULL,
	elementid               number(20)              DEFAULT '0'     NOT NULL,
	elementtype             number(10)              DEFAULT '0'     NOT NULL,
	iconid_off              number(20)              DEFAULT '0'     NOT NULL,
	iconid_on               number(20)              DEFAULT '0'     NOT NULL,
	iconid_unknown          number(20)              DEFAULT '0'     NOT NULL,
	label           varchar2(128)           DEFAULT ''      ,
	label_location          number(10)                      NULL,
	x               number(10)              DEFAULT '0'     NOT NULL,
	y               number(10)              DEFAULT '0'     NOT NULL,
	url             varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (selementid)
);

insert into sysmaps_elements_tmp select s.selementid,s.sysmapid,s.elementid,s.elementtype,i1.imageid,i2.imageid,i1.imageid,s.label,s.label_location,s.x,s.y,s.url from sysmaps_elements s,images i1,images i2 where s.icon=i1.name and s.icon_on=i2.name;
drop trigger sysmaps_elements_trigger;
drop sequence sysmaps_elements_selementid;
drop table sysmaps_elements;
alter table sysmaps_elements_tmp rename to sysmaps_elements;
