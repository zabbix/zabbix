CREATE TABLE icon_mapping (
	iconmappingid            bigint                                    NOT NULL,
	iconmapid                bigint                                    NOT NULL,
	iconid                   bigint                                    NOT NULL,
	inventory_link           integer         WITH DEFAULT '0'          NOT NULL,
	expression               varchar(64)     WITH DEFAULT ''           NOT NULL,
	sortorder                integer         WITH DEFAULT '0'          NOT NULL,
	PRIMARY KEY (iconmappingid)
)
/
CREATE INDEX icon_mapping_1 ON icon_mapping (iconmapid)
/
ALTER TABLE icon_mapping ADD CONSTRAINT c_icon_mapping_1 FOREIGN KEY (iconmapid) REFERENCES icon_map (iconmapid) ON DELETE CASCADE
/
ALTER TABLE icon_mapping ADD CONSTRAINT c_icon_mapping_2 FOREIGN KEY (iconid) REFERENCES images (imageid)
/
