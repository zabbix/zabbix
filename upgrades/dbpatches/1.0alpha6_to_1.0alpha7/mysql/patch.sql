#
# Table structure for table 'graphs_items'
#

CREATE TABLE graphs_items (
  gitemid		int(4)		NOT NULL auto_increment,
  graphid		int(4)		DEFAULT '0' NOT NULL,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (gitemid)
);
# Foreign keys

#
# Table structure for table 'graphs'
#

CREATE TABLE graphs (
  graphid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int(4)		DEFAULT '0' NOT NULL,
  height		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (graphid),
  UNIQUE (name)
);
# Foreign keys

#
# Table structure for table 'sysmaps_links'
#

CREATE TABLE sysmaps_links (
  linkid		int(4)		NOT NULL auto_increment,
  sysmapid		int(4)		DEFAULT '0' NOT NULL,
  shostid1		int(4)		DEFAULT '0' NOT NULL,
  shostid2		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (linkid)
);
# Foreign keys

#
# Table structure for table 'sysmaps_hosts'
#

CREATE TABLE sysmaps_hosts (
  shostid		int(4)		NOT NULL auto_increment,
  sysmapid		int(4)		DEFAULT '0' NOT NULL,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  label			varchar(128)	DEFAULT '' NOT NULL,
  x			int(4)		DEFAULT '0' NOT NULL,
  y			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (shostid)
);

# Foreign keys

#
# Table structure for table 'sysmaps'
#

CREATE TABLE sysmaps (
  sysmapid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int(4)		DEFAULT '0' NOT NULL,
  height		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (sysmapid),
  UNIQUE (name)
);
