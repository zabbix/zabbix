CREATE TABLE sysmaps_links_tmp (
	linkid          number(20)              DEFAULT '0'     NOT NULL,
	sysmapid                number(20)              DEFAULT '0'     NOT NULL,
	selementid1             number(20)              DEFAULT '0'     NOT NULL,
	selementid2             number(20)              DEFAULT '0'     NOT NULL,
	triggerid               number(20)                      ,
	drawtype_off            number(10)              DEFAULT '0'     NOT NULL,
	color_off               varchar2(32)            DEFAULT 'Black' ,
	drawtype_on             number(10)              DEFAULT '0'     NOT NULL,
	color_on                varchar2(32)            DEFAULT 'Red'   ,
	PRIMARY KEY (linkid)
);

insert into sysmaps_links_tmp select * from sysmaps_links;
drop trigger sysmaps_links_trigger;
drop sequence sysmaps_links_linkid;
drop table sysmaps_links;
alter table sysmaps_links_tmp rename to sysmaps_links;
