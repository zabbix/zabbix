CREATE TABLE sysmap_element_url (
	sysmapelementurlid       BIGINT                                NOT NULL,
	selementid               BIGINT                                NOT NULL,
	name                     varchar(255)                            ,
	url                      varchar(255)  DEFAULT ''                ,
	PRIMARY KEY (sysmapelementurlid)
)
/
CREATE UNIQUE INDEX sysmap_element_url_1 on sysmap_element_url (selementid,name)
/
ALTER TABLE sysmap_element_url ADD CONSTRAINT c_sysmap_element_url_1 FOREIGN KEY (selementid) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE
/

INSERT INTO sysmap_element_url (sysmapelementurlid,selementid,name,url)
	SELECT selementid,selementid,url,url FROM sysmaps_elements WHERE url IS NOT NULL
/

ALTER TABLE sysmaps_elements ALTER COLUMN selementid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN sysmapid SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_off SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_off DROP NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_on SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_on DROP NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements DROP COLUMN iconid_unknown
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_disabled SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_disabled DROP NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_maintenance SET WITH DEFAULT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ALTER COLUMN iconid_maintenance DROP NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements DROP COLUMN url
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ADD elementsubtype integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ADD areatype integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ADD width integer WITH DEFAULT '200' NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ADD height integer WITH DEFAULT '200' NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ADD viewtype integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE sysmaps_elements
/
ALTER TABLE sysmaps_elements ADD use_iconmap integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE sysmaps_elements
/
DELETE FROM sysmaps_elements WHERE sysmapid NOT IN (SELECT sysmapid FROM sysmaps)
/
UPDATE sysmaps_elements SET iconid_off=NULL WHERE iconid_off=0
/
UPDATE sysmaps_elements SET iconid_on=NULL WHERE iconid_on=0
/
UPDATE sysmaps_elements SET iconid_disabled=NULL WHERE iconid_disabled=0
/
UPDATE sysmaps_elements SET iconid_maintenance=NULL WHERE iconid_maintenance=0
/
UPDATE sysmaps_elements SET iconid_off=NULL WHERE NOT iconid_off IS NULL AND NOT iconid_off IN (SELECT imageid FROM images WHERE imagetype=1)
/
UPDATE sysmaps_elements SET iconid_on=NULL WHERE NOT iconid_on IS NULL AND NOT iconid_on IN (SELECT imageid FROM images WHERE imagetype=1)
/
UPDATE sysmaps_elements SET iconid_disabled=NULL WHERE NOT iconid_disabled IS NULL AND NOT iconid_disabled IN (SELECT imageid FROM images WHERE imagetype=1)
/
UPDATE sysmaps_elements SET iconid_maintenance=NULL WHERE NOT iconid_maintenance IS NULL AND NOT iconid_maintenance IN (SELECT imageid FROM images WHERE imagetype=1)
/
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE
/
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_2 FOREIGN KEY (iconid_off) REFERENCES images (imageid)
/
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_3 FOREIGN KEY (iconid_on) REFERENCES images (imageid)
/
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_4 FOREIGN KEY (iconid_disabled) REFERENCES images (imageid)
/
ALTER TABLE sysmaps_elements ADD CONSTRAINT c_sysmaps_elements_5 FOREIGN KEY (iconid_maintenance) REFERENCES images (imageid)
/
