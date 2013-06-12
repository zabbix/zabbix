CREATE TABLE mappings_tmp (
	mappingid		bigint unsigned		DEFAULT '0'	NOT NULL,
	valuemapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	value		varchar(64)		DEFAULT ''	NOT NULL,
	newvalue		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (mappingid)
) ENGINE=InnoDB;
CREATE INDEX mappings_1 on mappings_tmp (valuemapid);

insert into mappings_tmp select * from mappings;
drop table mappings;
alter table mappings_tmp rename mappings;
