CREATE TABLE escalations (
  escalationid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid),
  UNIQUE (name)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

alter table hosts add available	int(4)		DEFAULT '0' NOT NULL;
update hosts set available=1 where status=0;
update hosts set available=2 where status=2;

update hosts set status=0 where status=2;

alter table sysmaps add  label_type	int(4)	DEFAULT '0' NOT NULL;
