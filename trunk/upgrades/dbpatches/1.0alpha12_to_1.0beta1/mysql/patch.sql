alter table hosts add disable_until int(4) default '0' not null;

alter table triggers add url varchar(255) default '' not null;

#
# Table structure for table 'services'
#

CREATE TABLE services (
  serviceid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  status		int(1)		DEFAULT '0' NOT NULL,
  triggerid		int(4),
  PRIMARY KEY (serviceid)
);

#
# Table structure for table 'services_links'
#

CREATE TABLE services_links (
  serviceupid		int(4)		DEFAULT '0' NOT NULL,
  servicedownid		int(4)		DEFAULT '0' NOT NULL,
  soft			int(1)		DEFAULT '0' NOT NULL,
  KEY (serviceupid),
  KEY (servicedownid),
  UNIQUE (serviceupid,servicedownid)
);
