alter table graphs_items add color varchar(32) DEFAULT 'Dark Green' NOT NULL;
alter table sysmaps_hosts add icon varchar(32) DEFAULT 'Server' NOT NULL;
create index alarms_trggerid_clock on alarms (triggerid,clock);

alter table hosts add useip int4 DEFAULT '0' NOT NULL;
alter table hosts add ip varchar(15) DEFAULT '127.0.0.1' NOT NULL;
