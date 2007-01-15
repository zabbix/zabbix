CREATE TABLE acknowledges_tmp (
	acknowledgeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	eventid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	message		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (acknowledgeid)
);
CREATE INDEX acknowledges_1 on acknowledges_tmp (userid);
CREATE INDEX acknowledges_2 on acknowledges_tmp (eventid);
CREATE INDEX acknowledges_3 on acknowledges_tmp (clock);

insert into acknowledges_tmp select * from acknowledges;
drop table acknowledges;
alter table acknowledges_tmp rename acknowledges;

CREATE TABLE actions_tmp (
	actionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	subject		varchar(255)		DEFAULT ''	NOT NULL,
	message		blob		DEFAULT ''	NOT NULL,
	recipient		integer		DEFAULT '0'	NOT NULL,
	maxrepeats		integer		DEFAULT '0'	NOT NULL,
	repeatdelay		integer		DEFAULT '600'	NOT NULL,
	source		integer		DEFAULT '0'	NOT NULL,
	actiontype		integer		DEFAULT '0'	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	scripts		blob		DEFAULT ''	NOT NULL,
	PRIMARY KEY (actionid)
);

insert into actions_tmp select * from actions;
drop table actions;
alter table actions_tmp rename actions;

CREATE TABLE applications_tmp (
	applicationid		bigint unsigned		DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(255)		DEFAULT ''	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (applicationid)
);
CREATE INDEX applications_1 on applications_tmp (templateid);
CREATE UNIQUE INDEX applications_2 on applications_tmp (hostid,name);

insert into applications_tmp select * from applications;
drop table applications;
alter table applications_tmp rename applications;

CREATE TABLE auditlog_tmp (
	auditid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	action		integer		DEFAULT '0'	NOT NULL,
	resourcetype		integer		DEFAULT '0'	NOT NULL,
	details		varchar(128)		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (auditid)
);
CREATE INDEX auditlog_1 on auditlog_tmp (userid,clock);
CREATE INDEX auditlog_2 on auditlog_tmp (clock);

insert into auditlog_tmp select * from auditlog;
drop table auditlog;
alter table auditlog_tmp rename auditlog;

CREATE TABLE autoreg_tmp (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	priority		integer		DEFAULT '0'	NOT NULL,
	pattern		varchar(255)		DEFAULT ''	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
);

insert into autoreg_tmp select * from autoreg;
drop table autoreg;
alter table autoreg_tmp rename autoreg;

CREATE TABLE conditions_tmp (
	conditionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	actionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	conditiontype		integer		DEFAULT '0'	NOT NULL,
	operator		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (conditionid)
);
CREATE INDEX conditions_1 on conditions_tmp (actionid);

insert into conditions_tmp select * from conditions;
drop table conditions;
alter table conditions_tmp rename conditions;

CREATE TABLE config_tmp (
	configid		bigint unsigned		DEFAULT '0'	NOT NULL,
	alert_history		integer		DEFAULT '0'	NOT NULL,
	event_history		integer		DEFAULT '0'	NOT NULL,
	refresh_unsupported		integer		DEFAULT '0'	NOT NULL,
	work_period		varchar(100)		DEFAULT '1-5,00:00-24:00'	NOT NULL,
	PRIMARY KEY (configid)
);

insert into config_tmp select * from config;
drop table config;
alter table config_tmp rename config;

CREATE TABLE functions_tmp (
	functionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid		bigint unsigned		DEFAULT '0'	NOT NULL,
	lastvalue		varchar(255)			,
	function		varchar(12)		DEFAULT ''	NOT NULL,
	parameter		varchar(255)		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (functionid)
);
CREATE INDEX functions_1 on functions_tmp (triggerid);
CREATE INDEX functions_2 on functions_tmp (itemid,function,parameter);

insert into functions_tmp select * from functions;
drop table functions;
alter table functions_tmp rename functions;

CREATE TABLE graphs_tmp (
	graphid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	width		integer		DEFAULT '0'	NOT NULL,
	height		integer		DEFAULT '0'	NOT NULL,
	yaxistype		integer		DEFAULT '0'	NOT NULL,
	yaxismin		double(16,4)		DEFAULT '0'	NOT NULL,
	yaxismax		double(16,4)		DEFAULT '0'	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	show_work_period		integer		DEFAULT '1'	NOT NULL,
	show_triggers		integer		DEFAULT '1'	NOT NULL,
	graphtype		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (graphid)
);
CREATE INDEX graphs_graphs_1 on graphs_tmp (name);

insert into graphs_tmp select * from graphs;
drop table graphs;
alter table graphs_tmp rename graphs;

CREATE TABLE graphs_items_tmp (
	gitemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	graphid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	drawtype		integer		DEFAULT '0'	NOT NULL,
	sortorder		integer		DEFAULT '0'	NOT NULL,
	color		varchar(32)		DEFAULT 'Dark Green'	NOT NULL,
	yaxisside		integer		DEFAULT '1'	NOT NULL,
	calc_fnc		integer		DEFAULT '2'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	periods_cnt		integer		DEFAULT '5'	NOT NULL,
	PRIMARY KEY (gitemid)
);

insert into graphs_items_tmp select * from graphs_items;
drop table graphs_items;
alter table graphs_items_tmp rename graphs_items;

CREATE TABLE groups_tmp (
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (groupid)
);
CREATE INDEX groups_1 on groups_tmp (name);

insert into groups_tmp select * from groups;
drop table groups;
alter table groups_tmp rename groups;

CREATE TABLE help_items_tmp (
	itemtype		integer		DEFAULT '0'	NOT NULL,
	key_		varchar(255)		DEFAULT ''	NOT NULL,
	description		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (itemtype,key_)
);

insert into help_items_tmp select * from help_items;
drop table help_items;
alter table help_items_tmp rename help_items;

CREATE TABLE hosts_tmp (
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	host		varchar(64)		DEFAULT ''	NOT NULL,
	useip		integer		DEFAULT '1'	NOT NULL,
	ip		varchar(15)		DEFAULT '127.0.0.1'	NOT NULL,
	port		integer		DEFAULT '0'	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	disable_until		integer		DEFAULT '0'	NOT NULL,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	available		integer		DEFAULT '0'	NOT NULL,
	errors_from		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostid)
);
CREATE INDEX hosts_1 on hosts_tmp (host);
CREATE INDEX hosts_2 on hosts_tmp (status);

insert into hosts_tmp select * from hosts;
drop table hosts;
alter table hosts_tmp rename hosts;

CREATE TABLE hosts_groups_tmp (
	hostgroupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hostgroupid)
);
CREATE INDEX hosts_groups_groups_1 on hosts_groups_tmp (hostid,groupid);

insert into hosts_groups_tmp select * from hosts_groups;
drop table hosts_groups;
alter table hosts_groups_tmp rename hosts_groups;

CREATE TABLE hosts_profiles_tmp (
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	devicetype		varchar(64)		DEFAULT ''	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	os		varchar(64)		DEFAULT ''	NOT NULL,
	serialno		varchar(64)		DEFAULT ''	NOT NULL,
	tag		varchar(64)		DEFAULT ''	NOT NULL,
	macaddress		varchar(64)		DEFAULT ''	NOT NULL,
	hardware		blob		DEFAULT ''	NOT NULL,
	software		blob		DEFAULT ''	NOT NULL,
	contact		blob		DEFAULT ''	NOT NULL,
	location		blob		DEFAULT ''	NOT NULL,
	notes		blob		DEFAULT ''	NOT NULL,
	PRIMARY KEY (hostid)
);

insert into hosts_profiles_tmp select * from hosts_profiles;
drop table hosts_profiles;
alter table hosts_profiles_tmp rename hosts_profiles;

CREATE TABLE hosts_templates_tmp (
	hosttemplateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (hosttemplateid)
);
CREATE UNIQUE INDEX hosts_templates_1 on hosts_templates_tmp (hostid,templateid);

insert into hosts_templates_tmp select * from  hosts_templates;
drop table  hosts_templates;
alter table hosts_templates_tmp rename  hosts_templates;

CREATE TABLE housekeeper_tmp (
	housekeeperid		bigint unsigned		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	field		varchar(64)		DEFAULT ''	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (housekeeperid)
);

insert into housekeeper_tmp select * from housekeeper;
drop table housekeeper;
alter table housekeeper_tmp rename housekeeper;

CREATE TABLE image_tmp (
	imageid		bigint unsigned		DEFAULT '0'	NOT NULL,
	imagetype		integer		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT '0'	NOT NULL,
	image		longblob		DEFAULT ''	NOT NULL,
	PRIMARY KEY (imageid)
);
CREATE INDEX images_1 on images_tmp (imagetype,name);

insert into image_tmp select * from image;
drop table image;
alter table image_tmp rename image;

CREATE TABLE items_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	snmp_community		varchar(64)		DEFAULT ''	NOT NULL,
	snmp_oid		varchar(255)		DEFAULT ''	NOT NULL,
	snmp_port		integer		DEFAULT '161'	NOT NULL,
	hostid		bigint unsigned		DEFAULT '0'	NOT NULL,
	description		varchar(255)		DEFAULT ''	NOT NULL,
	key_		varchar(255)		DEFAULT ''	NOT NULL,
	delay		integer		DEFAULT '0'	NOT NULL,
	history		integer		DEFAULT '90'	NOT NULL,
	trends		integer		DEFAULT '365'	NOT NULL,
	nextcheck		integer		DEFAULT '0'	NOT NULL,
	lastvalue		varchar(255)			NULL,
	lastclock		integer			NULL,
	prevvalue		varchar(255)			NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	value_type		integer		DEFAULT '0'	NOT NULL,
	trapper_hosts		varchar(255)		DEFAULT ''	NOT NULL,
	units		varchar(10)		DEFAULT ''	NOT NULL,
	multiplier		integer		DEFAULT '0'	NOT NULL,
	delta		integer		DEFAULT '0'	NOT NULL,
	prevorgvalue		double(16,4)			NULL,
	snmpv3_securityname		varchar(64)		DEFAULT ''	NOT NULL,
	snmpv3_securitylevel		integer		DEFAULT '0'	NOT NULL,
	snmpv3_authpassphrase		varchar(64)		DEFAULT ''	NOT NULL,
	snmpv3_privpassphrase		varchar(64)		DEFAULT ''	NOT NULL,
	formula		varchar(255)		DEFAULT '0'	NOT NULL,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	lastlogsize		integer		DEFAULT '0'	NOT NULL,
	logtimefmt		varchar(64)		DEFAULT ''	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	valuemapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	delay_flex		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (itemid)
);
CREATE UNIQUE INDEX items_1 on items_tmp (hostid,key_);
CREATE INDEX items_2 on items_tmp (nextcheck);
CREATE INDEX items_3 on items_tmp (status);

insert into items_tmp select * from items;
drop table items;
alter table image_tmp rename items;

CREATE TABLE items_applications_tmp (
	itemappid		bigint unsigned		DEFAULT '0'	NOT NULL,
	applicationid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (itemappid)
);
CREATE INDEX items_applications_1 on items_applications_tmp (applicationid,itemid);

insert into items_applications_tmp select * from items_applications;
drop table items_applications;
alter table items_applications_tmp rename items_applications;

CREATE TABLE mappings_tmp (
	mappingid		bigint unsigned		DEFAULT '0'	NOT NULL,
	valuemapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	value		varchar(64)		DEFAULT ''	NOT NULL,
	newvalue		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (mappingid)
);
CREATE INDEX mappings_1 on mappings_tmp (valuemapid);

insert into mappings_tmp select * from mappings;
drop table mappings;
alter table mappings_tmp rename mappings;

CREATE TABLE media_tmp (
	mediaid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	mediatypeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	sendto		varchar(100)		DEFAULT ''	NOT NULL,
	active		integer		DEFAULT '0'	NOT NULL,
	severity		integer		DEFAULT '63'	NOT NULL,
	period		varchar(100)		DEFAULT '1-7,00:00-23:59'	NOT NULL,
	PRIMARY KEY (mediaid)
);
CREATE INDEX media_1 on media_tmp (userid);
CREATE INDEX media_2 on media_tmp (mediatypeid);

insert into media_tmp select * from media;
drop table media_tmp;
alter table media_tmp rename media_tmp;

CREATE TABLE media_type_tmp (
	mediatypeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	description		varchar(100)		DEFAULT ''	NOT NULL,
	smtp_server		varchar(255)		DEFAULT ''	NOT NULL,
	smtp_helo		varchar(255)		DEFAULT ''	NOT NULL,
	smtp_email		varchar(255)		DEFAULT ''	NOT NULL,
	exec_path		varchar(255)		DEFAULT ''	NOT NULL,
	gsm_modem		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (mediatypeid)
);

insert into media_type_tmp select * from media_type;
drop table media_type;
alter table media_type_tmp rename media_type;

CREATE TABLE profiles_tmp (
	profileid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	idx		varchar(64)		DEFAULT ''	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	valuetype		integer		DEFAULT 0	NOT NULL,
	PRIMARY KEY (profileid)
);
CREATE UNIQUE INDEX profiles_1 on profiles_tmp (userid,idx);

insert into profiles_tmp select * from profiles;
drop table profiles_tmp;
alter table profiles_tmp rename profiles_tmp;

CREATE TABLE rights_tmp (
	rightid		bigint unsigned		DEFAULT '0'	NOT NULL,
	groupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	permission		integer		DEFAULT '0'	NOT NULL,
	id		bigint unsigned			,
	PRIMARY KEY (rightid)
);
CREATE INDEX rights_1 on rights_tmp (groupid);

insert into rights_tmp select * from rights;
drop table rights;
alter table rights_tmp rename rights;

CREATE TABLE screens_tmp (
	screenid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(255)		DEFAULT 'Screen'	NOT NULL,
	hsize		integer		DEFAULT '1'	NOT NULL,
	vsize		integer		DEFAULT '1'	NOT NULL,
	PRIMARY KEY (screenid)
);

insert into screens_tmp select * from screens;
drop table screens;
alter table screens_tmp rename screens;

CREATE TABLE screens_items_tmp (
	screenitemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	screenid		bigint unsigned		DEFAULT '0'	NOT NULL,
	resourcetype		integer		DEFAULT '0'	NOT NULL,
	resourceid		bigint unsigned		DEFAULT '0'	NOT NULL,
	width		integer		DEFAULT '320'	NOT NULL,
	height		integer		DEFAULT '200'	NOT NULL,
	x		integer		DEFAULT '0'	NOT NULL,
	y		integer		DEFAULT '0'	NOT NULL,
	colspan		integer		DEFAULT '0'	NOT NULL,
	rowspan		integer		DEFAULT '0'	NOT NULL,
	elements		integer		DEFAULT '25'	NOT NULL,
	valign		integer		DEFAULT '0'	NOT NULL,
	halign		integer		DEFAULT '0'	NOT NULL,
	style		integer		DEFAULT '0'	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (screenitemid)
);

insert into screens_items_tmp select * from screens_items;
drop table screens_items_tmp;
alter table screens_items_tmp rename screens_items_tmp;

CREATE TABLE services_tmp (
	serviceid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	algorithm		integer		DEFAULT '0'	NOT NULL,
	triggerid		bigint unsigned			,
	showsla		integer		DEFAULT '0'	NOT NULL,
	goodsla		double(5,2)		DEFAULT '99.9'	NOT NULL,
	sortorder		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (serviceid)
);

insert into services_tmp select * from services;
drop table services_tmp;
alter table services_tmp rename services_tmp;

CREATE TABLE service_alarms_tmp (
	servicealarmid		bigint unsigned		DEFAULT '0'	NOT NULL,
	serviceid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (servicealarmid)
);
CREATE INDEX service_alarms_1 on service_alarms_tmp (serviceid,clock);
CREATE INDEX service_alarms_2 on service_alarms_tmp (clock);

insert into service_alarms_tmp select * from service_alarms;
drop table service_alarms;
alter table service_alarms_tmp rename service_alarms;

CREATE TABLE services_links_tmp (
	linkid		bigint unsigned		DEFAULT '0'	NOT NULL,
	serviceupid		bigint unsigned		DEFAULT '0'	NOT NULL,
	servicedownid		bigint unsigned		DEFAULT '0'	NOT NULL,
	soft		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (linkid)
);
CREATE INDEX services_links_links_1 on services_links_tmp (servicedownid);
CREATE UNIQUE INDEX services_links_links_2 on services_links_tmp (serviceupid,servicedownid);

insert into services_links_tmp select * from services_links;
drop table services_links;
alter table services_links_tmp rename services_links;

CREATE TABLE sessions_tmp (
	sessionid		varchar(32)		DEFAULT ''	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	lastaccess		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sessionid)
);

insert into sessions_tmp select * from sessions;
drop table sessions_tmp;
alter table sessions_tmp rename sessions_tmp;

CREATE TABLE sysmaps_links_tmp (
	linkid		bigint unsigned		DEFAULT '0'	NOT NULL,
	sysmapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	selementid1		bigint unsigned		DEFAULT '0'	NOT NULL,
	selementid2		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid		bigint unsigned			,
	drawtype_off		integer		DEFAULT '0'	NOT NULL,
	color_off		varchar(32)		DEFAULT 'Black'	NOT NULL,
	drawtype_on		integer		DEFAULT '0'	NOT NULL,
	color_on		varchar(32)		DEFAULT 'Red'	NOT NULL,
	PRIMARY KEY (linkid)
);

insert into sysmaps_links_tmp select * from sysmaps_links;
drop table sysmaps_links;
alter table sysmaps_links_tmp rename sysmaps_links;

CREATE TABLE sysmaps_elements_tmp (
	selementid		bigint unsigned		DEFAULT '0'	NOT NULL,
	sysmapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	elementid		bigint unsigned		DEFAULT '0'	NOT NULL,
	elementtype		integer		DEFAULT '0'	NOT NULL,
	iconid_off		bigint unsigned		DEFAULT '0'	NOT NULL,
	iconid_on		bigint unsigned		DEFAULT '0'	NOT NULL,
	label		varchar(128)		DEFAULT ''	NOT NULL,
	label_location		integer			NULL,
	x		integer		DEFAULT '0'	NOT NULL,
	y		integer		DEFAULT '0'	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (selementid)
);

insert into sysmaps_elements_tmp select * from sysmaps_elements;
drop table sysmaps_elements;
alter table sysmaps_elements_tmp rename sysmaps_elements;

CREATE TABLE sysmaps_tmp (
	sysmapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	width		integer		DEFAULT '0'	NOT NULL,
	height		integer		DEFAULT '0'	NOT NULL,
	backgroundid		bigint unsigned		DEFAULT '0'	NOT NULL,
	label_type		integer		DEFAULT '0'	NOT NULL,
	label_location		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sysmapid)
);
CREATE INDEX sysmaps_1 on sysmaps_tmp (name);

insert into sysmaps_tmp select * from sysmaps;
drop table sysmaps;
alter table sysmaps_tmp rename sysmaps;

CREATE TABLE triggers_tmp (
	triggerid		bigint unsigned		DEFAULT '0'	NOT NULL,
	expression		varchar(255)		DEFAULT ''	NOT NULL,
	description		varchar(255)		DEFAULT ''	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	priority		integer		DEFAULT '0'	NOT NULL,
	lastchange		integer		DEFAULT '0'	NOT NULL,
	dep_level		integer		DEFAULT '0'	NOT NULL,
	comments		blob			,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	templateid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (triggerid)
);
CREATE INDEX triggers_1 on triggers_tmp (status);
CREATE INDEX triggers_2 on triggers_tmp (value);

insert into triggers_tmp select * from triggers;
drop table triggers_tmp;
alter table triggers_tmp rename triggers_tmp;

CREATE TABLE trigger_depends_tmp (
	triggerdepid		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid_down		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid_up		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (triggerdepid)
);
CREATE INDEX trigger_depends_1 on trigger_depends_tmp (triggerid_down,triggerid_up);
CREATE INDEX trigger_depends_2 on trigger_depends_tmp (triggerid_up);

insert into trigger_depends_tmp select * from trigger_depends;
drop table trigger_depends;
alter table trigger_depends_tmp rename trigger_depends;

CREATE TABLE users_tmp (
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	alias		varchar(100)		DEFAULT ''	NOT NULL,
	name		varchar(100)		DEFAULT ''	NOT NULL,
	surname		varchar(100)		DEFAULT ''	NOT NULL,
	passwd		char(32)		DEFAULT ''	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	autologout		integer		DEFAULT '900'	NOT NULL,
	lang		varchar(5)		DEFAULT 'en_gb'	NOT NULL,
	refresh		integer		DEFAULT '30'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (userid)
);
CREATE INDEX users_1 on users_tmp (alias);

insert into users_tmp select * from users;
drop table users;
alter table users_tmp rename users;

CREATE TABLE usrgrp_tmp (
	usrgrpid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (usrgrpid)
);
CREATE INDEX usrgrp_1 on usrgrp_tmp (name);

insert into usrgrp_tmp select * from usrgrp;
drop table usrgrp;
alter table usrgrp_tmp rename usrgrp;

CREATE TABLE users_groups_tmp (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	usrgrpid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX users_groups_1 on users_groups_tmp (usrgrpid,userid);

insert into users_groups_tmp select * from users_groups;
drop table users_groups;
alter table users_groups_tmp rename users_groups;

CREATE TABLE valuemaps_tmp (
	valuemapid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (valuemapid)
);
CREATE INDEX valuemaps_1 on valuemaps_tmp (name);

insert into valuemaps_tmp select * from valuemaps;
drop table valuemaps;
alter table valuemaps_tmp rename valuemaps;
