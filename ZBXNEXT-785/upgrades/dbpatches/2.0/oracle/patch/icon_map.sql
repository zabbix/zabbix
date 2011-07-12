CREATE TABLE icon_map (
        iconmapid                number(20)                                NOT NULL,
        name                     nvarchar2(64)                             ,
        PRIMARY KEY (iconmapid)
);
CREATE UNIQUE INDEX icon_map_1 ON icon_map (name);
