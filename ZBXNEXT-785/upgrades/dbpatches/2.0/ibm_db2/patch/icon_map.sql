CREATE TABLE icon_map (
	iconmapid                bigint                                    NOT NULL,
	name                     varchar(64)                               NOT NULL,
	PRIMARY KEY (iconmapid)
);
CREATE UNIQUE INDEX icon_map_1 ON icon_map (name);
