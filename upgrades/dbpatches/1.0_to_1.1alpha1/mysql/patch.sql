alter table users add  url			varchar(255)	DEFAULT '' NOT NULL;
alter table sysmaps add  use_background		int(4)	DEFAULT 0 NOT NULL;
alter table sysmaps add  background		longblob	DEFAULT '' NOT NULL;
