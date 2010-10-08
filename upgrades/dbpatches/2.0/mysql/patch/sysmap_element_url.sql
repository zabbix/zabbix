CREATE TABLE IF NOT EXISTS sysmap_element_url (
  sysmapelementurlid bigint(20) unsigned NOT NULL,
  selementid bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (sysmapelementurlid),
  UNIQUE KEY sysmap_element_url_1 (selementid,name)
) ENGINE=InnoDB;

ALTER TABLE sysmap_element_url ADD CONSTRAINT c_sysmap_element_url_1 FOREIGN KEY (selementid) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;

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
WHERE
  url!=''

ALTER TABLE sysmaps_elements DROP COLUMN url
