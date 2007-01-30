CREATE TABLE hosts_templates (
  hosttemplateid	int(4)		NOT NULL auto_increment,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  templateid		int(4)		DEFAULT '0' NOT NULL,
  items			int(1)		DEFAULT '0' NOT NULL,
  triggers		int(1)		DEFAULT '0' NOT NULL,
  graphs		int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hosttemplateid),
  UNIQUE (hostid, templateid)
) type=InnoDB;
