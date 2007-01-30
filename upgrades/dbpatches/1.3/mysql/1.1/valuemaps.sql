CREATE TABLE valuemaps (
  valuemapid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (valuemapid),
  UNIQUE (name)
) type=InnoDB;
