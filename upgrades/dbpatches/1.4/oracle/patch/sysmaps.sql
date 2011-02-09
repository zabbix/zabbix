CREATE TABLE sysmaps_tmp (
	sysmapid                number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(128)           DEFAULT ''      ,
	width           number(10)              DEFAULT '0'     NOT NULL,
	height          number(10)              DEFAULT '0'     NOT NULL,
	backgroundid            number(20)              DEFAULT '0'     NOT NULL,
	label_type              number(10)              DEFAULT '0'     NOT NULL,
	label_location          number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (sysmapid)
);
CREATE INDEX sysmaps_1 on sysmaps_tmp (name);

insert into sysmaps_tmp select s.sysmapid,s.name,s.width,s.height,i.imageid,s.label_type,s.label_location from sysmaps s,images i where s.background=i.name;
insert into sysmaps_tmp select s.sysmapid,s.name,s.width,s.height,0,s.label_type,s.label_location from sysmaps s where s.background='';
drop trigger sysmaps_trigger;
drop sequence sysmaps_sysmapid;
drop table sysmaps;
alter table sysmaps_tmp rename to sysmaps;
