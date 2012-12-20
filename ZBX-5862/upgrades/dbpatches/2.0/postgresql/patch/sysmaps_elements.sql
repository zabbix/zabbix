CREATE TABLE sysmap_element_url (
	sysmapelementurlid       bigint                                    NOT NULL,
	selementid               bigint                                    NOT NULL,
	name                     varchar(255)                              NOT NULL,
	url                      varchar(255)    DEFAULT ''                NOT NULL,
	PRIMARY KEY (sysmapelementurlid)
);
CREATE UNIQUE INDEX sysmap_element_url_1 on sysmap_element_url (selementid,name);
ALTER TABLE ONLY sysmap_element_url ADD CONSTRAINT c_sysmap_element_url_1 FOREIGN KEY (selementid) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;

INSERT INTO sysmap_element_url (sysmapelementurlid,selementid,name,url)
	SELECT selementid,selementid,url,url FROM sysmaps_elements WHERE url<>'';

ALTER TABLE ONLY sysmaps_elements
	ALTER selementid DROP DEFAULT,
	ALTER sysmapid DROP DEFAULT,
	ALTER iconid_off DROP DEFAULT,
	ALTER iconid_off DROP NOT NULL,
	ALTER iconid_on DROP DEFAULT,
	ALTER iconid_on DROP NOT NULL,
	DROP COLUMN iconid_unknown,
	ALTER iconid_disabled DROP DEFAULT,
	ALTER iconid_disabled DROP NOT NULL,
	ALTER iconid_maintenance DROP DEFAULT,
	ALTER iconid_maintenance DROP NOT NULL,
	DROP COLUMN url,
	ADD elementsubtype integer DEFAULT '0' NOT NULL,
	ADD areatype integer DEFAULT '0' NOT NULL,
	ADD width integer DEFAULT '200' NOT NULL,
	ADD height integer DEFAULT '200' NOT NULL,
	ADD viewtype integer DEFAULT '0' NOT NULL,
	ADD use_iconmap integer DEFAULT '1' NOT NULL;

DELETE FROM sysmaps_elements WHERE NOT EXISTS (SELECT 1 FROM sysmaps WHERE sysmaps.sysmapid=sysmaps_elements.sysmapid);
UPDATE sysmaps_elements SET iconid_off=NULL WHERE iconid_off=0;
UPDATE sysmaps_elements SET iconid_on=NULL WHERE iconid_on=0;
UPDATE sysmaps_elements SET iconid_disabled=NULL WHERE iconid_disabled=0;
UPDATE sysmaps_elements SET iconid_maintenance=NULL WHERE iconid_maintenance=0;
UPDATE sysmaps_elements SET iconid_off=NULL WHERE iconid_off IS NOT NULL AND NOT EXISTS (SELECT imageid FROM images WHERE images.imagetype=1 and images.imageid=sysmaps_elements.iconid_off );
UPDATE sysmaps_elements SET iconid_on=NULL WHERE iconid_on IS NOT NULL AND NOT EXISTS (SELECT imageid FROM images WHERE images.imagetype=1 and images.imageid=sysmaps_elements.iconid_on);
UPDATE sysmaps_elements SET iconid_disabled=NULL WHERE iconid_disabled IS NOT NULL AND NOT EXISTS (SELECT imageid FROM images WHERE images.imagetype=1 and images.imageid=sysmaps_elements.iconid_disabled);
UPDATE sysmaps_elements SET iconid_maintenance=NULL WHERE iconid_maintenance IS NOT NULL AND NOT EXISTS (SELECT imageid FROM images WHERE images.imagetype=1 and images.imageid=sysmaps_elements.iconid_maintenance);
ALTER TABLE ONLY sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE;
ALTER TABLE ONLY sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_2 FOREIGN KEY (iconid_off) REFERENCES images (imageid);
ALTER TABLE ONLY sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_3 FOREIGN KEY (iconid_on) REFERENCES images (imageid);
ALTER TABLE ONLY sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_4 FOREIGN KEY (iconid_disabled) REFERENCES images (imageid);
ALTER TABLE ONLY sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_5 FOREIGN KEY (iconid_maintenance) REFERENCES images (imageid);
