CREATE TABLE escalations (
  escalationid		serial		DEFAULT '0' NOT NULL,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid)
);

CREATE UNIQUE INDEX escalations_name on escalations (name);

--
-- Table structure for table 'hosts_templates'
--

CREATE TABLE hosts_templates (
  hosttemplateid	serial,
  hostid		int4		DEFAULT '0' NOT NULL,
  templateid		int4		DEFAULT '0' NOT NULL,
  items			int2		DEFAULT '0' NOT NULL,
  triggers		int2		DEFAULT '0' NOT NULL,
  actions		int2		DEFAULT '0' NOT NULL,
  graphs		int2		DEFAULT '0' NOT NULL,
  screens		int2		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hosttemplateid)
);

CREATE UNIQUE INDEX hosts_templates_hostid_templateid on hosts_templates (hostid, templateid);

alter table hosts add available	int4	DEFAULT '0'	NOT NULL;

update hosts set available=1 where status=0;
update hosts set available=2 where status=2;

update hosts set status=0 where status=2;

alter table sysmaps add  label_type	int4	DEFAULT '0' NOT NULL;
