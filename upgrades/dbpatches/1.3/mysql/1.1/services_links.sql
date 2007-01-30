CREATE TABLE services_links (
  linkid		int(4)		NOT NULL auto_increment,
  serviceupid		int(4)		DEFAULT '0' NOT NULL,
  servicedownid		int(4)		DEFAULT '0' NOT NULL,
  soft			int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (linkid),
--  KEY (serviceupid),
  KEY (servicedownid),
  UNIQUE (serviceupid,servicedownid)
) type=InnoDB;
