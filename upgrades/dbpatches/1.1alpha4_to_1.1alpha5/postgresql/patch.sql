CREATE TABLE escalations (
  escalationid		serial		DEFAULT '0' NOT NULL,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid)
);

CREATE UNIQUE INDEX escalations_name on escalations (name);

CREATE TABLE hosts_templates (
  hostid		int4		DEFAULT '0' NOT NULL,
  templateid		int4		DEFAULT '0' NOT NULL,
  items			int2		DEFAULT '0' NOT NULL,
  triggers		int2		DEFAULT '0' NOT NULL,
  actions		int2		DEFAULT '0' NOT NULL,
  graphs		int2		DEFAULT '0' NOT NULL,
  screens		int2		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid, templateid)
);
