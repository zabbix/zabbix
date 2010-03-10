CREATE TABLE sysmaps_links_tmp (
	linkid		bigint DEFAULT '0'	NOT NULL,
	sysmapid	bigint DEFAULT '0'	NOT NULL,
	selementid1	bigint DEFAULT '0'	NOT NULL,
	selementid2	bigint DEFAULT '0'	NOT NULL,
	triggerid	bigint ,
	drawtype_off	integer		DEFAULT '0'	NOT NULL,
	color_off	varchar(32)		DEFAULT 'Black'	NOT NULL,
	drawtype_on	integer		DEFAULT '0'	NOT NULL,
	color_on	varchar(32)		DEFAULT 'Red'	NOT NULL,
	PRIMARY KEY (linkid)
) with OIDS;

insert into sysmaps_links_tmp select * from sysmaps_links;
drop table sysmaps_links;
alter table sysmaps_links_tmp rename to sysmaps_links;
