CREATE TABLE mappings (
  mappingid		int(4)		NOT NULL auto_increment,
  valuemapid		int(4)		DEFAULT '0' NOT NULL,
  value			varchar(64)	DEFAULT '' NOT NULL,
  newvalue		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (mappingid),
  KEY valuemapid (valuemapid)
) type=InnoDB;
