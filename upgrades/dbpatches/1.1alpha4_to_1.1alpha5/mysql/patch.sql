CREATE TABLE escalations (
  escalationid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'hosts_templates'
--

CREATE TABLE hosts_templates (
  hosttemplateid	int(4)		NOT NULL auto_increment,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  templateid		int(4)		DEFAULT '0' NOT NULL,
  items			int(1)		DEFAULT '0' NOT NULL,
  triggers		int(1)		DEFAULT '0' NOT NULL,
  actions		int(1)		DEFAULT '0' NOT NULL,
  graphs		int(1)		DEFAULT '0' NOT NULL,
  screens		int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hosttemplateid),
  UNIQUE (hostid, templateid)
) type=InnoDB;
