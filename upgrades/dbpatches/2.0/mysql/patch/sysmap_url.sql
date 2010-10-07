CREATE TABLE IF NOT EXISTS sysmap_url (
  sysmapurlid bigint(20) unsigned NOT NULL,
  sysmapid bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  url varchar(255) NOT NULL DEFAULT '',
  elementtype int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (sysmapurlid),
  UNIQUE KEY sysmap_url_1 (sysmapid,name)
) ENGINE=InnoDB;

ALTER TABLE sysmap_url ADD CONSTRAINT c_sysmapid_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE;
