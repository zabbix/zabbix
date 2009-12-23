alter table sysmaps_links  add label           nvarchar2(255)            DEFAULT ''      NOT NULL;

alter table sysmaps_links modify color           nvarchar2(6)            DEFAULT '000000';
