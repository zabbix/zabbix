CREATE TABLE graph_theme (
      graphthemeid            bigint unsigned         DEFAULT '0'     NOT NULL,
      description             varchar(64)             DEFAULT ''      NOT NULL,
      theme           varchar(64)             DEFAULT ''      NOT NULL,
      backgroundcolor         varchar(6)              DEFAULT 'F0F0F0'        NOT NULL,
      graphcolor              varchar(6)              DEFAULT 'FFFFFF'        NOT NULL,
      graphbordercolor                varchar(6)              DEFAULT '222222'        NOT NULL,
      gridcolor               varchar(6)              DEFAULT 'CCCCCC'        NOT NULL,
      maingridcolor           varchar(6)              DEFAULT 'AAAAAA'        NOT NULL,
      gridbordercolor         varchar(6)              DEFAULT '000000'        NOT NULL,
      textcolor               varchar(6)              DEFAULT '202020'        NOT NULL,
      highlightcolor          varchar(6)              DEFAULT 'AA4444'        NOT NULL,
      leftpercentilecolor             varchar(6)              DEFAULT '11CC11'        NOT NULL,
      rightpercentilecolor            varchar(6)              DEFAULT 'CC1111'        NOT NULL,
      noneworktimecolor               varchar(6)              DEFAULT 'CCCCCC'        NOT NULL,
      gridview                integer         DEFAULT 1       NOT NULL,
      legendview              integer         DEFAULT 1       NOT NULL,
      PRIMARY KEY (graphthemeid)
) type=InnoDB;
CREATE INDEX graph_theme_1 on graph_theme (description);
CREATE INDEX graph_theme_2 on graph_theme (theme);
