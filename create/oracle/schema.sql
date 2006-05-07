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
-- Table structure for table 'config'
--

CREATE TABLE config (
  alert_history		number(10)	DEFAULT '0' NOT NULL,
  alarm_history		number(10)	DEFAULT '0' NOT NULL,
  refresh_unsupported	number(10)	DEFAULT '0' NOT NULL,
  work_period		varchar2(100)	DEFAULT '1-5,00:00-24:00' NOT NULL
);

--
-- Table structure for table 'history'
--

CREATE TABLE history (
  itemid		number(10)	DEFAULT '0' NOT NULL,
  clock			number(10)	DEFAULT '0' NOT NULL,
  value			number(16,4)	DEFAULT '0.0000' NOT NULL
);

CREATE INDEX history_itemidclock on history (itemid, clock);


--
-- Table structure for table 'services'
--

CREATE TABLE services (
  serviceid		number(10)		NOT NULL,
  name			varchar2(128)	DEFAULT '' NOT NULL,
  status		number(3)		DEFAULT '0' NOT NULL,
  algorithm		number(3)		DEFAULT '0' NOT NULL,
  triggerid		number(10),
  showsla		number(3)		DEFAULT '0' NOT NULL,
  goodsla		number(5,2)	DEFAULT '99.9' NOT NULL,
  sortorder		number(10)		DEFAULT '0' NOT NULL,
  CONSTRAINT services_pk PRIMARY KEY (serviceid)
);

create sequence services_serviceid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger services_trigger
before insert on services
for each row
begin
	select service_serviceid.nextval into :new.serviceid from dual;
end;
/


--
-- Table structure for table 'services_links'
--

CREATE TABLE services_links (
  linkid		number(10)		NOT NULL auto_increment,
  serviceupid		number(10)		DEFAULT '0' NOT NULL,
  servicedownid		number(10)		DEFAULT '0' NOT NULL,
  soft			number(3)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (linkid),
--  KEY (serviceupid),
  KEY (servicedownid),
  UNIQUE (serviceupid,servicedownid)
) type=InnoDB;

--
-- Table structure for table 'graphs_items'
--

CREATE TABLE graphs_items (
  gitemid		number(10)		NOT NULL auto_increment,
  graphid		number(10)		DEFAULT '0' NOT NULL,
  itemid		number(10)		DEFAULT '0' NOT NULL,
  drawtype		number(10)		DEFAULT '0' NOT NULL,
  sortorder		number(10)		DEFAULT '0' NOT NULL,
  color			varchar2(32)	DEFAULT 'Dark Green' NOT NULL,
  yaxisside		number(3)		DEFAULT '1' NOT NULL,
  PRIMARY KEY (gitemid)
) type=InnoDB;

--
-- Table structure for table 'graphs'
--

CREATE TABLE graphs (
  graphid		number(10)		NOT NULL auto_increment,
  name			varchar2(128)	DEFAULT '' NOT NULL,
  width			number(10)		DEFAULT '0' NOT NULL,
  height		number(10)		DEFAULT '0' NOT NULL,
  yaxistype		number(3)		DEFAULT '0' NOT NULL,
  yaxismin		number(16,4)	DEFAULT '0' NOT NULL,
  yaxismax		number(16,4)	DEFAULT '0' NOT NULL,
  templateid		number(10)		DEFAULT '0' NOT NULL,
  show_work_period	number(3)		DEFAULT '1' NOT NULL,
  show_triggers		number(3)		DEFAULT '1' NOT NULL,
  PRIMARY KEY (graphid),
  KEY (name)
) type=InnoDB;

--
-- Table structure for table 'sysmaps_links'
--

CREATE TABLE sysmaps_links (
  linkid		number(10)		NOT NULL auto_increment,
  sysmapid		number(10)		DEFAULT '0' NOT NULL,
  selementid1		number(10)		DEFAULT '0' NOT NULL,
  selementid2		number(10)		DEFAULT '0' NOT NULL,
 -- may be NULL 
  triggerid		number(10),
  drawtype_off		number(10)		DEFAULT '0' NOT NULL,
  color_off		varchar2(32)	DEFAULT 'Black' NOT NULL,
  drawtype_on		number(10)		DEFAULT '0' NOT NULL,
  color_on		varchar2(32)	DEFAULT 'Red' NOT NULL,
  PRIMARY KEY (linkid)
) type=InnoDB;

--
-- Table structure for table 'sysmaps_elements'
--

CREATE TABLE sysmaps_elements (
  selementid		number(10)		NOT NULL auto_increment,
  sysmapid		number(10)		DEFAULT '0' NOT NULL,
  elementid		number(10)		DEFAULT '0' NOT NULL,
  elementtype		number(10)		DEFAULT '0' NOT NULL,
  icon			varchar2(32)	DEFAULT 'Server' NOT NULL,
  icon_on		varchar2(32)	DEFAULT 'Server' NOT NULL,
  label			varchar2(128)	DEFAULT '' NOT NULL,
  label_location	number(3)		DEFAULT NULL,
  x			number(10)		DEFAULT '0' NOT NULL,
  y			number(10)		DEFAULT '0' NOT NULL,
  url			varchar2(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (selementid)
) type=InnoDB;

--
-- Table structure for table 'sysmaps'
--

CREATE TABLE sysmaps (
  sysmapid		number(10)		NOT NULL auto_increment,
  name			varchar2(128)	DEFAULT '' NOT NULL,
  width			number(10)		DEFAULT '0' NOT NULL,
  height		number(10)		DEFAULT '0' NOT NULL,
  background		varchar2(64)	DEFAULT '' NOT NULL,
  label_type		number(10)		DEFAULT '0' NOT NULL,
  label_location	number(3)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (sysmapid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'groups'
--

CREATE TABLE groups (
  groupid		number(10)		NOT NULL auto_increment,
  name			varchar2(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'hosts_groups'
--

CREATE TABLE hosts_groups (
  hostid		number(10)		DEFAULT '0' NOT NULL,
  groupid		number(10)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid,groupid)
) type=InnoDB;

--
-- Table structure for table 'alerts'
--

CREATE TABLE alerts (
	alertid		number(10)		NOT NULL,
	actionid	number(10)		DEFAULT '0' NOT NULL,
	triggerid	number(10)		DEFAULT '0' NOT NULL,
	userid		number(10)		DEFAULT '0' NOT NULL,
	clock		number(10)		DEFAULT '0' NOT NULL,
	mediatypeid	number(10)		DEFAULT '0' NOT NULL,
	sendto		varchar2(100)	DEFAULT '' NOT NULL,
	subject		varchar2(255)	DEFAULT '' NOT NULL,
	message		varchar2(2048)		DEFAULT '' NOT NULL,
	status		number(10)		DEFAULT '0' NOT NULL,
	retries		number(10)		DEFAULT '0' NOT NULL,
	error		varchar2(128)	DEFAULT '' NOT NULL,
	repeats		number(10)		DEFAULT '0' NOT NULL,
	maxrepeats	number(10)		DEFAULT '0' NOT NULL,
	nextcheck	number(10)		DEFAULT '0' NOT NULL,
	delay		number(10)		DEFAULT '0' NOT NULL,
	CONSTRAINT 	alerts_pk PRIMARY KEY (alertid)
);

CREATE INDEX alerts_actionid on alerts (actionid);
CREATE INDEX alerts_clock on alerts (clock);
CREATE INDEX alerts_triggerid on alerts (triggerid);
CREATE INDEX alerts_statusretries on alerts (status, retries);
CREATE INDEX alerts_mediatypeid on alerts (mediatypeid);
CREATE INDEX alerts_userid on alerts (userid);

create sequence alerts_alertid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger alerts_trigger
before insert on alerts
for each row
begin
	select alerts_alertid.nextval into :new.alertid from dual;
end;
/

--
-- Table structure for table 'actions'
--

CREATE TABLE actions (
  actionid		number(10)		NOT NULL auto_increment,
  userid		number(10)		DEFAULT '0' NOT NULL,
  delay			number(10)		DEFAULT '0' NOT NULL,
  subject		varchar2(255)	DEFAULT '' NOT NULL,
  message		varchar2(2048)		DEFAULT '' NOT NULL,
  nextcheck		number(10)		DEFAULT '0' NOT NULL,
  recipient		number(3)		DEFAULT '0' NOT NULL,
  maxrepeats		number(10)		DEFAULT '0' NOT NULL,
  repeatdelay		number(10)		DEFAULT '600' NOT NULL,
  source		number(3)		DEFAULT '0' NOT NULL,
  actiontype		number(3)		DEFAULT '0' NOT NULL,
  status		number(3)		DEFAULT '0' NOT NULL,
  scripts		varchar(2048)		DEFAULT '' NOT NULL,
  PRIMARY KEY (actionid)
) type=InnoDB;

--
-- Table structure for table 'conditions'
--

CREATE TABLE conditions (
  conditionid		number(10)		NOT NULL auto_increment,
  actionid		number(10)		DEFAULT '0' NOT NULL,
  conditiontype		number(10)		DEFAULT '0' NOT NULL,
  operator		number(3)		DEFAULT '0' NOT NULL,
  value			varchar2(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (conditionid),
  KEY (actionid)
) type=InnoDB;

--
-- Table structure for table 'alarms'
--

CREATE TABLE alarms (
  alarmid		number(10)		NOT NULL auto_increment,
  triggerid		number(10)		DEFAULT '0' NOT NULL,
  clock			number(10)		DEFAULT '0' NOT NULL,
  value			number(10)		DEFAULT '0' NOT NULL,
  acknowledged		number(3)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid),
  KEY (triggerid,clock),
  KEY (clock)
) type=InnoDB;

--
-- Table structure for table 'functions'
--

CREATE TABLE functions (
	functionid	number(10)		NOT NULL,
	itemid		number(10)		DEFAULT '0' NOT NULL,
	triggerid	number(10)		DEFAULT '0' NOT NULL,
	lastvalue	varchar2(255),
	function	varchar2(10)	DEFAULT '' NOT NULL,
	parameter	varchar2(255)	DEFAULT '0' NOT NULL,
	CONSTRAINT 	functions_pk PRIMARY KEY (functionid)
);

CREATE INDEX functions_triggerid on functions (triggerid);
CREATE INDEX functions_itemidfunctionparam on functions (itemid,function,parameter);

create sequence functions_functionid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger functions_trigger
before insert on functions
for each row
begin
	select functions_functionid.nextval into :new.functionid from dual;
end;
/

--
-- Table structure for table 'history_uint'
--

CREATE TABLE history_uint (
  itemid		number(10)		DEFAULT '0' NOT NULL,
  clock			number(10)		DEFAULT '0' NOT NULL,
  value			bigint unsigned	DEFAULT '0' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;

--
-- Table structure for table 'history_str'
--

CREATE TABLE history_str (
  itemid		number(10)		DEFAULT '0' NOT NULL,
  clock			number(10)		DEFAULT '0' NOT NULL,
  value			varchar2(255)	DEFAULT '' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) type=InnoDB;

--
-- Table structure for table 'hosts'
--

CREATE TABLE hosts (
	hostid		number(10)		NOT NULL,
	host		varchar2(64)	DEFAULT '' NOT NULL,
	useip		number(3)		DEFAULT '1' NOT NULL,
	ip		varchar2(15)	DEFAULT '127.0.0.1' NOT NULL,
	port		number(10)		DEFAULT '0' NOT NULL,
	status		number(10)		DEFAULT '0' NOT NULL,
-- If status=UNREACHABLE, host will not be checked until this time
	disable_until	number(10)		DEFAULT '0' NOT NULL,
	error		varchar2(128)	DEFAULT '' NOT NULL,
	available	number(10)		DEFAULT '0' NOT NULL,
	errors_from	number(10)		DEFAULT '0' NOT NULL,
	templateid	number(10)		DEFAULT '0' NOT NULL,
  	CONSTRAINT 	hosts_pk PRIMARY KEY (hostid)
);
CREATE UNIQUE INDEX hosts_host on hosts (host);
CREATE INDEX hosts_status on hosts (status);

create sequence hosts_hostid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger hosts_trigger
before insert on hosts
for each row
begin
	select hosts_hostid.nextval into :new.hostid from dual;
end;
/


--
-- Table structure for table 'items'
--

CREATE TABLE items (
	itemid		number(10) NOT NULL,
	type		number(10) DEFAULT '0' NOT NULL,
	snmp_community	varchar2(64) DEFAULT '' NOT NULL,
	snmp_oid	varchar2(255) DEFAULT '' NOT NULL,
	snmp_port	number(10) DEFAULT '161' NOT NULL,
	hostid		number(10) NOT NULL,
	description	varchar2(255) DEFAULT '' NOT NULL,
	key_		varchar2(64) DEFAULT '' NOT NULL,
	delay		number(10) DEFAULT '0' NOT NULL,
	history		number(10) DEFAULT '90' NOT NULL,
	trends		number(10) DEFAULT '365' NOT NULL,
-- lastdelete is not longer required
--	lastdelete	number(10) DEFAULT '0' NOT NULL,
	nextcheck	number(10) DEFAULT '0' NOT NULL,
	lastvalue	varchar2(255) DEFAULT NULL,
	lastclock	number(10) DEFAULT NULL,
	prevvalue	varchar2(255) DEFAULT NULL,
	status		number(10) DEFAULT '0' NOT NULL,
	value_type	number(10) DEFAULT '0' NOT NULL,
	trapper_hosts	varchar2(255) DEFAULT '' NOT NULL,
	units		varchar2(10)	DEFAULT '' NOT NULL,
	multiplier	number(10)	DEFAULT '0' NOT NULL,
	delta		number(3)  DEFAULT '0' NOT NULL,
	prevorgvalue	number(16,4)  DEFAULT NULL,
	snmpv3_securityname	varchar2(64) DEFAULT '' NOT NULL,
	snmpv3_securitylevel	number(3) DEFAULT '0' NOT NULL,
	snmpv3_authpassphrase	varchar2(64) DEFAULT '' NOT NULL,
	snmpv3_privpassphrase	varchar2(64) DEFAULT '' NOT NULL,
	formula		varchar2(255) DEFAULT '0' NOT NULL,
	error		varchar2(128) DEFAULT '' NOT NULL,
	lastlogsize	number(10) DEFAULT '0' NOT NULL,
	logtimefmt	varchar2(64) DEFAULT '' NOT NULL,
	templateid	number(10) DEFAULT '0' NOT NULL,
	valuemapid	number(10) DEFAULT '0' NOT NULL,
  	CONSTRAINT 	items_pk PRIMARY KEY (itemid)
);

CREATE UNIQUE INDEX items_hostidkey on items (hostid, key_);
CREATE INDEX items_nextcheck on items (nextcheck);
CREATE INDEX items_status on items (status);

create sequence items_itemid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger items_trigger
before insert on items
for each row
begin
	select items_itemid.nextval into :new.itemid from dual;
end;
/

--
-- Table structure for table 'media'
--

CREATE TABLE media (
	mediaid		number(10) NOT NULL auto_increment,
	userid		number(10) DEFAULT '0' NOT NULL,
--	type		varchar2(10) DEFAULT '' NOT NULL,
	mediatypeid	number(10) DEFAULT '0' NOT NULL,
	sendto		varchar2(100) DEFAULT '' NOT NULL,
	active		number(10) DEFAULT '0' NOT NULL,
	severity	number(10) DEFAULT '63' NOT NULL,
	period		varchar2(100) DEFAULT '1-7,00:00-23:59' NOT NULL,
	PRIMARY KEY	(mediaid),
	KEY		(userid),
	KEY		(mediatypeid)
) type=InnoDB;

--
-- Table structure for table 'media'
--

CREATE TABLE media_type (
	mediatypeid	number(10) NOT NULL,
	type		number(10)		DEFAULT '0' NOT NULL,
	description	varchar2(100)	DEFAULT '' NOT NULL,
	smtp_server	varchar2(255)	DEFAULT '' NOT NULL,
	smtp_helo	varchar2(255)	DEFAULT '' NOT NULL,
	smtp_email	varchar2(255)	DEFAULT '' NOT NULL,
	exec_path	varchar2(255)	DEFAULT '' NOT NULL,
	CONSTRAINT 	media_type_pk PRIMARY KEY (mediatypeid)
);

create sequence media_type_mediatypeid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger media_type_trigger
before insert on media_type
for each row
begin
	select media_type_mediatypeid.nextval into :new.mediatypeid from dual;
end;
/

--
-- Table structure for table 'triggers'
--

CREATE TABLE triggers (
	triggerid	number(10) NOT NULL,
	expression	varchar2(255) DEFAULT '' NOT NULL,
	description	varchar2(255) DEFAULT '' NOT NULL,
	url		varchar2(255) DEFAULT '' NOT NULL,
	status		number(10) DEFAULT '0' NOT NULL,
	value		number(10) DEFAULT '0' NOT NULL,
	priority	number(4) DEFAULT '0' NOT NULL,
	lastchange	number(10) DEFAULT '0' NOT NULL,
	dep_level	number(4) DEFAULT '0' NOT NULL,
	comments	varchar2(2048),
	error		varchar2(128) DEFAULT '' NOT NULL,
	templateid	number(10) DEFAULT '0' NOT NULL,
  	CONSTRAINT 	triggers_pk PRIMARY KEY (triggerid)
);

CREATE INDEX triggers_status on triggers (status);
CREATE INDEX triggers_value on triggers (value);

create sequence triggers_triggerid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger triggers_trigger
before insert on triggers
for each row
begin
	select triggers_triggerid.nextval into :new.triggerid from dual;
end;
/

--
-- Table structure for table 'trigger_depends'
--

CREATE TABLE trigger_depends (
	triggerid_down	number(10) DEFAULT '0' NOT NULL,
	triggerid_up	number(10) DEFAULT '0' NOT NULL,
	PRIMARY KEY	(triggerid_down, triggerid_up),
--	KEY		(triggerid_down),
	KEY		(triggerid_up)
) type=InnoDB;

--
-- Table structure for table 'users'
--

CREATE TABLE users (
  userid		number(10)		NOT NULL auto_increment,
  alias			varchar2(100)	DEFAULT '' NOT NULL,
  name			varchar2(100)	DEFAULT '' NOT NULL,
  surname		varchar2(100)	DEFAULT '' NOT NULL,
  passwd		char(32)	DEFAULT '' NOT NULL,
  url			varchar2(255)	DEFAULT '' NOT NULL,
  autologout		number(10)		DEFAULT '900' NOT NULL,
  lang			varchar2(5)	DEFAULT 'en_gb' NOT NULL,
  refresh		number(10)		DEFAULT '30' NOT NULL,
  PRIMARY KEY (userid),
  UNIQUE (alias)
) type=InnoDB;

--
-- Table structure for table 'audit'
--

CREATE TABLE audit (
  auditid		number(10)		NOT NULL auto_increment,
  userid		number(10)		DEFAULT '0' NOT NULL,
  clock			number(10)		DEFAULT '0' NOT NULL,
  action		number(10)		DEFAULT '0' NOT NULL,
  resource		number(10)		DEFAULT '0' NOT NULL,
  details		varchar2(128)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (auditid),
  KEY (userid,clock),
  KEY (clock)
) type=InnoDB;

--
-- Table structure for table 'sessions'
--

CREATE TABLE sessions (
  sessionid		varchar2(32)	NOT NULL DEFAULT '',
  userid		number(10)		NOT NULL DEFAULT '0',
  lastaccess		number(10)		NOT NULL DEFAULT '0',
  PRIMARY KEY (sessionid)
) type=InnoDB;

--
-- Table structure for table 'rights'
--

CREATE TABLE rights (
  rightid		number(10)		NOT NULL auto_increment,
  userid		number(10)		DEFAULT '0' NOT NULL,
  name			char(255)	DEFAULT '' NOT NULL,
  permission		char(1)		DEFAULT '' NOT NULL,
  id			number(10),
  PRIMARY KEY (rightid),
  KEY (userid)
) type=InnoDB;

--
-- Table structure for table 'problems'
--

-- CREATE TABLE problems (
--   problemid		number(10)		NOT NULL auto_increment,
--   userid		number(10)		DEFAULT '0' NOT NULL,
--   triggerid		number(10),
--   lastupdate		number(10)		DEFAULT '0' NOT NULL,
--   clock			number(10)		DEFAULT '0' NOT NULL,
--   status		number(3)		DEFAULT '0' NOT NULL,
--   description		varchar2(255)	DEFAULT '' NOT NULL,
--   categoryid		number(10),
  -- priority		number(3)		DEFAULT '0' NOT NULL,
--   PRIMARY KEY (problemid),
--   KEY (status),
--   KEY (categoryid),
--   KEY (priority)
-- ) type=InnoDB;

--
-- Table structure for table 'categories'
--

-- CREATE TABLE categories (
--   categoryid		number(10)		NOT NULL auto_increment,
--   descripion		varchar2(64)	DEFAULT '' NOT NULL,
--   PRIMARY KEY (categoryid)
-- ) type=InnoDB;

--
-- Table structure for table 'problems_categories'
--

-- CREATE TABLE problems_comments (
--   commentid		number(10)		NOT NULL auto_increment,
--   problemid		number(10)		DEFAULT '0' NOT NULL,
--   clock			number(10),
--   status_before		number(3)		DEFAULT '0' NOT NULL,
--   status_after		number(3)		DEFAULT '0' NOT NULL,
--   comment		blob,
--   PRIMARY KEY (commentid),
--   KEY (problemid,clock)
-- ) type=InnoDB;

--
-- Table structure for table 'service_alarms'
--

CREATE TABLE service_alarms (
  servicealarmid	number(10)		NOT NULL auto_increment,
  serviceid		number(10)		DEFAULT '0' NOT NULL,
  clock			number(10)		DEFAULT '0' NOT NULL,
  value			number(10)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (servicealarmid),
  KEY (serviceid,clock),
  KEY (clock)
) type=InnoDB;

--
-- Table structure for table 'profiles'
--

CREATE TABLE profiles (
  profileid		number(10)		NOT NULL auto_increment,
  userid		number(10)		DEFAULT '0' NOT NULL,
  idx			varchar2(64)	DEFAULT '' NOT NULL,
  value			varchar2(255)	DEFAULT '' NOT NULL,
  valuetype		number(10)		DEFAULT 0 NOT NULL,
  PRIMARY KEY (profileid),
--  KEY (userid),
  UNIQUE (userid,idx)
) type=InnoDB;

--
-- Table structure for table 'screens'
--

CREATE TABLE screens (
  screenid		number(10)		NOT NULL auto_increment,
  name			varchar2(255)	DEFAULT 'Screen' NOT NULL,
  cols			number(10)		DEFAULT '1' NOT NULL,
  rows			number(10)		DEFAULT '1' NOT NULL,
  PRIMARY KEY  (screenid)
) TYPE=InnoDB;

--
-- Table structure for table 'screens_items'
--

CREATE TABLE screens_items (
	screenitemid	number(10)		NOT NULL auto_increment,
	screenid	number(10)		DEFAULT '0' NOT NULL,
	resource	number(10)		DEFAULT '0' NOT NULL,
	resourceid	number(10)		DEFAULT '0' NOT NULL,
	width		number(10)		DEFAULT '320' NOT NULL,
	height		number(10)		DEFAULT '200' NOT NULL,
	x		number(10)		DEFAULT '0' NOT NULL,
	y		number(10)		DEFAULT '0' NOT NULL,
	colspan		number(10)		DEFAULT '0' NOT NULL,
	rowspan		number(10)		DEFAULT '0' NOT NULL,
	elements	number(10)		DEFAULT '25' NOT NULL,
	valign		int(2)		DEFAULT '0' NOT NULL,
	halign		int(2)		DEFAULT '0' NOT NULL,
	style		number(10)		DEFAULT '0' NOT NULL,
	url		varchar2(255)	DEFAULT '' NOT NULL,
	  PRIMARY KEY  (screenitemid)
) TYPE=InnoDB;

--
-- Table structure for table 'stats'
--

CREATE TABLE stats (
  itemid		number(10)		DEFAULT '0' NOT NULL,
  year			number(10)		DEFAULT '0' NOT NULL,
  month			number(10)		DEFAULT '0' NOT NULL,
  day			number(10)		DEFAULT '0' NOT NULL,
  hour			number(10)		DEFAULT '0' NOT NULL,
  value_max		number(16,4)	DEFAULT '0.0000' NOT NULL,
  value_min		number(16,4)	DEFAULT '0.0000' NOT NULL,
  value_avg		number(16,4)	DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,year,month,day,hour)
) type=InnoDB;

--
-- Table structure for table 'usrgrp'
--

CREATE TABLE usrgrp (
  usrgrpid		number(10)		NOT NULL auto_increment,
  name			varchar2(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (usrgrpid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'users_groups'
--

CREATE TABLE users_groups (
  usrgrpid		number(10)		DEFAULT '0' NOT NULL,
  userid		number(10)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (usrgrpid,userid)
) type=InnoDB;

--
-- Table structure for table 'trends'
--

CREATE TABLE trends (
  itemid		number(10)		DEFAULT '0' NOT NULL,
  clock			number(10)		DEFAULT '0' NOT NULL,
  num			int(2)		DEFAULT '0' NOT NULL,
  value_min		number(16,4)	DEFAULT '0.0000' NOT NULL,
  value_avg		number(16,4)	DEFAULT '0.0000' NOT NULL,
  value_max		number(16,4)	DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock)
) type=InnoDB;

--
-- Table structure for table 'images'
--

CREATE TABLE images (
  imageid		number(10)		NOT NULL auto_increment,
  imagetype		number(10)		DEFAULT '0' NOT NULL,
  name			varchar2(64)	DEFAULT '0' NOT NULL,
  image			longblob	DEFAULT '' NOT NULL,
  PRIMARY KEY (imageid),
  UNIQUE (imagetype, name)
) type=InnoDB;

--
-- Table structure for table 'hosts_templates'
--

CREATE TABLE hosts_templates (
  hosttemplateid	number(10)		NOT NULL auto_increment,
  hostid		number(10)		DEFAULT '0' NOT NULL,
  templateid		number(10)		DEFAULT '0' NOT NULL,
  items			number(3)		DEFAULT '0' NOT NULL,
  triggers		number(3)		DEFAULT '0' NOT NULL,
  graphs		number(3)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hosttemplateid),
  UNIQUE (hostid, templateid)
) type=InnoDB;

--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
  id			number(10)		NOT NULL auto_increment,
  itemid		number(10)		DEFAULT '0' NOT NULL,
  clock			number(10)		DEFAULT '0' NOT NULL,
  timestamp		number(10)		DEFAULT '0' NOT NULL,
  source		varchar2(64)	DEFAULT '' NOT NULL,
  severity		number(10)		DEFAULT '0' NOT NULL,
  value			text		DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  KEY itemidclock (itemid, clock)
) type=InnoDB;

--
-- Table structure for table 'hosts_profiles'
--

CREATE TABLE hosts_profiles (
  hostid		number(10)		DEFAULT '0' NOT NULL,
  devicetype		varchar2(64)	DEFAULT '' NOT NULL,
  name			varchar2(64)	DEFAULT '' NOT NULL,
  os			varchar2(64)	DEFAULT '' NOT NULL,
  serialno		varchar2(64)	DEFAULT '' NOT NULL,
  tag			varchar2(64)	DEFAULT '' NOT NULL,
  macaddress		varchar2(64)	DEFAULT '' NOT NULL,
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
  id			number(10)		NOT NULL auto_increment,
  priority		number(10)		DEFAULT '0' NOT NULL,
  pattern		varchar2(255)	DEFAULT '' NOT NULL,
  hostid		number(10)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (id)
) type=InnoDB;

--
-- Table structure for table 'valuemaps'
--

CREATE TABLE valuemaps (
  valuemapid		number(10)		NOT NULL auto_increment,
  name			varchar2(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (valuemapid),
  UNIQUE (name)
) type=InnoDB;

--
-- Table structure for table 'mapping'
--

CREATE TABLE mappings (
  mappingid		number(10)		NOT NULL auto_increment,
  valuemapid		number(10)		DEFAULT '0' NOT NULL,
  value			varchar2(64)	DEFAULT '' NOT NULL,
  newvalue		varchar2(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (mappingid),
  KEY valuemapid (valuemapid)
) type=InnoDB;

--
-- Table structure for table 'housekeeper'
--

CREATE TABLE housekeeper (
  housekeeperid		number(10)		NOT NULL auto_increment,
  tablename		varchar2(64)	DEFAULT '' NOT NULL,
  field			varchar2(64)	DEFAULT '' NOT NULL,
  value			number(10)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (housekeeperid)
) type=InnoDB;

--
-- Table structure for table 'acknowledges'
--

CREATE TABLE acknowledges (
	acknowledgeid		number(10)		NOT NULL auto_increment,
	userid			number(10)		DEFAULT '0' NOT NULL,
	alarmid			number(10)		DEFAULT '0' NOT NULL,
	clock			number(10)		DEFAULT '0' NOT NULL,
	message			varchar2(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (acknowledgeid),
	KEY userid (userid),
	KEY alarmid (alarmid),
	KEY clock (clock)
) type=InnoDB;

--
-- Table structure for table 'applications'
--

CREATE TABLE applications (
	applicationid           number(10)          NOT NULL auto_increment,
	hostid                  number(10)          DEFAULT '0' NOT NULL,
	name                    varchar2(255)    DEFAULT '' NOT NULL,
	templateid		number(10)		DEFAULT '0' NOT NULL,
	PRIMARY KEY 	(applicationid),
	KEY 		hostid (hostid),
	KEY 		templateid (templateid),
	UNIQUE          appname (hostid,name)
) type=InnoDB;

--
-- Table structure for table 'items_applications'
--

CREATE TABLE items_applications (
	applicationid           number(10)          DEFAULT '0' NOT NULL,
	itemid                  number(10)          DEFAULT '0' NOT NULL,
	PRIMARY KEY (applicationid,itemid)
) type=InnoDB;

