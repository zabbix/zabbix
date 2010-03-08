CREATE TABLE valuemaps_tmp (
	valuemapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (valuemapid)
) ENGINE=InnoDB;
CREATE INDEX valuemaps_1 on valuemaps_tmp (name);

insert into valuemaps_tmp select * from valuemaps;
drop table valuemaps;
alter table valuemaps_tmp rename valuemaps;
