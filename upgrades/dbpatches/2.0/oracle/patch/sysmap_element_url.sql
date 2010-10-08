CREATE TABLE sysmap_element_url (
  sysmapelementurlid number(20) NOT NULL,
  selementid number(20) NOT NULL,
  name nvarchar2(255) DEFAULT '' NOT NULL,
  url nvarchar2(255) DEFAULT '' NOT NULL,
  PRIMARY KEY (sysmapelementurlid)
);
CREATE UNIQUE INDEX sysmap_element_url_1 on sysmap_element_url (selementid,name);

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
  NOT url IS NULL

ALTER TABLE sysmaps_elements DROP COLUMN url
