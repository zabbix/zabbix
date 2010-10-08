CREATE TABLE sysmap_url (
  sysmapurlid number(20) NOT NULL,
  sysmapid number(20)  NOT NULL,
  name nvarchar2(255) DEFAULT '' NOT NULL,
  url nvarchar2(255) DEFAULT '' NOT NULL,
  elementtype number(10) DEFAULT '0' NOT NULL ,
  PRIMARY KEY (sysmapurlid)
);
CREATE UNIQUE INDEX sysmap_url_1 on sysmap_url (sysmapid,name);
ALTER TABLE sysmap_url ADD CONSTRAINT c_sysmapid_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE;
