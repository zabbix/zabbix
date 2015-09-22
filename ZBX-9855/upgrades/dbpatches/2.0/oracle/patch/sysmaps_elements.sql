CREATE TABLE sysmap_element_url (
	sysmapelementurlid       number(20)                                NOT NULL,
	selementid               number(20)                                NOT NULL,
	name                     nvarchar2(255)                            ,
	url                      nvarchar2(255)  DEFAULT ''                ,
	PRIMARY KEY (sysmapelementurlid)
);
CREATE UNIQUE INDEX sysmap_element_url_1 on sysmap_element_url (selementid,name);
ALTER TABLE sysmap_element_url ADD CONSTRAINT c_sysmap_element_url_1 FOREIGN KEY (selementid) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;

INSERT INTO sysmap_element_url (sysmapelementurlid,selementid,name,url)
	SELECT selementid,selementid,url,url FROM sysmaps_elements WHERE url IS NOT NULL;

ALTER TABLE sysmaps_elements MODIFY selementid DEFAULT NULL;
ALTER TABLE sysmaps_elements MODIFY sysmapid DEFAULT NULL;
ALTER TABLE sysmaps_elements MODIFY iconid_off DEFAULT NULL;
ALTER TABLE sysmaps_elements MODIFY iconid_off NULL;
ALTER TABLE sysmaps_elements MODIFY iconid_on DEFAULT NULL;
ALTER TABLE sysmaps_elements MODIFY iconid_on NULL;
ALTER TABLE sysmaps_elements DROP COLUMN iconid_unknown;
ALTER TABLE sysmaps_elements MODIFY iconid_disabled DEFAULT NULL;
ALTER TABLE sysmaps_elements MODIFY iconid_disabled NULL;
ALTER TABLE sysmaps_elements MODIFY iconid_maintenance DEFAULT NULL;
ALTER TABLE sysmaps_elements MODIFY iconid_maintenance NULL;
ALTER TABLE sysmaps_elements DROP COLUMN url;
ALTER TABLE sysmaps_elements ADD elementsubtype number(10) DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps_elements ADD areatype number(10) DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps_elements ADD width number(10) DEFAULT '200' NOT NULL;
ALTER TABLE sysmaps_elements ADD height number(10) DEFAULT '200' NOT NULL;
ALTER TABLE sysmaps_elements ADD viewtype number(10) DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps_elements ADD use_iconmap number(10) DEFAULT '1' NOT NULL;

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
