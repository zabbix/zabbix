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

#
# Table structure for table 'graphs_items'
#

CREATE TABLE graphs_items (
  gitemid		int(4)		NOT NULL auto_increment,
  graphid		int(4)		DEFAULT '0' NOT NULL,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  color			varchar(32)	DEFAULT 'Dark Green' NOT NULL,
  PRIMARY KEY (gitemid)
);

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

#
# Table structure for table 'sysmaps_hosts'
#

CREATE TABLE sysmaps_hosts (
  shostid		int(4)		NOT NULL auto_increment,
  sysmapid		int(4)		DEFAULT '0' NOT NULL,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  icon			varchar(32)	DEFAULT 'Server' NOT NULL,
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

#
# Table structure for table 'config'
#

CREATE TABLE config (
  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
  password_required	int(1)		DEFAULT '0' NOT NULL,
  alert_history		int(4)		DEFAULT '0' NOT NULL,
  alarm_history		int(4)		DEFAULT '0' NOT NULL
);

#
# Table structure for table 'groups'
#

CREATE TABLE groups (
  groupid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid),
  UNIQUE (name)
);

#
# Table structure for table 'alerts'
#

CREATE TABLE alerts (
  alertid		int(4)		NOT NULL auto_increment,
  actionid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  type			varchar(10)	DEFAULT '' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		blob		DEFAULT '' NOT NULL,
  PRIMARY KEY (alertid),
  INDEX (actionid),
  KEY clock (clock)
);

#
# Table structure for table 'actions'
#

CREATE TABLE actions (
  actionid		int(4)		NOT NULL auto_increment,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  userid		int(4)		DEFAULT '0' NOT NULL,
  good			int(4)		DEFAULT '0' NOT NULL,
  delay			int(4)		DEFAULT '0' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		blob		DEFAULT '' NOT NULL,
  nextcheck		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (actionid),
  KEY (triggerid)
);

#
# Table structure for table 'alarms'
#

CREATE TABLE alarms (
  alarmid		int(4)		NOT NULL auto_increment,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  istrue		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid),
  KEY (triggerid,clock),
  KEY (clock)
);

#
# Table structure for table 'functions'
#

CREATE TABLE functions (
  functionid int(4) NOT NULL auto_increment,
  itemid int(4) DEFAULT '0' NOT NULL,
  triggerid int(4) DEFAULT '0' NOT NULL,
  lastvalue double(16,4) DEFAULT '0.0000' NOT NULL,
  function varchar(10) DEFAULT '' NOT NULL,
  parameter int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (functionid),
  KEY triggerid (triggerid),
  KEY itemidfunctionparameter (itemid,function,parameter)
);

#
# Table structure for table 'history'
#

CREATE TABLE history (
  itemid int(4) DEFAULT '0' NOT NULL,
  clock int(4) DEFAULT '0' NOT NULL,
  value double(16,4) DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock)
);

#
# Table structure for table 'hosts'
#

CREATE TABLE hosts (
  hostid int(4) NOT NULL auto_increment,
  host varchar(64) DEFAULT '' NOT NULL,
  useip int(1) DEFAULT '1' NOT NULL,
  ip   varchar(15) DEFAULT '127.0.0.1' NOT NULL,
  port int(4) DEFAULT '0' NOT NULL,
  status int(4) DEFAULT '0' NOT NULL,
# If status=UNREACHABLE, host will not be checked until  
  disable_until int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid),
  KEY (status)
);

#
# Table structure for table 'items_template'
#

CREATE TABLE items_template (
  itemtemplateid int(4) NOT NULL,
  description varchar(255) DEFAULT '' NOT NULL,
  key_ varchar(64) DEFAULT '' NOT NULL,
  delay int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (itemtemplateid),
  UNIQUE (key_)
);

#
# Table structure for table 'triggers_template'
#

CREATE TABLE triggers_template (
  triggertemplateid int(4) NOT NULL,
  itemtemplateid int(4) NOT NULL,
  description varchar(255) DEFAULT '' NOT NULL,
  expression varchar(255) DEFAULT '' NOT NULL,
  PRIMARY KEY (triggertemplateid),
  KEY (itemtemplateid)
);

#
# Table structure for table 'items'
#

CREATE TABLE items (
	itemid		int(4) NOT NULL auto_increment,
	type		int(4) DEFAULT '0' NOT NULL,
	snmp_community	varchar(64) DEFAULT '' NOT NULL,
	snmp_oid	varchar(255) DEFAULT '' NOT NULL,
	hostid		int(4) NOT NULL,
	description	varchar(255) DEFAULT '' NOT NULL,
	key_		varchar(64) DEFAULT '' NOT NULL,
	delay		int(4) DEFAULT '0' NOT NULL,
	history		int(4) DEFAULT '0' NOT NULL,
	lastdelete	int(4) DEFAULT '0' NOT NULL,
	nextcheck	int(4) DEFAULT '0' NOT NULL,
	lastvalue	double(16,4) DEFAULT NULL,
	lastclock	int(4) DEFAULT NULL,
	prevvalue	double(16,4) DEFAULT NULL,
	status		int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(itemid),
	UNIQUE		shortname (hostid,key_),
	KEY		(hostid),
	KEY		(nextcheck),
	KEY		(status)
);

#
# Table structure for table 'media'
#

CREATE TABLE media (
	mediaid		int(4) NOT NULL auto_increment,
	userid		int(4) DEFAULT '0' NOT NULL,
	type		varchar(10) DEFAULT '' NOT NULL,
	sendto		varchar(100) DEFAULT '' NOT NULL,
	active		int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(mediaid),
	KEY		(userid)
);

#
# Table structure for table 'triggers'
#

CREATE TABLE triggers (
	triggerid	int(4) NOT NULL auto_increment,
	expression	varchar(255) DEFAULT '' NOT NULL,
	description	varchar(255) DEFAULT '' NOT NULL,
	url		varchar(255) DEFAULT '' NOT NULL,
	istrue		int(4) DEFAULT '0' NOT NULL,
	priority	int(2) DEFAULT '0' NOT NULL,
	lastchange	int(4) DEFAULT '0' NOT NULL,
	dep_level	int(2) DEFAULT '0' NOT NULL,
	comments	blob,
	PRIMARY KEY	(triggerid),
	KEY		(istrue)
);

#
# Table structure for table 'trigger_depends'
#

CREATE TABLE trigger_depends (
	triggerid_down	int(4) DEFAULT '0' NOT NULL,
	triggerid_up	int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(triggerid_down, triggerid_up),
	KEY		(triggerid_down),
	KEY		(triggerid_up)
);

#
# Table structure for table 'users'
#

CREATE TABLE users (
  userid		int(4)		NOT NULL auto_increment,
  groupid		int(4)		NOT NULL DEFAULT '0',
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		char(32)	DEFAULT '' NOT NULL,
  PRIMARY KEY (userid),
  UNIQUE (alias)
);
