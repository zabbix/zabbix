CREATE TABLE icon_map (
        iconmapid                bigint unsigned                           NOT NULL,
        name                     varchar(64)                               NOT NULL,
        PRIMARY KEY (iconmapid)
) ENGINE=InnoDB;
CREATE UNIQUE INDEX icon_map_1 ON icon_map (name);
