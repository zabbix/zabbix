alter table graphs_items add color varchar(32) DEFAULT 'Dark Green' NOT NULL;
alter table sysmaps_hosts add icon varchar(32) DEFAULT 'Server' NOT NULL;
alter table alarms add key (triggerid,clock);
alter table users modify passwd char(32) default '' not null;

alter table hosts add useip int1 DEFAULT '0' NOT NULL;
alter table hosts add ip varchar(15) DEFAULT '127.0.0.1' NOT NULL;

alter table actions modify message blob default '' not null;
alter table alerts modify message blob default '' not null;
