alter table graphs_items add color varchar(32) DEFAULT 'Dark Green' NOT NULL;
alter table sysmaps_hosts add icon varchar(32) DEFAULT 'Server' NOT NULL;
alter table alarms add key (triggerid,clock);
alter table users modify passwd char(32) default '' not null;
