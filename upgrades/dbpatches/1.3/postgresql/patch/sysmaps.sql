CREATE TABLE sysmaps_tmp (
	sysmapid	bigint DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	width		integer		DEFAULT '0'	NOT NULL,
	height		integer		DEFAULT '0'	NOT NULL,
	backgroundid	bigint DEFAULT '0'	NOT NULL,
	label_type	integer		DEFAULT '0'	NOT NULL,
	label_location	integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sysmapid)
);
CREATE INDEX sysmaps_1 on sysmaps_tmp (name);

insert into sysmaps_tmp select * from sysmaps;
drop table sysmaps;
alter table sysmaps_tmp rename to sysmaps;
