alter table users add  url			varchar(255)	DEFAULT '' NOT NULL;
alter table sysmaps add  use_background		int4	DEFAULT 0 NOT NULL;
alter table sysmaps add  background		blob	DEFAULT '' NOT NULL;

alter table items add trends int4 DEFAULT '365' NOT NULL;
