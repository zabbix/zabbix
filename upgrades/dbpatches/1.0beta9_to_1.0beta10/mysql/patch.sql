alter table items add  units           varchar(10)     DEFAULT '' NOT NULL;
alter table items add  multiplier      int(4)          DEFAULT '' NOT NULL;
alter table sysmaps_links add triggerid int(4);

update items set units='bps' where key_ like "netload%";
update items set units='bytes' where key_ like "memory[%]";
update items set units='bytes' where key_ like "disk%[%]";
update items set units='bytes' where key_ like "swap[%]";
update items set units=' ' where key_ like "inode%[%]";

update items set multiplier=1 where key_ like "disk%[%]";

--
-- Table structure for table 'stats'
--

CREATE TABLE stats (
  itemid                int(4)          DEFAULT '0' NOT NULL,
  year                  int(4)          DEFAULT '0' NOT NULL,
  month                 int(4)          DEFAULT '0' NOT NULL,
  day                   int(4)          DEFAULT '0' NOT NULL,
  hour                  int(4)          DEFAULT '0' NOT NULL,
  value_max		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_min		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_avg		double(16,4)	DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,year,month,day,hour)
) type=InnoDB;
