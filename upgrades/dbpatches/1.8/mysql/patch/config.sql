alter table config add dropdown_first_entry integer DEFAULT '1' NOT NULL;
alter table config add dropdown_first_remember integer DEFAULT '1' NOT NULL;
alter table config add discovery_groupid bigint unsigned DEFAULT '0' NOT NULL;
alter table config add max_in_table integer DEFAULT '50' NOT NULL;
alter table config add search_limit integer DEFAULT '1000' NOT NULL;
