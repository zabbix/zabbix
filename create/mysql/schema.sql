-- 
-- ZABBIX
-- Copyright (C) 2000-2005 SIA Zabbix
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
--


--
-- Table structure for table 'services'
--

CREATE TABLE services (
  serviceid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  status		int(1)		DEFAULT '0' NOT NULL,
  algorithm		int(1)		DEFAULT '0' NOT NULL,
  triggerid		int(4),
  showsla		int(1)		DEFAULT '0' NOT NULL,
  goodsla		double(5,2)	DEFAULT '99.9' NOT NULL,
  sortorder		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (serviceid)
) type=InnoDB;

--
-- Table structure for table 'services_links'
--

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

--
-- Table structure for table 'graphs_items'
--

CREATE TABLE graphs_items (
  gitemid		int(4)		NOT NULL auto_increment,
  graphid		int(4)		DEFAULT '0' NOT NULL,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  drawtype		int(4)		DEFAULT '0' NOT NULL,
  sortorder		int(4)		DEFAULT '0' NOT NULL,
  color			varchar(32)	DEFAULT 'Dark Green' NOT NULL,
  yaxisside		int(1)		DEFAULT '1' NOT NULL,
  PRIMARY KEY (gitemid)
) type=InnoDB;

--
-- Table structure for table 'graphs'
--

CREATE TABLE graphs (
  graphid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int(4)		DEFAULT '0' NOT NULL,
  height		int(4)		DEFAULT '0' NOT NULL,
  yaxistype		int(1)		DEFAULT '0' NOT NULL,
  yaxismin		double(16,4)	DEFAULT '0' NOT NULL,
  yaxismax		double(16,4)	DEFAULT '0' NOT NULL,
  templateid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (graphid),
  KEY (name)
) type=InnoDB;

--
-- Table structure for table 'sysmaps_links'
--

CREATE TABLE sysmaps_links (
  linkid		int(4)		NOT NULL auto_increment,
  sysmapid		int(4)		DEFAULT '0' NOT NULL,
  selementid1		int(4)		DEFAULT '0' NOT NULL,
  selementid2		int(4)		DEFAULT '0' NOT NULL,
 -- may be NULL 
  triggerid		int(4),
  drawtype_off		int(4)		DEFAULT '0' NOT NULL,
  color_off		varchar(32)	DEFAULT 'Black' NOT NULL,
  drawtype_on		int(4)		DEFAULT '0' NOT NULL,
  color_on		varchar(32)	DEFAULT 'Red' NOT NULL,
  PRIMARY KEY (linkid)
) type=InnoDB;

--
-- Table structure for table 'sysmaps_elements'
--

CREATE TABLE sysmaps_elements (
  selementid		int(4)		NOT NULL auto_increment,
  sysmapid		int(4)		DEFAULT '0' NOT NULL,
  elementid		int(4)		DEFAULT '0' NOT NULL,
  elementtype		int(4)		DEFAULT '0' NOT NULL,
  icon			varchar(32)	DEFAULT 'Server' NOT NULL,
  icon_on		varchar(32)	DEFAULT 'Server' NOT NULL,
  label			varchar(128)	DEFAULT '' NOT NULL,
  label_location	int(1)		DEFAULT NULL,
  x			int(4)		DEFAULT '0' NOT NULL,
  y			int(4)		DEFAULT '0' NOT NULL,
  url			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (selementid)
) type=InnoDB;

--
-- Table structure for table 'sysmaps'
--

CREATE TABLE sysmaps (
  sysmapid		int(4)		NOT NULL auto_increment,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int(4)		DEFAULT '0' NOT NULL,
  height		int(4)		DEFAULT '0' NOT NULL,
  background		varchar(64)	DEFAULT '' NOT NULL,
  label_type		int(4)		DEFAULT '0' NOT NULL,
  label_location	int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (sysmapid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'config'
--

CREATE TABLE config (
--  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
--  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
--  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
--  password_required	int(1)		DEFAULT '0' NOT NULL,
  alert_history		int(4)		DEFAULT '0' NOT NULL,
  alarm_history		int(4)		DEFAULT '0' NOT NULL,
  refresh_unsupported	int(4)		DEFAULT '0' NOT NULL
) type=InnoDB;

--
-- Table structure for table 'groups'
--

CREATE TABLE groups (
  groupid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'hosts_groups'
--

CREATE TABLE hosts_groups (
  hostid		int(4)		DEFAULT '0' NOT NULL,
  groupid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid,groupid)
) type=InnoDB;

--
-- Table structure for table 'alerts'
--

CREATE TABLE alerts (
  alertid		int(4)		NOT NULL auto_increment,
  actionid		int(4)		DEFAULT '0' NOT NULL,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  userid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
--  type		varchar(10)	DEFAULT '' NOT NULL,
  mediatypeid		int(4)		DEFAULT '0' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		blob		DEFAULT '' NOT NULL,
  status		int(4)		DEFAULT '0' NOT NULL,
  retries		int(4)		DEFAULT '0' NOT NULL,
  error			varchar(128)	DEFAULT '' NOT NULL,
  repeats		int(4)		DEFAULT '0' NOT NULL,
  maxrepeats		int(4)		DEFAULT '0' NOT NULL,
  nextcheck		int(4)		DEFAULT '0' NOT NULL,
  delay			int(4)		DEFAULT '0' NOT NULL,

  PRIMARY KEY (alertid),
  INDEX (actionid),
  KEY clock (clock),
  KEY triggerid (triggerid),
  KEY status_retries (status, retries),
  KEY mediatypeid (mediatypeid),
  KEY userid (userid)
) type=InnoDB;

--
-- Table structure for table 'actions'
--

CREATE TABLE actions (
  actionid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  delay			int(4)		DEFAULT '0' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		blob		DEFAULT '' NOT NULL,
  nextcheck		int(4)		DEFAULT '0' NOT NULL,
  recipient		int(1)		DEFAULT '0' NOT NULL,
  maxrepeats		int(4)		DEFAULT '0' NOT NULL,
  repeatdelay		int(4)		DEFAULT '600' NOT NULL,
  source		int(1)		DEFAULT '0' NOT NULL,
  actiontype		int(1)		DEFAULT '0' NOT NULL,
  status		int(1)		DEFAULT '0' NOT NULL,
  scripts		blob		DEFAULT '' NOT NULL,
  PRIMARY KEY (actionid)
) type=InnoDB;

--
-- Table structure for table 'conditions'
--

CREATE TABLE conditions (
  conditionid		int(4)		NOT NULL auto_increment,
  actionid		int(4)		DEFAULT '0' NOT NULL,
  conditiontype		int(4)		DEFAULT '0' NOT NULL,
  operator		int(1)		DEFAULT '0' NOT NULL,
  value			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (conditionid),
  KEY (actionid)
) type=InnoDB;

--
-- Table structure for table 'alarms'
--

CREATE TABLE alarms (
  alarmid		int(4)		NOT NULL auto_increment,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  acknowledged		int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid),
  KEY (triggerid,clock),
  KEY (clock)
) type=InnoDB;

--
-- Table structure for table 'functions'
--

CREATE TABLE functions (
  functionid		int(4)		NOT NULL auto_increment,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  lastvalue		varchar(255),
  function		varchar(10)	DEFAULT '' NOT NULL,
  parameter		varchar(255)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (functionid),
  KEY triggerid (triggerid),
  KEY itemidfunctionparameter (itemid,function,parameter)
) type=InnoDB;

--
-- Table structure for table 'history'
--

CREATE TABLE history (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			double(16,4)	DEFAULT '0.0000' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;

--
-- Table structure for table 'history_uint'
--

CREATE TABLE history_uint (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			bigint unsigned	DEFAULT '0' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;

--
-- Table structure for table 'history_str'
--

CREATE TABLE history_str (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			varchar(255)	DEFAULT '' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;

--
-- Table structure for table 'hosts'
--

CREATE TABLE hosts (
	hostid		int(4)		NOT NULL auto_increment,
	host		varchar(64)	DEFAULT '' NOT NULL,
	useip		int(1)		DEFAULT '1' NOT NULL,
	ip		varchar(15)	DEFAULT '127.0.0.1' NOT NULL,
	port		int(4)		DEFAULT '0' NOT NULL,
	status		int(4)		DEFAULT '0' NOT NULL,
-- If status=UNREACHABLE, host will not be checked until this time
	disable_until	int(4)		DEFAULT '0' NOT NULL,
	error		varchar(128)	DEFAULT '' NOT NULL,
	available	int(4)		DEFAULT '0' NOT NULL,
	errors_from	int(4)		DEFAULT '0' NOT NULL,
	templateid	int(4)		DEFAULT '0' NOT NULL,
	PRIMARY KEY	(hostid),
	UNIQUE		(host),
	KEY		(status)
) type=InnoDB;

--
-- Table structure for table 'items'
--

CREATE TABLE items (
	itemid		int(4) NOT NULL auto_increment,
	type		int(4) DEFAULT '0' NOT NULL,
	snmp_community	varchar(64) DEFAULT '' NOT NULL,
	snmp_oid	varchar(255) DEFAULT '' NOT NULL,
	snmp_port	int(4) DEFAULT '161' NOT NULL,
	hostid		int(4) NOT NULL,
	description	varchar(255) DEFAULT '' NOT NULL,
	key_		varchar(64) DEFAULT '' NOT NULL,
	delay		int(4) DEFAULT '0' NOT NULL,
	history		int(4) DEFAULT '90' NOT NULL,
	trends		int(4) DEFAULT '365' NOT NULL,
-- lastdelete is not longer required
--	lastdelete	int(4) DEFAULT '0' NOT NULL,
	nextcheck	int(4) DEFAULT '0' NOT NULL,
	lastvalue	varchar(255) DEFAULT NULL,
	lastclock	int(4) DEFAULT NULL,
	prevvalue	varchar(255) DEFAULT NULL,
	status		int(4) DEFAULT '0' NOT NULL,
	value_type	int(4) DEFAULT '0' NOT NULL,
	trapper_hosts	varchar(255) DEFAULT '' NOT NULL,
	units		varchar(10)	DEFAULT '' NOT NULL,
	multiplier	int(4)	DEFAULT '0' NOT NULL,
	delta		int(1)  DEFAULT '0' NOT NULL,
	prevorgvalue	double(16,4)  DEFAULT NULL,
	snmpv3_securityname	varchar(64) DEFAULT '' NOT NULL,
	snmpv3_securitylevel	int(1) DEFAULT '0' NOT NULL,
	snmpv3_authpassphrase	varchar(64) DEFAULT '' NOT NULL,
	snmpv3_privpassphrase	varchar(64) DEFAULT '' NOT NULL,

	formula		varchar(255) DEFAULT '0' NOT NULL,
	error		varchar(128) DEFAULT '' NOT NULL,

	lastlogsize	int(4) DEFAULT '0' NOT NULL,
	logtimefmt	varchar(64) DEFAULT '' NOT NULL,
	templateid	int(4) DEFAULT '0' NOT NULL,
	valuemapid	int(4) DEFAULT '0' NOT NULL,

	PRIMARY KEY	(itemid),
	UNIQUE		shortname (hostid,key_),
--	KEY		(hostid),
	KEY		(nextcheck),
	KEY		(status)
) type=InnoDB;

--
-- Table structure for table 'media'
--

CREATE TABLE media (
	mediaid		int(4) NOT NULL auto_increment,
	userid		int(4) DEFAULT '0' NOT NULL,
--	type		varchar(10) DEFAULT '' NOT NULL,
	mediatypeid	int(4) DEFAULT '0' NOT NULL,
	sendto		varchar(100) DEFAULT '' NOT NULL,
	active		int(4) DEFAULT '0' NOT NULL,
	severity	int(4) DEFAULT '63' NOT NULL,
	period		varchar(100) DEFAULT '1-7,00:00-23:59' NOT NULL,
	PRIMARY KEY	(mediaid),
	KEY		(userid),
	KEY		(mediatypeid)
) type=InnoDB;

--
-- Table structure for table 'media'
--

CREATE TABLE media_type (
	mediatypeid	int(4) NOT NULL auto_increment,
	type		int(4)		DEFAULT '0' NOT NULL,
	description	varchar(100)	DEFAULT '' NOT NULL,
	smtp_server	varchar(255)	DEFAULT '' NOT NULL,
	smtp_helo	varchar(255)	DEFAULT '' NOT NULL,
	smtp_email	varchar(255)	DEFAULT '' NOT NULL,
	exec_path	varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY	(mediatypeid)
) type=InnoDB;

--
-- Table structure for table 'triggers'
--

CREATE TABLE triggers (
	triggerid	int(4) NOT NULL auto_increment,
	expression	varchar(255) DEFAULT '' NOT NULL,
	description	varchar(255) DEFAULT '' NOT NULL,
	url		varchar(255) DEFAULT '' NOT NULL,
	status		int(4) DEFAULT '0' NOT NULL,
	value		int(4) DEFAULT '0' NOT NULL,
	priority	int(2) DEFAULT '0' NOT NULL,
	lastchange	int(4) DEFAULT '0' NOT NULL,
	dep_level	int(2) DEFAULT '0' NOT NULL,
	comments	blob,
	error		varchar(128) DEFAULT '' NOT NULL,
	templateid	int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(triggerid),
	KEY		(status),
	KEY		(value)
) type=InnoDB;

--
-- Table structure for table 'trigger_depends'
--

CREATE TABLE trigger_depends (
	triggerid_down	int(4) DEFAULT '0' NOT NULL,
	triggerid_up	int(4) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(triggerid_down, triggerid_up),
--	KEY		(triggerid_down),
	KEY		(triggerid_up)
) type=InnoDB;

--
-- Table structure for table 'users'
--

CREATE TABLE users (
  userid		int(4)		NOT NULL auto_increment,
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		char(32)	DEFAULT '' NOT NULL,
  url			varchar(255)	DEFAULT '' NOT NULL,
  autologout		int(4)		DEFAULT '900' NOT NULL,
  lang			varchar(5)	DEFAULT 'en_gb' NOT NULL,
  refresh		int(4)		DEFAULT '30' NOT NULL,
  PRIMARY KEY (userid),
  UNIQUE (alias)
) type=InnoDB;

--
-- Table structure for table 'audit'
--

CREATE TABLE audit (
  auditid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  action		int(4)		DEFAULT '0' NOT NULL,
  resource		int(4)		DEFAULT '0' NOT NULL,
  details		varchar(128)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (auditid),
  KEY (userid,clock),
  KEY (clock)
) type=InnoDB;

--
-- Table structure for table 'sessions'
--

CREATE TABLE sessions (
  sessionid		varchar(32)	NOT NULL DEFAULT '',
  userid		int(4)		NOT NULL DEFAULT '0',
  lastaccess		int(4)		NOT NULL DEFAULT '0',
  PRIMARY KEY (sessionid)
) type=InnoDB;

--
-- Table structure for table 'rights'
--

CREATE TABLE rights (
  rightid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  name			char(255)	DEFAULT '' NOT NULL,
  permission		char(1)		DEFAULT '' NOT NULL,
  id			int(4),
  PRIMARY KEY (rightid),
  KEY (userid)
) type=InnoDB;

--
-- Table structure for table 'problems'
--

-- CREATE TABLE problems (
--   problemid		int(4)		NOT NULL auto_increment,
--   userid		int(4)		DEFAULT '0' NOT NULL,
--   triggerid		int(4),
--   lastupdate		int(4)		DEFAULT '0' NOT NULL,
--   clock			int(4)		DEFAULT '0' NOT NULL,
--   status		int(1)		DEFAULT '0' NOT NULL,
--   description		varchar(255)	DEFAULT '' NOT NULL,
--   categoryid		int(4),
  -- priority		int(1)		DEFAULT '0' NOT NULL,
--   PRIMARY KEY (problemid),
--   KEY (status),
--   KEY (categoryid),
--   KEY (priority)
-- ) type=InnoDB;

--
-- Table structure for table 'categories'
--

-- CREATE TABLE categories (
--   categoryid		int(4)		NOT NULL auto_increment,
--   descripion		varchar(64)	DEFAULT '' NOT NULL,
--   PRIMARY KEY (categoryid)
-- ) type=InnoDB;

--
-- Table structure for table 'problems_categories'
--

-- CREATE TABLE problems_comments (
--   commentid		int(4)		NOT NULL auto_increment,
--   problemid		int(4)		DEFAULT '0' NOT NULL,
--   clock			int(4),
--   status_before		int(1)		DEFAULT '0' NOT NULL,
--   status_after		int(1)		DEFAULT '0' NOT NULL,
--   comment		blob,
--   PRIMARY KEY (commentid),
--   KEY (problemid,clock)
-- ) type=InnoDB;

--
-- Table structure for table 'service_alarms'
--

CREATE TABLE service_alarms (
  servicealarmid	int(4)		NOT NULL auto_increment,
  serviceid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (servicealarmid),
  KEY (serviceid,clock),
  KEY (clock)
) type=InnoDB;

--
-- Table structure for table 'profiles'
--

CREATE TABLE profiles (
  profileid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  idx			varchar(64)	DEFAULT '' NOT NULL,
  value			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (profileid),
--  KEY (userid),
  UNIQUE (userid,idx)
) type=InnoDB;

--
-- Table structure for table 'screens'
--

CREATE TABLE screens (
  screenid		int(4)		NOT NULL auto_increment,
  name			varchar(255)	DEFAULT 'Screen' NOT NULL,
  cols			int(4)		DEFAULT '1' NOT NULL,
  rows			int(4)		DEFAULT '1' NOT NULL,
  PRIMARY KEY  (screenid)
) TYPE=InnoDB;

--
-- Table structure for table 'screens_items'
--

CREATE TABLE screens_items (
	screenitemid	int(4)		NOT NULL auto_increment,
	screenid	int(4)		DEFAULT '0' NOT NULL,
	resource	int(4)		DEFAULT '0' NOT NULL,
	resourceid	int(4)		DEFAULT '0' NOT NULL,
	width		int(4)		DEFAULT '320' NOT NULL,
	height		int(4)		DEFAULT '200' NOT NULL,
	x		int(4)		DEFAULT '0' NOT NULL,
	y		int(4)		DEFAULT '0' NOT NULL,
	colspan		int(4)		DEFAULT '0' NOT NULL,
	rowspan		int(4)		DEFAULT '0' NOT NULL,
	elements	int(4)		DEFAULT '25' NOT NULL,
	valign		int(2)		DEFAULT '0' NOT NULL,
	halign		int(2)		DEFAULT '0' NOT NULL,
	style		int(4)		DEFAULT '0' NOT NULL,
	url		varchar(255)	DEFAULT '' NOT NULL,
	  PRIMARY KEY  (screenitemid)
) TYPE=InnoDB;

--
-- Table structure for table 'stats'
--

CREATE TABLE stats (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  year			int(4)		DEFAULT '0' NOT NULL,
  month			int(4)		DEFAULT '0' NOT NULL,
  day			int(4)		DEFAULT '0' NOT NULL,
  hour			int(4)		DEFAULT '0' NOT NULL,
  value_max		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_min		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_avg		double(16,4)	DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,year,month,day,hour)
) type=InnoDB;

--
-- Table structure for table 'usrgrp'
--

CREATE TABLE usrgrp (
  usrgrpid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (usrgrpid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'users_groups'
--

CREATE TABLE users_groups (
  usrgrpid		int(4)		DEFAULT '0' NOT NULL,
  userid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (usrgrpid,userid)
) type=InnoDB;

--
-- Table structure for table 'trends'
--

CREATE TABLE trends (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  num			int(2)		DEFAULT '0' NOT NULL,
  value_min		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_avg		double(16,4)	DEFAULT '0.0000' NOT NULL,
  value_max		double(16,4)	DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock)
) type=InnoDB;

--
-- Table structure for table 'images'
--

CREATE TABLE images (
  imageid		int(4)		NOT NULL auto_increment,
  imagetype		int(4)		DEFAULT '0' NOT NULL,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  image			longblob	DEFAULT '' NOT NULL,
  PRIMARY KEY (imageid),
  UNIQUE (imagetype, name)
) type=InnoDB;

--
-- Table structure for table 'hosts_templates'
--

CREATE TABLE hosts_templates (
  hosttemplateid	int(4)		NOT NULL auto_increment,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  templateid		int(4)		DEFAULT '0' NOT NULL,
  items			int(1)		DEFAULT '0' NOT NULL,
  triggers		int(1)		DEFAULT '0' NOT NULL,
  graphs		int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hosttemplateid),
  UNIQUE (hostid, templateid)
) type=InnoDB;

--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
  id			int(4)		NOT NULL auto_increment,
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  timestamp		int(4)		DEFAULT '0' NOT NULL,
  source		varchar(64)	DEFAULT '' NOT NULL,
  severity		int(4)		DEFAULT '0' NOT NULL,
  value			text		DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  KEY itemidclock (itemid, clock)
) type=InnoDB;

--
-- Table structure for table 'hosts_profiles'
--

CREATE TABLE hosts_profiles (
  hostid		int(4)		DEFAULT '0' NOT NULL,
  devicetype		varchar(64)	DEFAULT '' NOT NULL,
  name			varchar(64)	DEFAULT '' NOT NULL,
  os			varchar(64)	DEFAULT '' NOT NULL,
  serialno		varchar(64)	DEFAULT '' NOT NULL,
  tag			varchar(64)	DEFAULT '' NOT NULL,
  macaddress		varchar(64)	DEFAULT '' NOT NULL,
  hardware		blob		DEFAULT '' NOT NULL,
  software		blob		DEFAULT '' NOT NULL,
  contact		blob		DEFAULT '' NOT NULL,
  location		blob		DEFAULT '' NOT NULL,
  notes			blob		DEFAULT '' NOT NULL,
  PRIMARY KEY (hostid)
) type=InnoDB;

--
-- Table structure for table 'autoreg'
--

CREATE TABLE autoreg (
  id			int(4)		NOT NULL auto_increment,
  priority		int(4)		DEFAULT '0' NOT NULL,
  pattern		varchar(255)	DEFAULT '' NOT NULL,
  hostid		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (id)
) type=InnoDB;

--
-- Table structure for table 'valuemaps'
--

CREATE TABLE valuemaps (
  valuemapid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (valuemapid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'mapping'
--

CREATE TABLE mappings (
  mappingid		int(4)		NOT NULL auto_increment,
  valuemapid		int(4)		DEFAULT '0' NOT NULL,
  value			varchar(64)	DEFAULT '' NOT NULL,
  newvalue		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (mappingid),
  KEY valuemapid (valuemapid)
) type=InnoDB;

--
-- Table structure for table 'housekeeper'
--

CREATE TABLE housekeeper (
  housekeeperid		int(4)		NOT NULL auto_increment,
  tablename		varchar(64)	DEFAULT '' NOT NULL,
  field			varchar(64)	DEFAULT '' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (housekeeperid)
) type=InnoDB;

--
-- Table structure for table 'acknowledges'
--

CREATE TABLE acknowledges (
	acknowledgeid		int(4)		NOT NULL auto_increment,
	userid			int(4)		DEFAULT '0' NOT NULL,
	alarmid			int(4)		DEFAULT '0' NOT NULL,
	clock			int(4)		DEFAULT '0' NOT NULL,
	message			varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (acknowledgeid),
	KEY userid (userid),
	KEY alarmid (alarmid),
	KEY clock (clock)
) type=InnoDB;
