CREATE TABLE sysmaps_tmp (
	sysmapid		bigint		DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	width		integer		DEFAULT '0'	NOT NULL,
	height		integer		DEFAULT '0'	NOT NULL,
	backgroundid		bigint		DEFAULT '0'	NOT NULL,
	label_type		integer		DEFAULT '0'	NOT NULL,
	label_location		integer		DEFAULT '0'	NOT NULL,
	highlight		integer		DEFAULT '1'	NOT NULL,
	expandproblem	integer 	DEFAULT '1' NOT NULL,
	markelements	integer 	DEFAULT '0' NOT NULL,
	PRIMARY KEY (sysmapid)
) with OIDS;

insert into sysmaps_tmp select sysmapid,name,width,height,backgroundid,label_type,label_location,highlight,1,0 from sysmaps;

UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4;
UPDATE sysmaps SET markelements=1 WHERE highlight>3;
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>3;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;

drop table sysmaps;

alter table sysmaps_tmp rename to sysmaps;
CREATE INDEX sysmaps_1 on sysmaps (name);