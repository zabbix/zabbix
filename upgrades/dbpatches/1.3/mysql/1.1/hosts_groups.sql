CREATE TABLE hosts_groups (
  hostid		int(4)		DEFAULT '0' NOT NULL,
  groupid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid,groupid)
) type=InnoDB;
