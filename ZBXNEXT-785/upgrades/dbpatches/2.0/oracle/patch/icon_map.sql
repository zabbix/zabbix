CREATE TABLE icon_map (
	iconmapid                number(20)                                NOT NULL,
	name                     nvarchar2(64)   DEFAULT ''                ,
	default_iconid           number(20)                                NOT NULL,
	PRIMARY KEY (iconmapid)
);
CREATE INDEX icon_map_1 ON icon_map (name);
ALTER TABLE icon_map ADD CONSTRAINT c_icon_map_1 FOREIGN KEY (default_iconid) REFERENCES images (imageid);
