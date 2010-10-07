-- Creating table `sysmap_element_url`
CREATE TABLE IF NOT EXISTS sysmap_element_url (
  sysmapelementurlid bigint(20) unsigned NOT NULL,
  selementid bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (sysmapelementurlid),
  UNIQUE KEY sysmap_element_url_1 (selementid,name)
) ENGINE=InnoDB;

-- adding foreight key constraint
ALTER TABLE sysmap_element_url ADD CONSTRAINT c_sysmap_element_url_1 FOREIGN KEY (selementid) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;

--moving data from sysmaps_elements
INSERT INTO sysmap_element_url
(
  sysmapelementurlid,
  selementid,
  name,
  url
)
SELECT
  selementid,
  selementid,
  url,
  url
FROM
  sysmaps_elements

--removing empty urls
DELETE FROM sysmap_element_url WHERE name='' AND url=''

--removing `url` column from `sysmaps_elements`
ALTER TABLE sysmaps_elements DROP COLUMN url
