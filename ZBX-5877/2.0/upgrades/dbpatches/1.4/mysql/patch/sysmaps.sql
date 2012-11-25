CREATE TABLE sysmaps_tmp (
	sysmapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	width		integer		DEFAULT '0'	NOT NULL,
	height		integer		DEFAULT '0'	NOT NULL,
	backgroundid		bigint unsigned		DEFAULT '0'	NOT NULL,
	label_type		integer		DEFAULT '0'	NOT NULL,
	label_location		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sysmapid)
) ENGINE=InnoDB;
CREATE INDEX sysmaps_1 on sysmaps_tmp (name);

insert into sysmaps_tmp select s.sysmapid,s.name,s.width,s.height,i.imageid,s.label_type,s.label_location from sysmaps s,images i where s.background=i.name;
insert into sysmaps_tmp select s.sysmapid,s.name,s.width,s.height,0,s.label_type,s.label_location from sysmaps s where s.background='';
drop table sysmaps;
alter table sysmaps_tmp rename sysmaps;
