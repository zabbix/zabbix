CREATE TABLE sysmap_element_url (
	sysmapelementurlid       bigint unsigned                           NOT NULL,
	selementid               bigint unsigned                           NOT NULL,
	name                     varchar(255)                              NOT NULL,
	url                      varchar(255)    DEFAULT ''                NOT NULL,
	PRIMARY KEY (sysmapelementurlid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX sysmap_element_url_1 on sysmap_element_url (selementid,name);
ALTER TABLE sysmap_element_url ADD CONSTRAINT c_sysmap_element_url_1 FOREIGN KEY (selementid) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;

INSERT INTO sysmap_element_url (sysmapelementurlid,selementid,name,url)
	SELECT selementid,selementid,url,url FROM sysmaps_elements WHERE url<>'';

ALTER TABLE sysmaps_elements
	MODIFY selementid bigint unsigned NOT NULL,
	MODIFY sysmapid bigint unsigned NOT NULL,
	MODIFY iconid_off bigint unsigned NULL,
	MODIFY iconid_on bigint unsigned NULL,
	DROP COLUMN iconid_unknown,
	MODIFY iconid_disabled bigint unsigned NULL,
	MODIFY iconid_maintenance bigint unsigned NULL,
	DROP COLUMN url,
	ADD elementsubtype integer DEFAULT '0' NOT NULL,
	ADD areatype integer DEFAULT '0' NOT NULL,
	ADD width integer DEFAULT '200' NOT NULL,
	ADD height integer DEFAULT '200' NOT NULL,
	ADD viewtype integer DEFAULT '0' NOT NULL,
	ADD use_iconmap integer DEFAULT '1' NOT NULL;

DELETE FROM sysmaps_elements WHERE sysmapid NOT IN (SELECT sysmapid FROM sysmaps);
UPDATE sysmaps_elements SET iconid_off=NULL WHERE iconid_off=0;
UPDATE sysmaps_elements SET iconid_on=NULL WHERE iconid_on=0;
UPDATE sysmaps_elements SET iconid_disabled=NULL WHERE iconid_disabled=0;
UPDATE sysmaps_elements SET iconid_maintenance=NULL WHERE iconid_maintenance=0;
UPDATE sysmaps_elements SET iconid_off=NULL WHERE NOT iconid_off IS NULL AND NOT iconid_off IN (SELECT imageid FROM images WHERE imagetype=1);
UPDATE sysmaps_elements SET iconid_on=NULL WHERE NOT iconid_on IS NULL AND NOT iconid_on IN (SELECT imageid FROM images WHERE imagetype=1);
UPDATE sysmaps_elements SET iconid_disabled=NULL WHERE NOT iconid_disabled IS NULL AND NOT iconid_disabled IN (SELECT imageid FROM images WHERE imagetype=1);
UPDATE sysmaps_elements SET iconid_maintenance=NULL WHERE NOT iconid_maintenance IS NULL AND NOT iconid_maintenance IN (SELECT imageid FROM images WHERE imagetype=1);
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE;
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_2 FOREIGN KEY (iconid_off) REFERENCES images (imageid);
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_3 FOREIGN KEY (iconid_on) REFERENCES images (imageid);
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_4 FOREIGN KEY (iconid_disabled) REFERENCES images (imageid);
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_5 FOREIGN KEY (iconid_maintenance) REFERENCES images (imageid);
