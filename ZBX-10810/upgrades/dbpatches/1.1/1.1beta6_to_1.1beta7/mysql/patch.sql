alter table hosts add	templateid	int(4) DEFAULT '0' NOT NULL;
alter table items add	templateid	int(4) DEFAULT '0' NOT NULL;
alter table triggers add	templateid	int(4) DEFAULT '0' NOT NULL;
alter table graphs add	templateid	int(4) DEFAULT '0' NOT NULL;

alter table items add	valuemapid	int(4) DEFAULT '0' NOT NULL;

alter table actions add	status	int(1) DEFAULT '0' NOT NULL;

--
-- Table structure for table 'valuemaps'
--

CREATE TABLE valuemaps (
  valuemapid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (valuemapid),
  UNIQUE (name)
) ENGINE=InnoDB;

--
-- Table structure for table 'mapping'
--

CREATE TABLE mappings (
  mappingid		int(4)		NOT NULL auto_increment,
  valuemapid		int(4)		DEFAULT '0' NOT NULL,
  value			varchar(64)	DEFAULT '' NOT NULL,
  newvalue		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (mappingid),
  KEY valuemapid (valuemapid)
) ENGINE=InnoDB;

--
-- Table structure for table 'housekeeper'
--

CREATE TABLE housekeeper (
  housekeeperid		int(4)		NOT NULL auto_increment,
  tablename		varchar(64)	DEFAULT '' NOT NULL,
  field			varchar(64)	DEFAULT '' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (housekeeperid)
) ENGINE=InnoDB;
