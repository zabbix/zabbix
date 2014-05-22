alter table sysmaps_elements change label label varchar(255) DEFAULT '' NOT NULL;

ALTER TABLE sysmaps_elements ADD iconid_maintenance BIGINT unsigned DEFAULT '0' NOT NULL;
