CREATE TABLE mappings_tmp (
	mappingid               number(20)              DEFAULT '0'     NOT NULL,
	valuemapid              number(20)              DEFAULT '0'     NOT NULL,
	value           varchar2(64)            DEFAULT ''      ,
	newvalue                varchar2(64)            DEFAULT ''      ,
	PRIMARY KEY (mappingid)
);
CREATE INDEX mappings_1 on mappings_tmp (valuemapid);

insert into mappings_tmp select * from mappings;
drop trigger mappings_trigger;
drop sequence mappings_mappingid;
drop table mappings;
alter table mappings_tmp rename to mappings;
