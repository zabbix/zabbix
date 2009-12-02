CREATE TABLE sysmaps_tmp
(
  sysmapid bigint NOT NULL DEFAULT 0,
  name varchar(128) NOT NULL DEFAULT '',
  width integer NOT NULL DEFAULT 0,
  height integer NOT NULL DEFAULT 0,
  backgroundid bigint NOT NULL DEFAULT 0,
  label_type integer NOT NULL DEFAULT 0,
  label_location integer NOT NULL DEFAULT 0,
  highlight integer NOT NULL DEFAULT 1
)
WITH (
  OIDS=TRUE
);


insert into sysmaps_tmp select sysmapid,name,width,height,backgroundid,label_type,label_location from sysmaps;
drop table sysmaps;
alter table sysmaps_tmp rename to sysmaps;
