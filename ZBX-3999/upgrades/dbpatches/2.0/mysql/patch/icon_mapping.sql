CREATE TABLE icon_mapping (
	iconmappingid            bigint unsigned                           NOT NULL,
	iconmapid                bigint unsigned                           NOT NULL,
	iconid                   bigint unsigned                           NOT NULL,
	inventory_link           integer         DEFAULT '0'               NOT NULL,
	expression               varchar(64)     DEFAULT ''                NOT NULL,
	sortorder                integer         DEFAULT '0'               NOT NULL,
	PRIMARY KEY (iconmappingid)
) ENGINE=InnoDB;
CREATE INDEX icon_mapping_1 ON icon_mapping (iconmapid);
ALTER TABLE icon_mapping ADD CONSTRAINT c_icon_mapping_1 FOREIGN KEY (iconmapid) REFERENCES icon_map (iconmapid) ON DELETE CASCADE;
ALTER TABLE icon_mapping ADD CONSTRAINT c_icon_mapping_2 FOREIGN KEY (iconid) REFERENCES images (imageid);
