CREATE TABLE valuemaps_tmp (
	valuemapid              number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(64)            DEFAULT ''      ,
	PRIMARY KEY (valuemapid)
);
CREATE INDEX valuemaps_1 on valuemaps_tmp (name);

insert into valuemaps_tmp select * from valuemaps;
drop trigger valuemaps_trigger;
drop sequence valuemaps_valuemapid;
drop table valuemaps;
alter table valuemaps_tmp rename to valuemaps;
