alter table items add  units           varchar(10);
alter table items alter units set default '';
update items set units='';

alter table items add  multiplier      int4;
alter table items alter multiplier set default '0';
update items set multiplier='0';

alter table sysmaps_links add triggerid int4;

alter table graphs_items add sortorder int4;
alter table graphs_items alter sortorder set DEFAULT '0';
update graphs_items set sortorder=0;
--alter table graphs_items alter sortorder set not null;

update items set units='Bps' where key_ like "netload%";
update items set units='B' where key_ like "memory[%]";
update items set units='B' where key_ like "disk%[%]";
update items set units='B' where key_ like "swap[%]";
update items set units=' ' where key_ like "inode%[%]";

update items set multiplier=1 where key_ like "disk%[%]";


CREATE TABLE stats (
  itemid                int4            DEFAULT '0' NOT NULL,
  year                  int4            DEFAULT '0' NOT NULL,
  month                 int4            DEFAULT '0' NOT NULL,
  day                   int4            DEFAULT '0' NOT NULL,
  hour                  int4            DEFAULT '0' NOT NULL,
  value_max		float8		DEFAULT '0.0000' NOT NULL,
  value_min		float8		DEFAULT '0.0000' NOT NULL,
  value_avg		float8		DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,year,month,day,hour)
);

alter table screens_items add  resourceid       int4;
alter table screens_items alter resourceid set default '0';
alter table screens_items add  resource         int4;
alter table screens_items alter resource set default '0';
update screens_items set resourceid=graphid, resource=0;
alter table screens_items drop graphid;
