CREATE TABLE sysmaps_links_tmp (
	linkid			bigint		DEFAULT 0		NOT NULL,
	sysmapid		bigint		DEFAULT 0		NOT NULL,
	selementid1		bigint		DEFAULT 0		NOT NULL,
	selementid2		bigint		DEFAULT 0		NOT NULL,
	drawtype		integer		DEFAULT 0		NOT NULL,
	color			varchar(6)	DEFAULT '000000'	NOT NULL,
	label			varchar(255)	DEFAULT ''		NOT NULL,
	PRIMARY KEY (linkid)
) with OIDS;

insert into sysmaps_links_tmp select linkid,sysmapid,selementid1,selementid2,drawtype,color,'' from sysmaps_links;
drop table sysmaps_links;
alter table sysmaps_links_tmp rename to sysmaps_links;
