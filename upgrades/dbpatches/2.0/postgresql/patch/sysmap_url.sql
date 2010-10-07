CREATE TABLE sysmap_url (
  sysmapurlid bigint NOT NULL,
  sysmapid bigint  NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  elementtype int NOT NULL DEFAULT '0',
  PRIMARY KEY (sysmapurlid)
) with OIDS;
CREATE UNIQUE INDEX sysmap_url_1 on sysmap_url (sysmapid,name);

ALTER TABLE ONLY sysmap_url ADD CONSTRAINT c_sysmapid_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE;
