CREATE TABLE icon_map (
	iconmapid                number(20)                                NOT NULL,
	name                     nvarchar2(64)   DEFAULT ''                ,
	default_iconid           number(20)                                NOT NULL,
	PRIMARY KEY (iconmapid)
);
CREATE INDEX icon_map_1 ON icon_map (name);
ALTER TABLE icon_map ADD CONSTRAINT c_icon_map_1 FOREIGN KEY (default_iconid) REFERENCES images (imageid);

CREATE TABLE icon_mapping (
	iconmappingid            number(20)                                NOT NULL,
	iconmapid                number(20)                                NOT NULL,
	iconid                   number(20)                                NOT NULL,
	inventory_link           number(10)      DEFAULT '0'               NOT NULL,
	expression               nvarchar2(64)   DEFAULT ''                ,
	sortorder                number(10)      DEFAULT '0'               NOT NULL,
	PRIMARY KEY (iconmappingid)
);
CREATE INDEX icon_mapping_1 ON icon_mapping (iconmapid);
ALTER TABLE icon_mapping ADD CONSTRAINT c_icon_mapping_1 FOREIGN KEY (iconmapid) REFERENCES icon_map (iconmapid) ON DELETE CASCADE;
ALTER TABLE icon_mapping ADD CONSTRAINT c_icon_mapping_2 FOREIGN KEY (iconid) REFERENCES images (imageid);
