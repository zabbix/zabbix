alter table sysmaps_elements  modify label           nvarchar2(255)            DEFAULT '';
ALTER TABLE sysmaps_elements ADD iconid_maintenance number(20) DEFAULT '0' NOT NULL;

alter table sysmaps_elements modify url             nvarchar2(255)          DEFAULT '';
