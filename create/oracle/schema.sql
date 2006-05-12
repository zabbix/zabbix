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
	if (:new.serviceid is null or :new.serviceid = 0) then
		select services_serviceid.nextval into :new.serviceid from dual;
	end if;
end;
/


--
-- Table structure for table 'services_links'
--

CREATE TABLE services_links (
	linkid		number(10)		NOT NULL,
	serviceupid		number(10)		DEFAULT '0' NOT NULL,
	servicedownid		number(10)		DEFAULT '0' NOT NULL,
	soft			number(3)		DEFAULT '0' NOT NULL,
	CONSTRAINT		services_links_pk	PRIMARY KEY (linkid)
);

CREATE INDEX services_links_servicedownid on services_links (servicedownid);
CREATE UNIQUE INDEX services_links_serviceupdownid on services_links (serviceupid,servicedownid);

create sequence services_links_linkid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger services_links_trigger
before insert on services_links
for each row
begin
	if (:new.linkid is null or :new.linkid = 0) then
		select services_links_linkid.nextval into :new.linkid from dual;
	end if;
end;
/

--
-- Table structure for table 'graphs_items'
--

CREATE TABLE graphs_items (
	gitemid		number(10)		NOT NULL,
	graphid		number(10)		DEFAULT '0' NOT NULL,
	itemid		number(10)		DEFAULT '0' NOT NULL,
	drawtype	number(10)		DEFAULT '0' NOT NULL,
	sortorder	number(10)		DEFAULT '0' NOT NULL,
	color		varchar2(32)	DEFAULT 'Dark Green' NOT NULL,
	yaxisside	number(3)		DEFAULT '1' NOT NULL,
	CONSTRAINT	graphs_items_pk	PRIMARY KEY (gitemid)
);

create sequence graphs_items_gitemid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger graphs_items_trigger
before insert on graphs_items
for each row
begin
	if (:new.gitemid is null or :new.gitemid = 0) then
		select graphs_items_gitemid.nextval into :new.gitemid from dual;
	end if;
end;
/

--
-- Table structure for table 'graphs'
--

CREATE TABLE graphs (
	graphid		number(10)		NOT NULL,
	name		varchar2(128)	DEFAULT '' NOT NULL,
	width		number(10)		DEFAULT '0' NOT NULL,
	height		number(10)		DEFAULT '0' NOT NULL,
	yaxistype	number(3)		DEFAULT '0' NOT NULL,
	yaxismin	number(16,4)	DEFAULT '0' NOT NULL,
	yaxismax	number(16,4)	DEFAULT '0' NOT NULL,
	templateid	number(10)		DEFAULT '0' NOT NULL,
	show_work_period	number(3)		DEFAULT '1' NOT NULL,
	show_triggers	number(3)		DEFAULT '1' NOT NULL,
	CONSTRAINT	graphs_pk	PRIMARY KEY (graphid)
);

CREATE INDEX graphs_name on graphs (name);

create sequence graphs_graphid
start with 1 
increment by 1 
nomaxvalue; 

create trigger graphs_trigger
before insert on graphs
for each row
begin
	if (:new.graphid is null or :new.graphid = 0) then
		select graphs_graphid.nextval into :new.graphid from dual;
	end if;
end;
/


--
-- Table structure for table 'sysmaps_links'
--

CREATE TABLE sysmaps_links (
	linkid		number(10)	NOT NULL,
	sysmapid	number(10)	DEFAULT '0' NOT NULL,
	selementid1	number(10)	DEFAULT '0' NOT NULL,
	selementid2	number(10)	DEFAULT '0' NOT NULL,
 -- may be NULL
	triggerid	number(10),
	drawtype_off	number(10)	DEFAULT '0' NOT NULL,
	color_off	varchar2(32)	DEFAULT 'Black' NOT NULL,
	drawtype_on	number(10)	DEFAULT '0' NOT NULL,
	color_on	varchar2(32)	DEFAULT 'Red' NOT NULL,
	CONSTRAINT	sysmaps_links_pk	PRIMARY KEY (linkid)
);

create sequence sysmaps_links_linkid
start with 1 
increment by 1 
nomaxvalue; 

create trigger sysmaps_links_trigger
before insert on sysmaps_links
for each row
begin
	if (:new.linkid is null or :new.linkid = 0) then
		select sysmaps_links_linkid.nextval into :new.linkid from dual;
	end if;
end;
/


--
-- Table structure for table 'sysmaps_elements'
--

CREATE TABLE sysmaps_elements (
	selementid	number(10)		NOT NULL,
	sysmapid	number(10)		DEFAULT '0' NOT NULL,
	elementid	number(10)		DEFAULT '0' NOT NULL,
	elementtype	number(10)		DEFAULT '0' NOT NULL,
	icon		varchar2(32)	DEFAULT 'Server' NOT NULL,
	icon_on		varchar2(32)	DEFAULT 'Server' NOT NULL,
	label		varchar2(128)	DEFAULT '' NOT NULL,
	label_location	number(3)		DEFAULT NULL,
	x		number(10)		DEFAULT '0' NOT NULL,
	y		number(10)		DEFAULT '0' NOT NULL,
	url		varchar2(255)	DEFAULT '' NOT NULL,
	CONSTRAINT	sysmaps_elements_pk	PRIMARY KEY (selementid)
);

create sequence sysmaps_elements_selementid
start with 1 
increment by 1 
nomaxvalue; 

create trigger sysmaps_elements_trigger
before insert on sysmaps_elements
for each row
begin
	if (:new.selementid is null or :new.selementid = 0) then
		select sysmaps_elements_selementid.nextval into :new.selementid from dual;
	end if;
end;
/

--
-- Table structure for table 'sysmaps'
--

CREATE TABLE sysmaps (
	sysmapid	number(10)		NOT NULL,
	name		varchar2(128)	DEFAULT '' NOT NULL,
	width		number(10)		DEFAULT '0' NOT NULL,
	height		number(10)		DEFAULT '0' NOT NULL,
	background	varchar2(64)	DEFAULT '' NOT NULL,
	label_type	number(10)		DEFAULT '0' NOT NULL,
	label_location	number(3)		DEFAULT '0' NOT NULL,
	CONSTRAINT	sysmaps_pk	PRIMARY KEY (sysmapid)
);

CREATE UNIQUE INDEX sysmaps_name on sysmaps (name);

create sequence sysmaps_sysmapid
start with 1 
increment by 1 
nomaxvalue; 

create trigger sysmaps_trigger
before insert on sysmaps
for each row
begin
	if (:new.sysmapid is null or :new.sysmapid = 0) then
		select sysmaps_sysmapid.nextval into :new.sysmapid from dual;
	end if;
end;
/

--
-- Table structure for table 'groups'
--

CREATE TABLE groups (
	groupid		number(10)	NOT NULL,
	name		varchar2(64)	DEFAULT '' NOT NULL,
	CONSTRAINT 	groups_pk PRIMARY KEY (groupid)
);

CREATE UNIQUE INDEX groups_name on groups (name);

create sequence groups_groupid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger groups_trigger
before insert on groups
for each row
begin
	if (:new.groupid is null or :new.groupid = 0) then
		select groups_groupid.nextval into :new.groupid from dual;
	end if;
end;
/

--
-- Table structure for table 'hosts_groups'
--

CREATE TABLE hosts_groups (
	hostid		number(10)		DEFAULT '0' NOT NULL,
	groupid		number(10)		DEFAULT '0' NOT NULL,
	CONSTRAINT 	hosts_groups_pk PRIMARY KEY (hostid, groupid)
);

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
	if (:new.alertid is null or :new.alertid = 0) then
		select alerts_alertid.nextval into :new.alertid from dual;
	end if;
end;
/

--
-- Table structure for table 'actions'
--

CREATE TABLE actions (
	actionid	number(10),
	userid		number(10)	DEFAULT '0' NOT NULL,
	delay		number(10)	DEFAULT '0' NOT NULL,
	subject		varchar2(255)	DEFAULT '' NOT NULL,
	message		varchar2(2048)	DEFAULT '' NOT NULL,
	nextcheck	number(10)	DEFAULT '0' NOT NULL,
	recipient	number(3)	DEFAULT '0' NOT NULL,
	maxrepeats	number(10)	DEFAULT '0' NOT NULL,
	repeatdelay	number(10)	DEFAULT '600' NOT NULL,
	source		number(3)	DEFAULT '0' NOT NULL,
	actiontype	number(3)	DEFAULT '0' NOT NULL,
	status		number(3)	DEFAULT '0' NOT NULL,
	scripts		varchar(2048)	DEFAULT '' NOT NULL,
	CONSTRAINT 	actions_pk PRIMARY KEY (actionid)
);

create sequence actions_actionid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger actions_trigger
before insert on actions
for each row
begin
	if (:new.actionid is null or :new.actionid = 0) then
		select actions_actionid.nextval into :new.actionid from dual;
	end if;
end;
/

--
-- Table structure for table 'conditions'
--

CREATE TABLE conditions (
	conditionid	number(10)	NOT NULL,
	actionid	number(10)	DEFAULT '0' NOT NULL,
	conditiontype	number(10)	DEFAULT '0' NOT NULL,
	operator	number(3)	DEFAULT '0' NOT NULL,
	value		varchar2(255)	DEFAULT '' NOT NULL,
	CONSTRAINT 	conditions_pk	PRIMARY KEY (conditionid)
);

CREATE INDEX conditions_actionid on conditions (actionid);

create sequence conditions_conditionid
start with 1 
increment by 1 
nomaxvalue; 

create trigger conditions_trigger
before insert on conditions
for each row
begin
	if (:new.conditionid is null or :new.conditionid = 0) then
		select conditions_conditionid.nextval into :new.conditionid from dual;
	end if;
end;
/

--

--
-- Table structure for table 'alarms'
--

CREATE TABLE alarms (
	alarmid		number(10)		NOT NULL,
	triggerid	number(10)		DEFAULT '0' NOT NULL,
	clock		number(10)		DEFAULT '0' NOT NULL,
	value		number(10)		DEFAULT '0' NOT NULL,
	acknowledged	number(3)		DEFAULT '0' NOT NULL,
	CONSTRAINT 	alarms_pk		 PRIMARY KEY (alarmid)
);

CREATE INDEX alarms_triggeridclock on alarms (triggerid, clock);
CREATE INDEX alarms_clock on alarms (clock);

create sequence alarms_alarmid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger alarms_trigger
before insert on alarms
for each row
begin
	if (:new.alarmid is null or :new.alarmid = 0) then
		select alarms_alarmid.nextval into :new.alarmid from dual;
	end if;
end;
/

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
	if (:new.functionid is null or :new.functionid = 0) then
		select functions_functionid.nextval into :new.functionid from dual;
	end if;
end;
/

--
-- Table structure for table 'history_uint'
--

CREATE TABLE history_uint (
	itemid		number(10)	DEFAULT '0' NOT NULL,
	clock		number(10)	DEFAULT '0' NOT NULL,
	value		number(20)	DEFAULT '0' NOT NULL
);

CREATE INDEX history_uint_itemidclock on history_uint (itemid, clock);

--
-- Table structure for table 'history_str'
--

CREATE TABLE history_str (
	itemid		number(10)		DEFAULT '0' NOT NULL,
	clock		number(10)		DEFAULT '0' NOT NULL,
	value		varchar2(255)		DEFAULT '' NOT NULL
);

CREATE INDEX history_str_itemidclock on history_str (itemid, clock);

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
	if (:new.hostid is null or :new.hostid = 0) then
		select hosts_hostid.nextval into :new.hostid from dual;
	end if;
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
	if (:new.itemid is null or :new.itemid = 0) then
		select items_itemid.nextval into :new.itemid from dual;
	end if;
end;
/

--
-- Table structure for table 'media'
--

CREATE TABLE media (
	mediaid		number(10) NOT NULL,
	userid		number(10) DEFAULT '0' NOT NULL,
	mediatypeid	number(10) DEFAULT '0' NOT NULL,
	sendto		varchar2(100) DEFAULT '' NOT NULL,
	active		number(10) DEFAULT '0' NOT NULL,
	severity	number(10) DEFAULT '63' NOT NULL,
	period		varchar2(100) DEFAULT '1-7,00:00-23:59' NOT NULL,
  	CONSTRAINT 	media_pk PRIMARY KEY (mediaid)
);

CREATE INDEX media_userid on media (userid);
CREATE INDEX media_mediatypeid on media (mediatypeid);

create sequence media_mediaid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger media_trigger
before insert on media
for each row
begin
	if (:new.mediaid is null or :new.mediaid = 0) then
		select media_mediaid.nextval into :new.mediaid from dual;
	end if;
end;
/

--
-- Table structure for table 'media'
--

CREATE TABLE media_type (
	mediatypeid	number(10) NOT NULL,
	type		number(10)	DEFAULT '0' NOT NULL,
	description	varchar2(100)	DEFAULT '' NOT NULL,
	smtp_server	varchar2(255)	DEFAULT '',
	smtp_helo	varchar2(255)	DEFAULT '',
	smtp_email	varchar2(255)	DEFAULT '',
	exec_path	varchar2(255)	DEFAULT '',
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
	if (:new.mediatypeid is null or :new.mediatypeid = 0) then
		select media_type_mediatypeid.nextval into :new.mediatypeid from dual;
	end if;
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
	if (:new.triggerid is null or :new.triggerid = 0) then
		select triggers_triggerid.nextval into :new.triggerid from dual;
	end if;
end;
/

--
-- Table structure for table 'trigger_depends'
--

CREATE TABLE trigger_depends (
	triggerid_down	number(10) DEFAULT '0' NOT NULL,
	triggerid_up	number(10) DEFAULT '0' NOT NULL,
  	CONSTRAINT 	triggers_depends_pk PRIMARY KEY (triggerid_down, triggerid_up)
);

CREATE INDEX triggers_depends_triggerid_up on trigger_depends (triggerid_up);

--
-- Table structure for table 'users'
--

CREATE TABLE users (
	userid		number(10)	NOT NULL,
	alias		varchar2(100)	DEFAULT '' NOT NULL,
	name		varchar2(100)	DEFAULT '' NOT NULL,
	surname		varchar2(100)	DEFAULT '' NOT NULL,
	passwd		varchar2(32)	DEFAULT '' NOT NULL,
	url		varchar2(255)	DEFAULT '' NOT NULL,
	autologout	number(10)	DEFAULT '900' NOT NULL,
	lang		varchar2(5)	DEFAULT 'en_gb' NOT NULL,
	refresh		number(10)	DEFAULT '30' NOT NULL,
  	CONSTRAINT 	users_pk PRIMARY KEY (userid)
);

CREATE UNIQUE INDEX users_alias on users (alias);

create sequence users_userid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger users_trigger
before insert on users
for each row
begin
	if (:new.userid is null or :new.userid = 0) then
		select users_userid.nextval into :new.userid from dual;
	end if;
end;
/

--
-- Table structure for table 'audit'
--

CREATE TABLE auditlog (
	auditid		number(10),
	userid		number(10)		DEFAULT '0' NOT NULL,
	clock		number(10)		DEFAULT '0' NOT NULL,
	action		number(10)		DEFAULT '0' NOT NULL,
	resource	number(10)		DEFAULT '0' NOT NULL,
	details		varchar2(128)	DEFAULT '0' NOT NULL,
  	CONSTRAINT 	auditlog_pk PRIMARY KEY (auditid)
);

CREATE INDEX auditlog_useridclock on audit (userid,clock);
CREATE INDEX auditlog_clock on audit (clock);

create sequence auditlog_auditid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger auditlog_trigger
before insert on auditlog
for each row
begin
	if (:new.auditid is null or :new.auditid = 0) then
		select auditlog_trigger.nextval into :new.auditid from dual;
	end if;
end;
/

--
-- Table structure for table 'sessions'
--

CREATE TABLE sessions (
	sessionid	varchar2(32)		DEFAULT '' NOT NULL,
	userid		number(10)		DEFAULT '0' NOT NULL,
	lastaccess	number(10)		DEFAULT '0' NOT NULL,
	CONSTRAINT 	sessions_pk PRIMARY KEY (sessionid)
);

--
-- Table structure for table 'rights'
--

CREATE TABLE rights (
	rightid		number(10)	NOT NULL,
	userid		number(10)	DEFAULT '0' NOT NULL,
	name		varchar2(255)	DEFAULT '' NOT NULL,
	permission	varchar2(1)	DEFAULT '' NOT NULL,
	id		number(10),
  	CONSTRAINT 	rights_pk PRIMARY KEY (rightid)
);

CREATE INDEX rights_userid on rights (userid);

create sequence rights_rightid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger rights_trigger
before insert on rights
for each row
begin
	if (:new.rightid is null or :new.rightid = 0) then
		select rights_rightid.nextval into :new.rightid from dual;
	end if;
end;
/


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
	servicealarmid	number(10)		NOT NULL,
	serviceid	number(10)		DEFAULT '0' NOT NULL,
	clock		number(10)		DEFAULT '0' NOT NULL,
	value		number(10)		DEFAULT '0' NOT NULL,
  	CONSTRAINT 	service_alarms_pk 	PRIMARY KEY (servicealarmid)
);

CREATE INDEX service_alarms_serviceidclock on service_alarms (serviceid,clock);
CREATE INDEX service_alarms_clock on service_alarms (clock);

create sequence service_alarms_servicealarmid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger service_alarms_trigger
before insert on service_alarms
for each row
begin
	if (:new.servicealarmid is null or :new.servicealarmid = 0) then
		select service_alarms_servicealarmid.nextval into :new.servicealarmid from dual;
	end if;
end;
/


--
-- Table structure for table 'profiles'
--

CREATE TABLE profiles (
	profileid	number(10)	NOT NULL,
	userid		number(10)	DEFAULT '0' NOT NULL,
	idx		varchar2(64)	DEFAULT '' NOT NULL,
	value		varchar2(255)	DEFAULT '' NOT NULL,
	valuetype	number(10)	DEFAULT 0 NOT NULL,
  	CONSTRAINT 	profiles_pk PRIMARY KEY (profileid)
);

CREATE UNIQUE INDEX profiles_userididx on profiles (userid, idx);

create sequence profiles_profileid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger profiles_trigger
before insert on profiles
for each row
begin
	if (:new.profileid is null or :new.profileid = 0) then
		select profiles_profileid.nextval into :new.profileid from dual;
	end if;
end;
/

--
-- Table structure for table 'screens'
--

CREATE TABLE screens (
	screenid	number(10)	NOT NULL,
	name		varchar2(255)	DEFAULT 'Screen' NOT NULL,
	cols		number(10)	DEFAULT '1' NOT NULL,
	rows		number(10)	DEFAULT '1' NOT NULL,
	CONSTRAINT 	screens_pk PRIMARY KEY (screenid)
);

create sequence screens_screenid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger screens_trigger
before insert on screens
for each row
begin
	if (:new.screenid is null or :new.screenid = 0) then
		select screens_screenid.nextval into :new.screenid from dual;
	end if;
end;
/


--
-- Table structure for table 'screens_items'
--

CREATE TABLE screens_items (
	screenitemid	number(10)	NOT NULL,
	screenid	number(10)	DEFAULT '0' NOT NULL,
	resource	number(10)	DEFAULT '0' NOT NULL,
	resourceid	number(10)	DEFAULT '0' NOT NULL,
	width		number(10)	DEFAULT '320' NOT NULL,
	height		number(10)	DEFAULT '200' NOT NULL,
	x		number(10)	DEFAULT '0' NOT NULL,
	y		number(10)	DEFAULT '0' NOT NULL,
	colspan		number(10)	DEFAULT '0' NOT NULL,
	rowspan		number(10)	DEFAULT '0' NOT NULL,
	elements	number(10)	DEFAULT '25' NOT NULL,
	valign		number(3)	DEFAULT '0' NOT NULL,
	halign		number(3)	DEFAULT '0' NOT NULL,
	style		number(10)	DEFAULT '0' NOT NULL,
	url		varchar2(255)	DEFAULT '' NOT NULL,
	CONSTRAINT 	screens_items_pk PRIMARY KEY (screenitemid)
);

create sequence screens_items_screenid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger screens_items_trigger
before insert on screens_items
for each row
begin
	if (:new.screenitemid is null or :new.screenitemid = 0) then
		select screens_items_screenid.nextval into :new.screenitemid from dual;
	end if;
end;
/

--
-- Table structure for table 'usrgrp'
--

CREATE TABLE usrgrp (
	usrgrpid	number(10)	NOT NULL,
	name		varchar2(64)	DEFAULT '' NOT NULL,
  	CONSTRAINT 	usrgrp_pk PRIMARY KEY (usrgrpid)
);

CREATE UNIQUE INDEX usrgrp_name on usrgrp (name);

create sequence usrgrp_usrgrpid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger usrgrp_trigger
before insert on usrgrp
for each row
begin
	if (:new.usrgrpid is null or :new.usrgrpid = 0) then
		select usrgrp_usrgrpid.nextval into :new.usrgrpid from dual;
	end if;
end;
/


--
-- Table structure for table 'users_groups'
--

CREATE TABLE users_groups (
	usrgrpid	number(10)		DEFAULT '0' NOT NULL,
	userid		number(10)		DEFAULT '0' NOT NULL,
	CONSTRAINT 	users_groups_pk	 PRIMARY KEY (usrgrpid,userid)
);

--
-- Table structure for table 'trends'
--

CREATE TABLE trends (
	itemid		number(10)	DEFAULT '0' NOT NULL,
	clock		number(10)	DEFAULT '0' NOT NULL,
	num		number(5)	DEFAULT '0' NOT NULL,
	value_min	number(16,4)	DEFAULT '0.0000' NOT NULL,
	value_avg	number(16,4)	DEFAULT '0.0000' NOT NULL,
	value_max	number(16,4)	DEFAULT '0.0000' NOT NULL,
	CONSTRAINT 	trends_pk	 PRIMARY KEY (itemid, clock)
);

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
	hosttemplateid	number(10)	NOT NULL,
	hostid		number(10)	DEFAULT '0' NOT NULL,
	templateid	number(10)	DEFAULT '0' NOT NULL,
	items		number(3)	DEFAULT '0' NOT NULL,
	triggers	number(3)	DEFAULT '0' NOT NULL,
	graphs		number(3)	DEFAULT '0' NOT NULL,
	CONSTRAINT 	hosts_templates_pk PRIMARY KEY (usrgrpid)
);

CREATE UNIQUE INDEX hosts_templates_id on hosts_templates (hostid, templateid);

create sequence hosts_templates_hosttemplateid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger hosts_templates_trigger
before insert on hosts_templates
for each row
begin
	if (:new.hosttemplateid is null or :new.hosttemplateid = 0) then
		select hosts_templates_hosttemplateid.nextval into :new.hosttemplateid from dual;
	end if;
end;
/


--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
	id		number(10)	NOT NULL,
	itemid		number(10)	DEFAULT '0' NOT NULL,
	clock		number(10)	DEFAULT '0' NOT NULL,
	timestamp	number(10)	DEFAULT '0' NOT NULL,
	source		varchar2(64)	DEFAULT '' NOT NULL,
	severity	number(10)	DEFAULT '0' NOT NULL,
	value		varvhar2(2048)	DEFAULT '' NOT NULL,
	CONSTRAINT 	history_log_pk	PRIMARY KEY (id)
);

CREATE INDEX history_log_itemidclock on history_log (itemidclock);

create sequence history_log_id 
start with 1 
increment by 1 
nomaxvalue; 

create trigger history_log_trigger
before insert on history_log
for each row
begin
	if (:new.id is null or :new.id = 0) then
		select history_log_id.nextval into :new.id from dual;
	end if;
end;
/

--
-- Table structure for table 'hosts_profiles'
--

CREATE TABLE hosts_profiles (
	hostid		number(10)	DEFAULT '0' NOT NULL,
	devicetype	varchar2(64)	DEFAULT '' NOT NULL,
	name		varchar2(64)	DEFAULT '' NOT NULL,
	os		varchar2(64)	DEFAULT '' NOT NULL,
	serialno	varchar2(64)	DEFAULT '' NOT NULL,
	tag		varchar2(64)	DEFAULT '' NOT NULL,
	macaddress	varchar2(64)	DEFAULT '' NOT NULL,
	hardware	varchar2(2048)	DEFAULT '' NOT NULL,
	software	varchar2(2048)	DEFAULT '' NOT NULL,
	contact		varchar2(2048)	DEFAULT '' NOT NULL,
	location	varchar2(2048)	DEFAULT '' NOT NULL,
	notes		varchar2(2048)	DEFAULT '' NOT NULL,
	CONSTRAINT 	hosts_profiles_pk	PRIMARY KEY (hostid)
);

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
	valuemapid		number(10)	NOT NULL,
	name			varchar2(64)	DEFAULT '' NOT NULL,
  	CONSTRAINT	 	valuemaps_pk PRIMARY KEY (valuemapid)
);

CREATE UNIQUE INDEX valuemaps_name on valuemaps (name);

create sequence valuemaps_valuemapid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger valuemaps_trigger
before insert on valuemaps
for each row
begin
	if (:new.valuemapid is null or :new.valuemapid = 0) then
		select valuemaps_valuemapid.nextval into :new.valuemapid from dual;
	end if;
end;
/

--
-- Table structure for table 'mapping'
--

CREATE TABLE mappings (
	mappingid		number(10)	NOT NULL,
	valuemapid		number(10)	DEFAULT '0' NOT NULL,
	value			varchar2(64)	DEFAULT '' NOT NULL,
	newvalue		varchar2(64)	DEFAULT '' NOT NULL,
  	CONSTRAINT	 	mappings_pk PRIMARY KEY (mappingid)
);

CREATE INDEX mappings_valuemapid on mappings (valuemapid);

create sequence mappings_mappingid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger mappings_trigger
before insert on mappings
for each row
begin
	if (:new.mappingid is null or :new.mappingid = 0) then
		select mappings_mappingid.nextval into :new.mappingid from dual;
	end if;
end;
/


--
-- Table structure for table 'housekeeper'
--

CREATE TABLE housekeeper (
	housekeeperid		number(10)	NOT NULL,
	tablename		varchar2(64)	DEFAULT '' NOT NULL,
	field			varchar2(64)	DEFAULT '' NOT NULL,
	value			number(10)	DEFAULT '0' NOT NULL,
  	CONSTRAINT	 	housekeeper_pk PRIMARY KEY (housekeeperid)
);

create sequence housekeeper_housekeeperid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger housekeeper_trigger
before insert on housekeeper
for each row
begin
	if (:new.housekeeperid is null or :new.housekeeperid = 0) then
		select housekeeper_housekeeperid.nextval into :new.housekeeperid from dual;
	end if;
end;
/

--
-- Table structure for table 'acknowledges'
--

CREATE TABLE acknowledges (
	acknowledgeid		number(10)	NOT NULL,
	userid			number(10)	DEFAULT '0' NOT NULL,
	alarmid			number(10)	DEFAULT '0' NOT NULL,
	clock			number(10)	DEFAULT '0' NOT NULL,
	message			varchar2(255)	DEFAULT '' NOT NULL,
  	CONSTRAINT	 	acknowledges_pk PRIMARY KEY (acknowledgeid)
);

CREATE INDEX acknowledges_userid on acknowledges (userid);
CREATE INDEX acknowledges_alarmid on acknowledges (alarmid);
CREATE INDEX acknowledges_clock on acknowledges (clock);

create sequence acknowledges_acknowledgeid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger acknowledges_trigger
before insert on acknowledges
for each row
begin
	if (:new.acknowledgeid is null or :new.acknowledgeid = 0) then
		select acknowledges_acknowledgeid.nextval into :new.acknowledgeid from dual;
	end if;
end;
/

--
-- Table structure for table 'acknowledges'

--
-- Table structure for table 'applications'
--

CREATE TABLE applications (
	applicationid           number(10)	NOT NULL,
	hostid                  number(10)	DEFAULT '0' NOT NULL,
	name                    varchar2(255)	DEFAULT '' NOT NULL,
	templateid		number(10)	DEFAULT '0' NOT NULL,
  	CONSTRAINT	 	applications_pk	 PRIMARY KEY (applicationid)
);

CREATE INDEX applications_hostid on applications (hostid);
CREATE INDEX applications_templateid on applications (templateid);
CREATE UNIQUE INDEX applications_name on applications (name);

create sequence applications_applicationid 
start with 1 
increment by 1 
nomaxvalue; 

create trigger applications_trigger
before insert on applications
for each row
begin
	if (:new.applicationid is null or :new.applicationid = 0) then
		select applications_applicationid.nextval into :new.applicationid from dual;
	end if;
end;
/

--
-- Table structure for table 'items_applications'
--

CREATE TABLE items_applications (
	applicationid           number(10)          DEFAULT '0' NOT NULL,
	itemid                  number(10)          DEFAULT '0' NOT NULL,
  	CONSTRAINT	 	items_applications_pk	 PRIMARY KEY (applicationid,itemid)
);
