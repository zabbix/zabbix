--
-- Table structure for table 'sysmaps'
--

CREATE TABLE sysmaps (
  sysmapid		serial,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int4		DEFAULT '0' NOT NULL,
  height		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (sysmapid)
);

CREATE UNIQUE INDEX sysmaps_name on sysmaps (name);

--
-- Table structure for table 'sysmaps_hosts'
--

CREATE TABLE sysmaps_hosts (
  shostid		serial,
  sysmapid		int4		DEFAULT '0' NOT NULL,
  hostid		int4		DEFAULT '0' NOT NULL,
  label			varchar(128)	DEFAULT '' NOT NULL,
  x			int4		DEFAULT '0' NOT NULL,
  y			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (shostid),
  FOREIGN KEY (sysmapid) REFERENCES sysmaps,
  FOREIGN KEY (hostid) REFERENCES hosts
);

--
-- Table structure for table 'sysmaps_links'
--

CREATE TABLE sysmaps_links (
  linkid		serial,
  sysmapid		int4		DEFAULT '0' NOT NULL,
  shostid1		int4		DEFAULT '0' NOT NULL,
  shostid2		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (linkid),
  FOREIGN KEY (sysmapid) REFERENCES sysmaps,
  FOREIGN KEY (shostid1) REFERENCES sysmaps_hosts,
  FOREIGN KEY (shostid2) REFERENCES sysmaps_hosts
);

--
-- Table structure for table 'graphs'
--

CREATE TABLE graphs (
  graphid		serial,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int4		DEFAULT '0' NOT NULL,
  height		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (graphid),
  UNIQUE (name)
);

CREATE UNIQUE INDEX graphs_name on graphs (name);

--
-- Table structure for table 'graphs_items'
--

CREATE TABLE graphs_items (
  gitemid		serial,
  graphid		int4		DEFAULT '0' NOT NULL,
  itemid		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (gitemid),
  FOREIGN KEY (graphid) REFERENCES graphs,
  FOREIGN KEY (itemid) REFERENCES items
);
