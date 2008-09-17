alter table sysmaps_links drop triggerid;
alter table sysmaps_links drop drawtype_off;
alter table sysmaps_links drop color_off;
alter table sysmaps_links drop drawtype_on;
alter table sysmaps_links drop color_on;

alter table sysmaps_links add drawtype integer DEFAULT '0' NOT NULL;
alter table sysmaps_links add color varchar(6) DEFAULT '000000' NOT NULL;

