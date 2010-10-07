CREATE TABLE sysmap_element_url (
  sysmapelementurlid bigint NOT NULL,
  selementid bigint NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (sysmapelementurlid)
) with OIDS;
CREATE UNIQUE INDEX sysmap_element_url_1 on sysmap_element_url (selementid,name);

ALTER TABLE ONLY sysmap_element_url ADD CONSTRAINT c_sysmap_element_url_1 FOREIGN KEY (selementid) REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE;

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
