alter table items add  units           varchar(10)     DEFAULT '' NOT NULL;

update items set units='bps' where key_ like "netload%";

CREATE TABLE stats (
  itemid                int4            DEFAULT '0' NOT NULL,
  year                  int4            DEFAULT '0' NOT NULL,
  month                 int4            DEFAULT '0' NOT NULL,
  day                   int4            DEFAULT '0' NOT NULL,
  hour                  int4            DEFAULT '0' NOT NULL,
  value                 float8          DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,year,month,day,hour),
);
