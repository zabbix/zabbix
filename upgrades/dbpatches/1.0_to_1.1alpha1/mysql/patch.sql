alter table users add  url			varchar(255)	DEFAULT '' NOT NULL;
alter table sysmaps add  use_background		int(4)	DEFAULT 0 NOT NULL;
alter table sysmaps add  background		longblob	DEFAULT '' NOT NULL;

alter table items add trends int(4) DEFAULT '365' NOT NULL;

alter table graphs add  yaxistype		int(1)		DEFAULT '0' NOT NULL;
alter table graphs add  yaxismin		double(16,4)	DEFAULT '0' NOT NULL;
alter table graphs add  yaxismax		double(16,4)	DEFAULT '0' NOT NULL;
