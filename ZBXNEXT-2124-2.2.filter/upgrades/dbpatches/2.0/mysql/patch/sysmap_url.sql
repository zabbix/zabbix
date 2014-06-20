CREATE TABLE sysmap_url (
	sysmapurlid              bigint unsigned                           NOT NULL,
	sysmapid                 bigint unsigned                           NOT NULL,
	name                     varchar(255)                              NOT NULL,
	url                      varchar(255)    DEFAULT ''                NOT NULL,
	elementtype              integer         DEFAULT '0'               NOT NULL,
	PRIMARY KEY (sysmapurlid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX sysmap_url_1 on sysmap_url (sysmapid,name);
ALTER TABLE sysmap_url ADD CONSTRAINT c_sysmap_url_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE;
