\connect zabbix

--
-- Table structure for table 'hosts'
--

CREATE TABLE hosts (
  hostid		serial,
  host			varchar(64)	DEFAULT '' 		NOT NULL,
  useip			int4		DEFAULT '0'		NOT NULL,
  ip			varchar(15)	DEFAULT '127.0.0.1'	NOT NULL,
  port			int4		DEFAULT '0'		NOT NULL,
  status		int4		DEFAULT '0'		NOT NULL,
  PRIMARY KEY (hostid)
);

CREATE INDEX hosts_status on hosts (status);

--
-- Table structure for table 'items'
--

CREATE TABLE items (
  itemid		serial,
  type			int4		NOT NULL,
  snmp_community	varchar(64)	DEFAULT '' NOT NULL,
  snmp_oid		varchar(255)	DEFAULT '' NOT NULL,
  hostid		int4		NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  key_			varchar(64)	DEFAULT '' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  history		int4		DEFAULT '0' NOT NULL,
  lastdelete		int4		DEFAULT '0' NOT NULL,
  nextcheck		int4		DEFAULT '0' NOT NULL,
  lastvalue		float8		DEFAULT NULL,
  lastclock		int4		DEFAULT NULL,
  prevvalue		float8		DEFAULT NULL,
  status		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (itemid),
  FOREIGN KEY (hostid) REFERENCES hosts
);

CREATE UNIQUE INDEX items_hostid_key on items (hostid,key_);
CREATE INDEX items_hostid on items (hostid);
CREATE INDEX items_nextcheck on items (nextcheck);
CREATE INDEX items_status on items (status);

--
-- Table structure for table 'config'
--

CREATE TABLE config (
  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
  password_required	int4		DEFAULT '0' NOT NULL,
  alert_history		int4		DEFAULT '0' NOT NULL,
  alarm_history		int4		DEFAULT '0' NOT NULL
);

--
-- Table structure for table 'groups'
--

CREATE TABLE groups (
  groupid		serial,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid)
);

--
-- Table structure for table 'triggers'
--

CREATE TABLE triggers (
  triggerid		serial,
  expression		varchar(255)	DEFAULT '' NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  istrue		int4		DEFAULT '0' NOT NULL,
  priority		int2		DEFAULT '0' NOT NULL,
  lastchange		int4		DEFAULT '0' NOT NULL,
  dep_level		int2		DEFAULT '0' NOT NULL,
  comments		text,
  PRIMARY KEY (triggerid)
);

CREATE INDEX triggers_istrue on triggers (istrue);

--
-- Table structure for table 'trigger_depends'
--

CREATE TABLE trigger_depends (
  triggerid_down	int4	DEFAULT '0' NOT NULL,
  triggerid_up		int4	DEFAULT '0' NOT NULL,
  PRIMARY KEY		(triggerid_down, triggerid_up)
);

CREATE INDEX trigger_depends_down on trigger_depends (triggerid_down);
CREATE INDEX trigger_depends_up   on trigger_depends (triggerid_up);

--
-- Table structure for table 'users'
--

CREATE TABLE users (
  userid		serial,
  groupid		int4		NOT NULL DEFAULT '0',
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		char(32)	DEFAULT '' NOT NULL,
  PRIMARY KEY (userid),
  FOREIGN KEY (groupid) REFERENCES groups
);

CREATE UNIQUE INDEX users_alias on users (alias);

--
-- Table structure for table 'actions'
--

CREATE TABLE actions (
  actionid		serial,
  triggerid		int4		DEFAULT '0' NOT NULL,
  userid		int4		DEFAULT '0' NOT NULL,
  good			int4		DEFAULT '0' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		text		DEFAULT '' NOT NULL,
  nextcheck		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (actionid),
  FOREIGN KEY (triggerid) REFERENCES triggers,
  FOREIGN KEY (userid) REFERENCES users
);

--
-- Table structure for table 'alerts'
--

CREATE TABLE alerts (
  alertid		serial,
  actionid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  type			varchar(10)	DEFAULT '' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		text		DEFAULT '' NOT NULL,
  PRIMARY KEY (alertid),
  FOREIGN KEY (actionid) REFERENCES actions
);

CREATE INDEX alerts_actionid on alerts (actionid);
CREATE INDEX alerts_clock on alerts (clock);

--
-- Table structure for table 'alarms'
--

CREATE TABLE alarms (
  alarmid		serial,
  triggerid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  istrue		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid),
  FOREIGN KEY (triggerid) REFERENCES triggers
);

CREATE INDEX alarms_triggerid_clock on alarms (triggerid, clock);
CREATE INDEX alarms_clock on alarms (clock);

--
-- Table structure for table 'functions'
--

CREATE TABLE functions (
  functionid		serial,
  itemid		int4		DEFAULT '0' NOT NULL,
  triggerid		int4		DEFAULT '0' NOT NULL,
  lastvalue		float8		DEFAULT '0.0000' NOT NULL,
  function		varchar(10)	DEFAULT '' NOT NULL,
  parameter		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (functionid),
  FOREIGN KEY (itemid) REFERENCES items,
  FOREIGN KEY (triggerid) REFERENCES triggers
);

CREATE INDEX funtions_triggerid on functions (triggerid);
CREATE INDEX functions_i_f_p on functions (itemid,function,parameter);

--
-- Table structure for table 'history'
--

CREATE TABLE history (
  itemid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			float8		DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock),
  FOREIGN KEY (itemid) REFERENCES items
);


--
-- Table structure for table 'items_template'
--

CREATE TABLE items_template (
  itemtemplateid	int4		NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  key_			varchar(64)	DEFAULT '' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (itemtemplateid)
);

CREATE UNIQUE INDEX items_template_p_k on items_template (key_);

--
-- Table structure for table 'triggers_template'
--

CREATE TABLE triggers_template (
  triggertemplateid	int4		NOT NULL,
  itemtemplateid	int4		NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  expression		varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (triggertemplateid),
  FOREIGN KEY (itemtemplateid) REFERENCES items_template
);

--
-- Table structure for table 'media'
--

CREATE TABLE media (
  mediaid		serial,
  userid		int4		DEFAULT '0' NOT NULL,
  type			varchar(10)	DEFAULT '' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  active		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (mediaid),
  FOREIGN KEY (userid) REFERENCES users
);

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
  icon			varchar(32)	DEFAULT 'Server' NOT NULL,
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
  color			varchar(32)	DEFAULT 'Dark Green' NOT NULL,
  PRIMARY KEY (gitemid),
  FOREIGN KEY (graphid) REFERENCES graphs,
  FOREIGN KEY (itemid) REFERENCES items
);

VACUUM ANALYZE;
