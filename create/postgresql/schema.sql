\connect zabbix

--
-- Table structure for table 'hosts'
--

CREATE TABLE hosts (
  hostid		serial,
  host			varchar(64)	DEFAULT '' NOT NULL,
  port			int4		DEFAULT '0' NOT NULL,
  status		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid)
);

CREATE INDEX hosts_status on hosts (status);

--
-- Table structure for table 'items'
--

CREATE TABLE items (
  itemid		serial,
  type			int4		NOT NULL,
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
  password_required	int4		DEFAULT '0' NOT NULL
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
  lastcheck		int4		DEFAULT '0' NOT NULL,
  priority		int2		DEFAULT '0' NOT NULL,
  lastchange		int4		DEFAULT '0' NOT NULL,
  comments		text,
  PRIMARY KEY (triggerid)
);

CREATE INDEX triggers_istrue on triggers (istrue);

--
-- Table structure for table 'users'
--

CREATE TABLE users (
  userid		serial,
  groupid		int4		NOT NULL DEFAULT '0',
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		varchar(64)	DEFAULT '' NOT NULL,
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
  message		varchar(255)	DEFAULT '' NOT NULL,
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
  message		varchar(255)	DEFAULT '' NOT NULL,
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

CREATE INDEX functions_itemid on functions (itemid);
CREATE INDEX funtions_triggerid on functions (triggerid);
CREATE UNIQUE INDEX functions_i_f_p on functions (itemid,function,parameter);

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

VACUUM ANALYZE;
