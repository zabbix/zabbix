CREATE TABLE sysmap_url (
	sysmapurlid              BIGINT                                NOT NULL,
	sysmapid                 BIGINT                                NOT NULL,
	name                     varchar(255)                            ,
	url                      varchar(255)  DEFAULT ''                ,
	elementtype              integer      DEFAULT '0'               NOT NULL,
	PRIMARY KEY (sysmapurlid)
)
/
CREATE UNIQUE INDEX sysmap_url_1 on sysmap_url (sysmapid,name)
/
ALTER TABLE sysmap_url ADD CONSTRAINT c_sysmap_url_1 FOREIGN KEY (sysmapid) REFERENCES sysmaps (sysmapid) ON DELETE CASCADE
/
