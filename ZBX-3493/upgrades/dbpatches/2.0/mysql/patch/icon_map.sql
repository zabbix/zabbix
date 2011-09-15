CREATE TABLE icon_map (
	iconmapid                bigint unsigned                           NOT NULL,
	name                     varchar(64)     DEFAULT ''                NOT NULL,
	default_iconid           bigint unsigned                           NOT NULL,
	PRIMARY KEY (iconmapid)
) ENGINE=InnoDB;
CREATE INDEX icon_map_1 ON icon_map (name);
ALTER TABLE icon_map ADD CONSTRAINT c_icon_map_1 FOREIGN KEY (default_iconid) REFERENCES images (imageid);
