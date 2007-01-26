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
-- Table structure for table 'hosts'
--

--\connect zabbix

CREATE SEQUENCE hosts_hostid_seq START 10100;

CREATE TABLE hosts (
  hostid		integer DEFAULT nextval('hosts_hostid_seq') NOT NULL,
  host			varchar(64)	DEFAULT '' 		NOT NULL,
  useip			int4		DEFAULT '0'		NOT NULL,
  ip			varchar(15)	DEFAULT '127.0.0.1'	NOT NULL,
  port			int4		DEFAULT '0'		NOT NULL,
  status		int4		DEFAULT '0'		NOT NULL,
  disable_until		int4		DEFAULT '0'		NOT NULL,
  error			varchar(128)	DEFAULT ''		NOT NULL,
  available		int4		DEFAULT '0'		NOT NULL,
  errors_from		int4		DEFAULT '0'		NOT NULL,
  templateid		int4		DEFAULT '0'		NOT NULL,
  PRIMARY KEY (hostid)
) with oids;

CREATE INDEX hosts_status on hosts (status);
CREATE UNIQUE INDEX hosts_host on hosts (host);

--
-- Table structure for table 'items'
--

CREATE SEQUENCE items_itemid_seq START 18000;

CREATE TABLE items (
	itemid		integer DEFAULT nextval('items_itemid_seq') NOT NULL,
	type			int4		NOT NULL,
	snmp_community	varchar(64)	DEFAULT ''	NOT NULL,
	snmp_oid		varchar(255)	DEFAULT ''	NOT NULL,
	snmp_port		int4		DEFAULT '161'	NOT NULL,
	hostid		int4		NOT NULL,
	description		varchar(255)	DEFAULT '' NOT NULL,
	key_			varchar(64)	DEFAULT '' NOT NULL,
	delay			int4		DEFAULT '0' NOT NULL,
	history		int4		DEFAULT '90' NOT NULL,
	trends		int4		DEFAULT '365' NOT NULL,
	-- lastdelete is no longer required
	--  lastdelete		int4		DEFAULT '0' NOT NULL,
	nextcheck		int4		DEFAULT '0' NOT NULL,
	lastvalue		varchar(255)	DEFAULT NULL,
	lastclock		int4		DEFAULT NULL,
	prevvalue		varchar(255)	DEFAULT NULL,
	status		int4		DEFAULT '0' NOT NULL,
	value_type		int4		DEFAULT '0' NOT NULL,
	trapper_hosts		varchar(255)	DEFAULT '' NOT NULL,
	units			varchar(10)	DEFAULT '' NOT NULL,
	multiplier		int4		DEFAULT '0' NOT NULL,
	delta			int4		DEFAULT '0' NOT NULL,
	prevorgvalue		float8		DEFAULT NULL,
	snmpv3_securityname	varchar(64)	DEFAULT '' NOT NULL,
	snmpv3_securitylevel	int4		DEFAULT '0' NOT NULL,
	snmpv3_authpassphrase	varchar(64)	DEFAULT '' NOT NULL,
	snmpv3_privpassphrase	varchar(64)	DEFAULT '' NOT NULL,
	formula		varchar(255)	DEFAULT '0' NOT NULL,
	error			varchar(128)	DEFAULT '' NOT NULL,
	lastlogsize		int4		DEFAULT '0' NOT NULL,
	logtimefmt		varchar(64)	DEFAULT '' NOT NULL,
	templateid		int4		DEFAULT '0' NOT NULL,
	valuemapid		int4		 DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemid)
	--  FOREIGN KEY (hostid) REFERENCES hosts
) with oids;

CREATE UNIQUE INDEX items_hostid_key on items (hostid,key_);
--CREATE INDEX items_hostid on items (hostid);
CREATE INDEX items_nextcheck on items (nextcheck);
CREATE INDEX items_status on items (status);

--
-- Table structure for table 'config'
--

CREATE TABLE config (
--  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
--  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
--  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
--  password_required	int4		DEFAULT '0' NOT NULL,
  alert_history		int4		DEFAULT '0' NOT NULL,
  alarm_history		int4		DEFAULT '0' NOT NULL,
  refresh_unsupported	int4		DEFAULT '0' NOT NULL,
  work_period		varchar(100)	DEFAULT '1-5,00:00-24:00' NOT NULL
) with oids;

--
-- Table structure for table 'groups'
--

CREATE SEQUENCE groups_groupid_seq START 3;

CREATE TABLE groups (
  groupid		integer DEFAULT nextval('groups_groupid_seq') NOT NULL,
  name			varchar(64)     DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid)
) with oids;

CREATE UNIQUE INDEX groups_name on groups (name);

--
-- Table structure for table 'hosts_groups'
--

CREATE TABLE hosts_groups (
  hostid		int4		DEFAULT '0' NOT NULL,
  groupid		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid,groupid)
) with oids;

--CREATE UNIQUE INDEX hosts_groups_name on hosts_groups (hostid,groupid);

--
-- Table structure for table 'triggers'
--

CREATE SEQUENCE triggers_triggerid_seq START 13000;
CREATE TABLE triggers (
  triggerid		integer DEFAULT nextval('triggers_triggerid_seq') NOT NULL,
  expression		varchar(255)	DEFAULT '' NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  url			varchar(255)	DEFAULT '' NOT NULL,
  status		int4		DEFAULT '0' NOT NULL,
  value			int4		DEFAULT '0' NOT NULL,
  priority		int2		DEFAULT '0' NOT NULL,
  lastchange		int4		DEFAULT '0' NOT NULL,
  dep_level		int2		DEFAULT '0' NOT NULL,
  comments		text,
  error			varchar(128)	DEFAULT '' NOT NULL,
  templateid		int4 DEFAULT '0' NOT NULL,
  PRIMARY KEY (triggerid)
) with oids;

CREATE INDEX triggers_value on triggers (value);
CREATE INDEX triggers_status on triggers (status);

--
-- Table structure for table 'trigger_depends'
--

CREATE TABLE trigger_depends (
  triggerid_down	int4	DEFAULT '0' NOT NULL,
  triggerid_up		int4	DEFAULT '0' NOT NULL,
  PRIMARY KEY		(triggerid_down, triggerid_up)
) with oids;

--CREATE INDEX trigger_depends_down on trigger_depends (triggerid_down);
CREATE INDEX trigger_depends_up   on trigger_depends (triggerid_up);

--
-- Table structure for table 'users'
--
CREATE SEQUENCE users_userid_seq START 3;

CREATE TABLE users (
  userid		integer DEFAULT nextval('users_userid_seq') NOT NULL,
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		char(32)	DEFAULT '' NOT NULL,
  url			varchar(255)	DEFAULT '' NOT NULL,
  autologout		int4		DEFAULT '900' NOT NULL,
  lang			varchar(5)	DEFAULT 'en_gb' NOT NULL,
  refresh		int4		DEFAULT '30' NOT NULL,
  PRIMARY KEY (userid)
) with oids;

CREATE UNIQUE INDEX users_alias on users (alias);

--
-- Table structure for table 'auditlog'
--

CREATE TABLE auditlog (
  auditid		serial,
  userid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  action		int4		DEFAULT '0' NOT NULL,
  resourcetype		int4		DEFAULT '0' NOT NULL,
  details		varchar(128)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (auditid)
) with oids;

CREATE INDEX auditlog_userid_clock on auditlog (userid,clock);
CREATE INDEX auditlog_clock on auditlog (clock);

--
-- Table structure for table 'actions'
--

CREATE TABLE actions (
  actionid		serial,
  userid		int4		DEFAULT '0' NOT NULL,
--  delay			int4		DEFAULT '0' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		text		DEFAULT '' NOT NULL,
--  nextcheck		int4		DEFAULT '0' NOT NULL,
  recipient		int4		DEFAULT '0' NOT NULL,
  maxrepeats		int4		DEFAULT '0' NOT NULL,
  repeatdelay		int4		DEFAULT '600' NOT NULL,
  source		int2		DEFAULT '0' NOT NULL,
  actiontype		int2		DEFAULT '0' NOT NULL,
  status		int2		DEFAULT '0' NOT NULL,
  scripts		text		DEFAULT '' NOT NULL,
  PRIMARY KEY (actionid)
--  depends on scope. Could be hostid or 0.
--  FOREIGN KEY (triggerid) REFERENCES triggers
--  could be groupid
--  FOREIGN KEY (userid) REFERENCES users
) with oids;

--
-- Table structure for table 'conditions'
--

CREATE TABLE conditions (
  conditionid		serial,
  actionid		int4		DEFAULT '0' NOT NULL,
  conditiontype		int4		DEFAULT '0' NOT NULL,
  operator		int2		DEFAULT '0' NOT NULL,
  value			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (conditionid)
--  FOREIGN KEY (actionid) REFERENCES actions
) with oids;

CREATE INDEX conditiond_actionid on conditions (actionid);


--
-- Table structure for table 'media_type'
--
CREATE SEQUENCE media_type_mediatypeid_seq START 3;

CREATE TABLE media_type (
  mediatypeid		integer DEFAULT nextval('media_type_mediatypeid_seq') NOT NULL,
  type			int4		DEFAULT '0' NOT NULL,
  description		varchar(100)	DEFAULT '' NOT NULL,
  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
  exec_path		varchar(255)	DEFAULT '' NOT NULL,
  gsm_modem		varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY(mediatypeid)
) with oids;


--
-- Table structure for table 'alerts'
--

CREATE TABLE alerts (
  alertid		serial,
  actionid		int4		DEFAULT '0' NOT NULL,
  triggerid		int4		DEFAULT '0' NOT NULL,
  userid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
--  type		varchar(10)	DEFAULT '' NOT NULL,
  mediatypeid		int4		DEFAULT '0' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		text		DEFAULT '' NOT NULL,
  status		int4		DEFAULT '0' NOT NULL,
  retries		int4		DEFAULT '0' NOT NULL,
  error			varchar(128)	DEFAULT '' NOT NULL,
  repeats		int4		DEFAULT '0' NOT NULL,
  maxrepeats		int4		DEFAULT '0' NOT NULL,
  nextcheck		int4		DEFAULT '0' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alertid)
--  FOREIGN KEY (actionid) REFERENCES actions,
--  FOREIGN KEY (triggerid) REFERENCES triggers,
--  FOREIGN KEY (mediatypeid) REFERENCES media_type
) with oids;

CREATE INDEX alerts_actionid on alerts (actionid);
CREATE INDEX alerts_clock on alerts (clock);
CREATE INDEX alerts_triggerid on alerts (triggerid);
CREATE INDEX alerts_status_retires on alerts (status,retries);
CREATE INDEX alerts_mediatypeid on alerts (mediatypeid);
CREATE INDEX alerts_userid on alerts (userid);

--
-- Table structure for table 'alarms'
--

CREATE TABLE alarms (
  alarmid		serial,
  triggerid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			int4		DEFAULT '0' NOT NULL,
  acknowledged		int2		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid)
--  FOREIGN KEY (triggerid) REFERENCES triggers
) with oids;

CREATE INDEX alarms_triggerid_clock on alarms (triggerid, clock);
CREATE INDEX alarms_clock on alarms (clock);

--
-- Table structure for table 'functions'
--

CREATE SEQUENCE functions_functionid_seq START 11300;

CREATE TABLE functions (
  functionid		integer DEFAULT nextval('functions_functionid_seq') NOT NULL,
  itemid		int4		DEFAULT '0' NOT NULL,
  triggerid		int4		DEFAULT '0' NOT NULL,
  lastvalue		varchar(255),
  function		varchar(12)	DEFAULT '' NOT NULL,
  parameter		varchar(255)	DEFAULT '0' NOT NULL,
  PRIMARY KEY (functionid)
--  FOREIGN KEY (itemid) REFERENCES items,
--  FOREIGN KEY (triggerid) REFERENCES triggers
) with oids;

CREATE INDEX funtions_triggerid on functions (triggerid);
CREATE INDEX functions_i_f_p on functions (itemid,function,parameter);

--
-- Table structure for table 'history'
--

CREATE TABLE history (
  itemid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			float8		DEFAULT '0.0000' NOT NULL
--  PRIMARY KEY (itemid,clock),
--  FOREIGN KEY (itemid) REFERENCES items
) with oids;

CREATE INDEX history_i_c on history (itemid, clock);

--
-- Table structure for table 'history_uint'
--

CREATE TABLE history_uint (
  itemid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			double precision	DEFAULT '0' NOT NULL
--  PRIMARY KEY (itemid,clock),
--  FOREIGN KEY (itemid) REFERENCES items
) with oids;

CREATE INDEX history_uint_i_c on history_uint (itemid, clock);

--
-- Table structure for table 'history_str'
--

CREATE TABLE history_str (
  itemid                int4            DEFAULT '0' NOT NULL,
  clock                 int4            DEFAULT '0' NOT NULL,
  value                 varchar(255)    DEFAULT '' NOT NULL
--  PRIMARY KEY (itemid,clock),
--  FOREIGN KEY (itemid) REFERENCES items
) with oids;

CREATE INDEX history_str_i_c on history_str (itemid, clock);

--
-- Table structure for table 'media'
--

CREATE TABLE media (
  mediaid		serial,
  userid		int4		DEFAULT '0' NOT NULL,
--  type		varchar(10)	DEFAULT '' NOT NULL,
  mediatypeid		int4		DEFAULT '0' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  active		int4		DEFAULT '0' NOT NULL,
  severity		int4		DEFAULT '63' NOT NULL,
  period		varchar(100)	DEFAULT '1-7,00:00-23:59' NOT NULL,
  PRIMARY KEY (mediaid)
--  FOREIGN KEY (userid) REFERENCES users,
--  FOREIGN KEY (mediatypeid) REFERENCES media_type
) with oids;

CREATE INDEX media_userid on media (userid);
CREATE INDEX media_mediatypeid on media (mediatypeid);

--
-- Table structure for table 'sysmaps'
--

CREATE TABLE sysmaps (
  sysmapid		serial,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int4		DEFAULT '0' NOT NULL,
  height		int4		DEFAULT '0' NOT NULL,
  background		varchar(64)	DEFAULT '' NOT NULL,
  label_type		int4		DEFAULT '0' NOT NULL,
  label_location	int2		DEFAULT '0' NOT NULL,
  PRIMARY KEY (sysmapid)
) with oids;

CREATE UNIQUE INDEX sysmaps_name on sysmaps (name);

--
-- Table structure for table 'sysmaps_hosts'
--

CREATE TABLE sysmaps_elements (
  selementid		serial,
  sysmapid		int4		DEFAULT '0' NOT NULL,
  elementid		int4		DEFAULT '0' NOT NULL,
  elementtype		int4		DEFAULT '0' NOT NULL,
  icon			varchar(32)	DEFAULT 'Server' NOT NULL,
  icon_on		varchar(32)	DEFAULT 'Server' NOT NULL,
  label			varchar(128)	DEFAULT '' NOT NULL,
  label_location	int2		DEFAULT NULL,
  x			int4		DEFAULT '0' NOT NULL,
  y			int4		DEFAULT '0' NOT NULL,
  url			varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (selementid)
--  FOREIGN KEY (sysmapid) REFERENCES sysmaps,
--  FOREIGN KEY (hostid) REFERENCES hosts
) with oids;

--
-- Table structure for table 'sysmaps_links'
--

CREATE TABLE sysmaps_links (
  linkid		serial,
  sysmapid		int4		DEFAULT '0' NOT NULL,
  selementid1		int4		DEFAULT '0' NOT NULL,
  selementid2		int4		DEFAULT '0' NOT NULL,
-- may be NULL 
  triggerid		int4,
  drawtype_off		int4		DEFAULT '0' NOT NULL,
  color_off		varchar(32)	DEFAULT 'Black' NOT NULL,
  drawtype_on		int4		DEFAULT '0' NOT NULL,
  color_on		varchar(32)	DEFAULT 'Red' NOT NULL,
  PRIMARY KEY (linkid)
--  FOREIGN KEY (sysmapid) REFERENCES sysmaps,
--  FOREIGN KEY (shostid1) REFERENCES sysmaps_hosts,
--  FOREIGN KEY (shostid2) REFERENCES sysmaps_hosts
) with oids;

--
-- Table structure for table 'graphs'
--

CREATE TABLE graphs (
  graphid		serial,
  name			varchar(128)	DEFAULT '' NOT NULL,
  width			int4		DEFAULT '0' NOT NULL,
  height		int4		DEFAULT '0' NOT NULL,
  yaxistype		int2		DEFAULT '0' NOT NULL,
  yaxismin		float8		DEFAULT '0' NOT NULL,
  yaxismax		float8		DEFAULT '0' NOT NULL,
  templateid		int4		DEFAULT '0' NOT NULL,
  show_work_period	int2		DEFAULT '1' NOT NULL,
  show_triggers		int2		DEFAULT '1' NOT NULL,
  PRIMARY KEY (graphid)
) with oids;

CREATE INDEX graphs_name on graphs (name);

--
-- Table structure for table 'graphs_items'
--

CREATE TABLE graphs_items (
  gitemid		serial,
  graphid		int4		DEFAULT '0' NOT NULL,
  itemid		int4		DEFAULT '0' NOT NULL,
  drawtype		int4		DEFAULT '0' NOT NULL,
  sortorder		int4		DEFAULT '0' NOT NULL,
  color			varchar(32)	DEFAULT 'Dark Green' NOT NULL,
  yaxisside		int2		DEFAULT '1' NOT NULL,
  calc_fnc		int2		DEFAULT '2' NOT NULL,
  type			int2		DEFAULT '0' NOT NULL,
  periods_cnt		int4		DEFAULT '5' NOT NULL,
  PRIMARY KEY (gitemid)
--  FOREIGN KEY (graphid) REFERENCES graphs,
--  FOREIGN KEY (itemid) REFERENCES items
) with oids;

--
-- Table structure for table 'services'
--

CREATE TABLE services (
  serviceid		serial,
  name			varchar(128)	DEFAULT '' NOT NULL,
  status		int2		DEFAULT '0' NOT NULL,
  algorithm		int2		DEFAULT '0' NOT NULL,
  triggerid		int4,
  showsla		int4		DEFAULT '0' NOT NULL,
  goodsla		float8		DEFAULT '99.9' NOT NULL,
  sortorder		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (serviceid)
) with oids;

--
-- Table structure for table 'services_links'
--

CREATE TABLE services_links (
  linkid		serial,
  serviceupid		int4		DEFAULT '0' NOT NULL,
  servicedownid		int4		DEFAULT '0' NOT NULL,
  soft			int2		DEFAULT '0' NOT NULL,
  PRIMARY KEY (linkid)
) with oids;

--CREATE INDEX services_links_serviceupid on services_links (serviceupid);
CREATE INDEX services_links_servicedownid on services_links (servicedownid);
CREATE UNIQUE INDEX services_links_upidownid on services_links (serviceupid, servicedownid);

CREATE SEQUENCE rights_rightid_seq START 4;

CREATE TABLE rights (
  rightid               integer DEFAULT nextval('rights_rightid_seq') NOT NULL,
  userid                int4		DEFAULT '0' NOT NULL,
  name                  varchar(255)	DEFAULT '' NOT NULL,
  permission            char(1)		DEFAULT '' NOT NULL,
  id                    int4,
  PRIMARY KEY (rightid)
) with oids;

CREATE INDEX rights_userid on rights (userid);

CREATE TABLE sessions (
	sessionid	varchar(32)	DEFAULT '' NOT NULL,
	userid		int4		DEFAULT '0' NOT NULL,
	lastaccess	int4		DEFAULT '0' NOT NULL,
	PRIMARY KEY (sessionid),
	FOREIGN KEY (userid) REFERENCES users
) with oids;

--
-- Table structure for table 'services_alarms'
--

CREATE TABLE service_alarms (
  servicealarmid	serial,
  serviceid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (servicealarmid)
) with oids;

CREATE INDEX services_alarms_serviceid_clock on service_alarms (serviceid,clock);
CREATE INDEX services_alarms_clock on service_alarms (clock);

--
-- Table structure for table 'profiles'
--

CREATE TABLE profiles (
  profileid		serial,
  userid		int4		DEFAULT '0' NOT NULL,
  idx			varchar(64)	DEFAULT '' NOT NULL,
  value                 varchar(255)    DEFAULT '' NOT NULL,
  valuetype             int4            DEFAULT 0 NOT NULL,
  PRIMARY KEY (profileid)
) with oids;

--CREATE INDEX profiles_userid on profiles (userid);
CREATE UNIQUE INDEX profiles_userid_idx on profiles (userid,idx);

--
-- Table structure for table 'screens'
--

CREATE TABLE screens (
  screenid		serial,
  name			varchar(255)	DEFAULT 'Screen' NOT NULL,
  hsize			int4		DEFAULT '1' NOT NULL,
  vsize			int4		DEFAULT '1' NOT NULL,
  PRIMARY KEY  (screenid)
) with oids;

--
-- Table structure for table 'screens_items'
--

CREATE TABLE screens_items (
	screenitemid	serial,
	screenid	int4		DEFAULT '0' NOT NULL,
	resourcetype	int4		DEFAULT '0' NOT NULL,
	resourceid	int4		DEFAULT '0' NOT NULL,
	width		int4		DEFAULT '320' NOT NULL,
	height		int4		DEFAULT '200' NOT NULL,
	x		int4		DEFAULT '0' NOT NULL,
	y		int4		DEFAULT '0' NOT NULL,
	colspan		int4		DEFAULT '0' NOT NULL,
	rowspan		int4		DEFAULT '0' NOT NULL,
	elements	int4		DEFAULT '25' NOT NULL,
	valign		int2		DEFAULT '0' NOT NULL,
	halign		int2		DEFAULT '0' NOT NULL,
	style		int4		DEFAULT '0' NOT NULL,
	url		varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY  (screenitemid)
) with oids;

--
-- Table structure for table 'stats'
--

--CREATE TABLE stats (
--  itemid		int4		DEFAULT '0' NOT NULL,
--  year			int4		DEFAULT '0' NOT NULL,
--  month			int4		DEFAULT '0' NOT NULL,
--  day			int4		DEFAULT '0' NOT NULL,
--  hour			int4		DEFAULT '0' NOT NULL,
--  value_max		float8		DEFAULT '0.0000' NOT NULL,
--  value_min		float8		DEFAULT '0.0000' NOT NULL,
--  value_avg		float8		DEFAULT '0.0000' NOT NULL,
--  PRIMARY KEY (itemid,year,month,day,hour)
--);

--
-- Table structure for table 'usrgrp'
--

CREATE SEQUENCE usrgrp_usrgrpid_seq START 9;

CREATE TABLE usrgrp (
  usrgrpid		integer DEFAULT nextval('usrgrp_usrgrpid_seq') NOT NULL,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (usrgrpid)
) with oids;

CREATE UNIQUE INDEX usrgrp_name on usrgrp (name);

--
-- Table structure for table 'users_groups'
--

CREATE TABLE users_groups (
  usrgrpid		int4		DEFAULT '0' NOT NULL,
  userid		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (usrgrpid,userid)
--  FOREIGN KEY (usrgrpid) REFERENCES usrgrp,
--  FOREIGN KEY (userid) REFERENCES users
) with oids;

--
-- Table structure for table 'trends'
--

CREATE TABLE trends (
  itemid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  num			int2		DEFAULT '0' NOT NULL,
  value_min		float8		DEFAULT '0.0000' NOT NULL,
  value_avg		float8		DEFAULT '0.0000' NOT NULL,
  value_max		float8		DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock)
--  FOREIGN KEY (itemid) REFERENCES items
) with oids;

--
-- Table structure for table 'images'
--

CREATE SEQUENCE images_imageid_seq START 100;

CREATE TABLE images (
  imageid		integer DEFAULT nextval('images_imageid_seq') NOT NULL,
  imagetype		int4		DEFAULT '0' NOT NULL,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  image			bytea,
  PRIMARY KEY (imageid)
) with oids;

CREATE UNIQUE INDEX images_name_imagetype on images (name, imagetype);

--
-- Table structure for table 'hosts_templates'
--

CREATE TABLE hosts_templates (
  hosttemplateid	serial,
  hostid		int4		DEFAULT '0' NOT NULL,
  templateid		int4		DEFAULT '0' NOT NULL,
  items			int2		DEFAULT '0' NOT NULL,
  triggers		int2		DEFAULT '0' NOT NULL,
  graphs		int2		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hosttemplateid)
) with oids;

CREATE UNIQUE INDEX hosts_templates_hostid_templateid on hosts_templates (hostid, templateid);

--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
  id			serial,
  itemid                int4            DEFAULT '0' NOT NULL,
  clock                 int4            DEFAULT '0' NOT NULL,
  timestamp		int4		DEFAULT '0' NOT NULL,
  source		varchar(64)	DEFAULT '' NOT NULL,
  severity		int4		DEFAULT '0' NOT NULL,
  value                 varchar(255)    DEFAULT '' NOT NULL,
  PRIMARY KEY (id)
--  FOREIGN KEY (itemid) REFERENCES items
) with oids;

CREATE INDEX history_log_i_c on history_str (itemid, clock);

--
-- Table structure for table 'history_text'
--

CREATE TABLE history_text (
  id                    serial,
  itemid		int4	DEFAULT '0' NOT NULL,
  clock			int4	DEFAULT '0' NOT NULL,
  value			text	DEFAULT '' NOT NULL,
  PRIMARY KEY (id)
) with oids;

CREATE UNIQUE INDEX history_text_itemid_clock on history_text (itemid, clock);

--
-- Table structure for table 'hosts_profiles'
--

CREATE TABLE hosts_profiles (
  hostid		int4		DEFAULT '0' NOT NULL,
  devicetype		varchar(64)	DEFAULT '' NOT NULL,
  name			varchar(64)	DEFAULT '' NOT NULL,
  os			varchar(64)	DEFAULT '' NOT NULL,
  serialno		varchar(64)	DEFAULT '' NOT NULL,
  tag			varchar(64)	DEFAULT '' NOT NULL,
  macaddress		varchar(64)	DEFAULT '' NOT NULL,
  hardware		text		DEFAULT '' NOT NULL,
  software		text		DEFAULT '' NOT NULL,
  contact		text		DEFAULT '' NOT NULL,
  location		text		DEFAULT '' NOT NULL,
  notes			text		DEFAULT '' NOT NULL,
  PRIMARY KEY (hostid)
) with oids;

--
-- Table structure for table 'autoreg'
--

CREATE TABLE autoreg (
  id                    serial,
  priority              int4		DEFAULT '0' NOT NULL,
  pattern               varchar(255)	DEFAULT '' NOT NULL,
  hostid                int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (id)
) with oids;

--
-- Table structure for table 'valuemaps'
--

CREATE TABLE valuemaps (
  valuemapid		serial,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (valuemapid)
) with oids;

CREATE UNIQUE INDEX valuemaps_name on valuemaps (name);

--
-- Table structure for table 'mapping'
--

CREATE TABLE mappings (
  mappingid		serial,
  valuemapid		int4		DEFAULT '0' NOT NULL,
  value			varchar(64)	DEFAULT '' NOT NULL,
  newvalue		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (mappingid)
) with oids;

CREATE INDEX mappings_valuemapid on mappings (valuemapid);

--
-- Table structure for table 'housekeeper'
--

CREATE TABLE housekeeper (
  housekeeperid		serial,
  tablename		varchar(64)	DEFAULT '' NOT NULL,
  field			varchar(64)	DEFAULT '' NOT NULL,
  value			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (housekeeperid)
) with oids;

--
-- Table structure for table 'acknowledges'
--

CREATE TABLE acknowledges (
	acknowledgeid		serial,
	userid			int4		DEFAULT '0' NOT NULL,
	alarmid			int4		DEFAULT '0' NOT NULL,
	clock			int4		DEFAULT '0' NOT NULL,
	message			varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY (acknowledgeid)
--	FOREIGN KEY (alarmid) REFERENCES alarms,
--	FOREIGN KEY (userid) REFERENCES users
) with oids;

CREATE INDEX acknowledges_userid on acknowledges (userid);
CREATE INDEX acknowledges_alarmid on acknowledges (alarmid);
CREATE INDEX acknowledges_clock on acknowledges (clock);

--
-- Table structure for table 'applications'
--

CREATE TABLE applications (
        applicationid           serial,
        hostid                  int4            DEFAULT '0' NOT NULL,
        name                    varchar(255)    DEFAULT '' NOT NULL,
	templateid		int4		DEFAULT '0' NOT NULL,
        PRIMARY KEY (applicationid)
--        FOREIGN KEY hostid (hostid) REFERENCES hosts
) with oids;

CREATE INDEX applications_templateid on applications (templateid);
CREATE UNIQUE INDEX applications_hostid_key on applications (hostid,name);

--
-- Table structure for table 'items_applications'
--

CREATE TABLE items_applications (
        applicationid           int4          DEFAULT '0' NOT NULL,
        itemid                  int4          DEFAULT '0' NOT NULL,
        PRIMARY KEY (applicationid,itemid)
--        FOREIGN KEY (applicationid) REFERENCES applications,
 --       FOREIGN KEY (itemid) REFERENCES items
) with oids;

--
-- Table structure for table 'help_items'
--

CREATE TABLE help_items (
	itemtype	int4		DEFAULT '0' NOT NULL,
	key_		varchar(64)	DEFAULT '' NOT NULL,
	description	varchar(255)	DEFAULT '' NOT NULL,
	PRIMARY KEY	(itemtype, key_)
) with oids;


VACUUM ANALYZE;
