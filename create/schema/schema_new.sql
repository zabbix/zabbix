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
-- Do not use spaces
--

TABLE|nodes|nodeid|no_sync
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|NO_SYNC
FIELD		|name		|t_varchar(64)	|'0'	|NOT NULL	|NO_SYNC
FIELD		|timezone	|t_integer	|'0'	|NOT NULL	|
FIELD		|ip		|t_varchar(15)	|''	|NOT NULL	|
FIELD		|port		|t_integer	|'0'	|NOT NULL	|
FIELD		|slave_history	|t_integer	|'0'	|NOT NULL	|
FIELD		|slave_trends	|t_integer	|'0'	|NOT NULL	|
FIELD		|event_lastid	|t_id		|'0'	|NOT NULL	|
FIELD		|event_maxid	|t_id		|'0'	|NOT NULL	|
FIELD		|history_lastid	|t_bigint	|'0'	|NOT NULL	|
FIELD		|history_str_lastid|t_bigint	|'0'	|NOT NULL	|
FIELD		|history_uint_lastid|t_bigint	|'0'	|NOT NULL	|
FIELD		|nodetype	|t_integer	|'0'	|NOT NULL	|
FIELD		|masterid	|t_id		|'0'	|NOT NULL	|

TABLE|node_cksum|cksumid|
FIELD		|cksumid	|t_id		|'0'	|NOT NULL	|
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|
FIELD		|tablename	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|fieldname	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|recordid	|t_id		|'0'	|NOT NULL	|
FIELD		|cksumtype	|t_integer	|'0'	|NOT NULL	|
FIELD		|cksum		|t_char(32)	|''	|NOT NULL	|
INDEX		|cksum_1	|nodeid,tablename,fieldname,recordid,cksumtype

TABLE|node_configlog|nodeid,conflogid|DB_NOSYNC
FIELD		|conflogid	|t_id		|'0'	|NOT NULL	|
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|
FIELD		|tablename	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|recordid	|t_id		|'0'	|NOT NULL	|
FIELD		|operation	|t_integer	|'0'	|NOT NULL	|
FIELD		|sync_master	|t_integer	|'0'	|NOT NULL	|
FIELD		|sync_slave	|t_integer	|'0'	|NOT NULL	|
INDEX		|configlog_1	|conflogid
INDEX		|configlog_2	|nodeid,tablename

TABLE|services|serviceid|
FIELD		|serviceid	|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|
FIELD		|status		|t_integer	|'0'	|NOT NULL	|
FIELD		|algorithm	|t_integer	|'0'	|NOT NULL	|
FIELD		|triggerid	|t_id		|	|		|
FIELD		|showsla	|t_integer	|'0'	|NOT NULL	|
FIELD		|goodsla	|t_double(5,2)	|'99.9'	|NOT NULL	|
FIELD		|sortorder	|t_integer	|'0'	|NOT NULL	|

TABLE|services_times|timeid|
FIELD		|timeid		|t_id		|'0'	|NOT NULL	|
FIELD		|serviceid	|t_id		|'0'	|NOT NULL	|
FIELD		|type		|t_integer	|'0'	|NOT NULL	|
FIELD		|ts_from	|t_integer	|'0'	|NOT NULL	|
FIELD		|ts_to		|t_integer	|'0'	|NOT NULL	|
FIELD		|note		|t_varchar(255)	|''	|NOT NULL	|
INDEX		|times_1	|serviceid,type,ts_from,ts_to

TABLE|services_links|linkid|
FIELD		|linkid		|t_id		|'0'	|NOT NULL	|
FIELD		|serviceupid	|t_id		|'0'	|NOT NULL	|
FIELD		|servicedownid	|t_id		|'0'	|NOT NULL	|
FIELD		|soft		|t_integer	|'0'	|NOT NULL	|
INDEX		|links_1	|servicedownid
INDEX		|links_2	|serviceupid,servicedownid

TABLE|graphs_items|gitemid|
FIELD		|gitemid	|t_id		|'0'	|NOT NULL	|
FIELD		|graphid	|t_id		|'0'	|NOT NULL	|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|drawtype	|t_integer	|'0'	|NOT NULL	|
FIELD		|sortorder	|t_integer	|'0'	|NOT NULL	|
FIELD		|color		|t_varchar(32)	|'Dark Green'	|NOT NULL	|
FIELD		|yaxisside	|t_integer	|'1'	|NOT NULL	|
FIELD		|calc_fnc	|t_integer	|'2'	|NOT NULL	|
FIELD		|type		|t_integer	|'0'	|NOT NULL	|
FIELD		|periods_cnt	|t_integer	|'5'	|NOT NULL	|

TABLE|graphs|graphid|
FIELD		|graphid	|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|
FIELD		|width		|t_integer	|'0'	|NOT NULL	|
FIELD		|height		|t_integer	|'0'	|NOT NULL	|
FIELD		|yaxistype	|t_integer	|'0'	|NOT NULL	|
FIELD		|yaxismin	|t_double(16,4)	|'0'	|NOT NULL	|
FIELD		|yaxismax	|t_double(16,4)	|'0'	|NOT NULL	|
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|
FIELD		|show_work_period|t_integer	|'1'	|NOT NULL	|
FIELD		|show_triggers	|t_integer	|'1'	|NOT NULL	|
FIELD		|graphtype	|t_integer	|'0'	|NOT NULL	|
INDEX		|graphs_1	|name

TABLE|sysmaps_links|linkid|
FIELD		|linkid		|t_id		|'0'	|NOT NULL	|
FIELD		|sysmapid	|t_id		|'0'	|NOT NULL	|
FIELD		|selementid1	|t_id		|'0'	|NOT NULL	|
FIELD		|selementid2	|t_id		|'0'	|NOT NULL	|
FIELD		|triggerid	|t_id		|	|		|
FIELD		|drawtype_off	|t_integer	|'0'	|NOT NULL	|
FIELD		|color_off	|t_varchar(32)	|'Black'|NOT NULL	|
FIELD		|drawtype_on	|t_integer	|'0'	|NOT NULL	|
FIELD		|color_on	|t_varchar(32)	|'Red'	|NOT NULL	|

TABLE|sysmaps_elements|selementid|
FIELD		|selementid	|t_id		|'0'	|NOT NULL	|
FIELD		|sysmapid	|t_id		|'0'	|NOT NULL	|
FIELD		|elementid	|t_id		|'0'	|NOT NULL	|
FIELD		|elementtype	|t_integer	|'0'	|NOT NULL	|
FIELD		|iconid_off	|t_bigint		|'0'	|NOT NULL	|
FIELD		|iconid_on	|t_bigint		|'0'	|NOT NULL	|
FIELD		|label		|t_varchar(128)	|''	|NOT NULL	|
FIELD		|label_location	|t_integer	|	|NULL		|
FIELD		|x		|t_integer	|'0'	|NOT NULL	|
FIELD		|y		|t_integer	|'0'	|NOT NULL	|
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|

TABLE|sysmaps|sysmapid|
FIELD		|sysmapid	|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|
FIELD		|width		|t_integer	|'0'	|NOT NULL	|
FIELD		|height		|t_integer	|'0'	|NOT NULL	|
FIELD		|backgroundid	|t_bigint		|'0'	|NOT NULL	|
FIELD		|label_type	|t_integer	|'0'	|NOT NULL	|
FIELD		|label_location	|t_integer	|'0'	|NOT NULL	|
INDEX		|1		|name

TABLE|config|configid|
FIELD		|configid	|t_id		|'0'	|NOT NULL	|
FIELD		|alert_history	|t_integer	|'0'	|NOT NULL	|
FIELD		|event_history	|t_integer	|'0'	|NOT NULL	|
FIELD		|refresh_unsupported|t_integer	|'0'	|NOT NULL	|
FIELD		|work_period	|t_varchar(100)	|'1-5,00:00-24:00'	|NOT NULL	|

TABLE|groups|groupid|
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|
INDEX		|1		|name

TABLE|hosts_groups|hostgroupid|
FIELD		|hostgroupid	|t_id		|'0'	|NOT NULL	|
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|
INDEX		|groups_1	|hostid,groupid

TABLE|alerts|alertid|
FIELD		|alertid	|t_id		|'0'	|NOT NULL	|
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|mediatypeid	|t_id		|'0'	|NOT NULL	|
FIELD		|sendto		|t_varchar(100)	|''	|NOT NULL	|
FIELD		|subject	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|message	|t_blob		|''	|NOT NULL	|
FIELD		|status		|t_integer	|'0'	|NOT NULL	|
FIELD		|retries	|t_integer	|'0'	|NOT NULL	|
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|
FIELD		|repeats	|t_integer	|'0'	|NOT NULL	|
FIELD		|maxrepeats	|t_integer	|'0'	|NOT NULL	|
FIELD		|nextcheck	|t_integer	|'0'	|NOT NULL	|
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|
INDEX		|1		|actionid
INDEX		|2		|clock
INDEX		|3		|triggerid
INDEX		|4		|status,retries
INDEX		|5		|mediatypeid
INDEX		|6		|userid

TABLE|actions|actionid|
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|subject	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|message	|t_blob		|''	|NOT NULL	|
FIELD		|recipient	|t_integer	|'0'	|NOT NULL	|
FIELD		|maxrepeats	|t_integer	|'0'	|NOT NULL	|
FIELD		|repeatdelay	|t_integer	|'600'	|NOT NULL	|
FIELD		|source		|t_integer	|'0'	|NOT NULL	|
FIELD		|actiontype	|t_integer	|'0'	|NOT NULL	|
FIELD		|status		|t_integer	|'0'	|NOT NULL	|
FIELD		|scripts	|t_blob		|''	|NOT NULL	|

TABLE|conditions|conditionid|
FIELD		|conditionid	|t_id		|'0'	|NOT NULL	|
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|
FIELD		|conditiontype	|t_integer	|'0'	|NOT NULL	|
FIELD		|operator	|t_integer	|'0'	|NOT NULL	|
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|
INDEX		|1		|actionid

TABLE|events|eventid|
FIELD		|eventid		|t_id		|'0'	|NOT NULL	|
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|value		|t_integer	|'0'	|NOT NULL	|
FIELD		|acknowledged	|t_integer	|'0'	|NOT NULL	|
INDEX		|1		|triggerid,clock
INDEX		|2		|clock

TABLE|functions|functionid|
FIELD		|functionid	|t_id		|'0'	|NOT NULL	|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|
FIELD		|lastvalue	|t_varchar(255)	|	|		|
FIELD		|function	|t_varchar(12)	|''	|NOT NULL	|
FIELD		|parameter	|t_varchar(255)	|'0'	|NOT NULL	|
INDEX		|1		|triggerid
INDEX		|2		|itemid,function,parameter

TABLE|history||NO_SYNC
FIELD		|itemid		|t_id		|'0'		|NOT NULL	|
FIELD		|clock		|t_time		|'0'		|NOT NULL	|
FIELD		|value		|t_double(16,4)	|'0.0000'	|NOT NULL	|
INDEX		|1		|itemid,clock

TABLE|history_sync|id|NO_SYNC
FIELD		|id		|t_serial		|		|		|
FIELD		|nodeid		|t_id		|'0'		|NOT NULL	|
FIELD		|itemid		|t_id		|'0'		|NOT NULL	|
FIELD		|clock		|t_time		|'0'		|NOT NULL	|
FIELD		|value		|t_double(16,4)	|'0.0000'	|NOT NULL	|
INDEX		|1		|nodeid,id

TABLE|history_uint||NO_SYNC
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|value		|t_bigint		|'0'	|NOT NULL	|
INDEX		|1		|itemid,clock

TABLE|history_uint_sync|id|NO_SYNC
FIELD		|id		|t_serial		|	|		|
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|value		|t_bigint		|'0'	|NOT NULL	|
INDEX		|1		|nodeid,id

TABLE|history_str||NO_SYNC
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|
INDEX		|1		|itemid,clock

TABLE|history_str_sync|id|NO_SYNC
FIELD		|id		|t_serial		|	|		|
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|
INDEX		|1		|nodeid,id

TABLE|hosts|hostid
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|
FIELD		|host		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|useip		|t_integer	|'1'	|NOT NULL	|
FIELD		|ip		|t_varchar(15)	|'127.0.0.1'|NOT NULL	|
FIELD		|port		|t_integer	|'0'	|NOT NULL	|
FIELD		|status		|t_integer	|'0'	|NOT NULL	|
FIELD		|disable_until	|t_integer	|'0'	|NOT NULL	|
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|
FIELD		|available	|t_integer	|'0'	|NOT NULL	|
FIELD		|errors_from	|t_integer	|'0'	|NOT NULL	|
INDEX		|1		|host
INDEX		|2		|status

TABLE|items|itemid
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|type		|t_integer	|'0'	|NOT NULL	|
FIELD		|snmp_community	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|snmp_oid	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|snmp_port	|t_integer	|'161'	|NOT NULL	|
FIELD		|hostid		|t_bigint		|'0'	|NOT NULL	|
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|key_		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|
FIELD		|history	|t_integer	|'90'	|NOT NULL	|
FIELD		|trends		|t_integer	|'365'	|NOT NULL	|
FIELD		|nextcheck	|t_time		|'0'	|NOT NULL	|
FIELD		|lastvalue	|t_varchar(255)	|	|NULL		|
FIELD		|lastclock	|t_time		|	|NULL		|
FIELD		|prevvalue	|t_varchar(255)	|	|NULL		|
FIELD		|status		|t_integer	|'0'	|NOT NULL	|
FIELD		|value_type	|t_integer	|'0'	|NOT NULL	|
FIELD		|trapper_hosts	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|units		|t_varchar(10)	|''	|NOT NULL	|
FIELD		|multiplier	|t_integer	|'0'	|NOT NULL	|
FIELD		|delta		|t_integer	|'0'	|NOT NULL	|
FIELD		|prevorgvalue	|t_double(16,4)	|	|NULL		|
FIELD		|snmpv3_securityname|t_varchar(64)|''	|NOT NULL	|
FIELD		|snmpv3_securitylevel|t_integer	|'0'	|NOT NULL	|
FIELD		|snmpv3_authpassphrase|t_varchar(64)|''	|NOT NULL	|
FIELD		|snmpv3_privpassphrase|t_varchar(64)|''	|NOT NULL	|

FIELD		|formula	|t_varchar(255)	|'0'	|NOT NULL	|
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|

FIELD		|lastlogsize	|t_integer	|'0'	|NOT NULL	|
FIELD		|logtimefmt	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|templateid	|t_bigint		|'0'	|NOT NULL	|
FIELD		|valuemapid	|t_bigint		|'0'	|NOT NULL	|
FIELD		|delay_flex	|t_varchar(255)	|''	|NOT NULL	|
INDEX		|1		|hostid,key_
INDEX		|2		|nextcheck
INDEX		|3		|status

TABLE|media|mediaid
FIELD		|mediaid	|t_id		|'0'	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|mediatypeid	|t_id		|'0'	|NOT NULL	|
FIELD		|sendto		|t_varchar(100)	|''	|NOT NULL	|
FIELD		|active		|t_integer	|'0'	|NOT NULL	|
FIELD		|severity	|t_integer	|'63'	|NOT NULL	|
FIELD		|period		|t_varchar(100)	|'1-7,00:00-23:59'|NOT NULL	|
INDEX		|1		|userid
INDEX		|2		|mediatypeid

TABLE|media_type|mediatypeid
FIELD		|mediatypeid	|t_id		|'0'	|NOT NULL	|
FIELD		|type		|t_integer	|'0'	|NOT NULL	|
FIELD		|description	|t_varchar(100)	|''	|NOT NULL	|
FIELD		|smtp_server	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|smtp_helo	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|smtp_email	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|exec_path	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|gsm_modem	|t_varchar(255)	|''	|NOT NULL	|

TABLE|triggers|triggerid|
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|
FIELD		|expression	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|
FIELD		|status		|t_integer	|'0'	|NOT NULL	|
FIELD		|value		|t_integer	|'0'	|NOT NULL	|
FIELD		|priority	|t_integer	|'0'	|NOT NULL	|
FIELD		|lastchange	|t_integer	|'0'	|NOT NULL	|
FIELD		|dep_level	|t_integer	|'0'	|NOT NULL	|
FIELD		|comments	|t_blob		|	|		|
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|
INDEX		|1		|status
INDEX		|2		|value

TABLE|trigger_depends|triggerdepid|
FIELD		|triggerdepid	|t_id		|'0'	|NOT NULL	|
FIELD		|triggerid_down	|t_id		|'0'	|NOT NULL	|
FIELD		|triggerid_up	|t_id		|'0'	|NOT NULL	|
INDEX		|1		|triggerid_down,triggerid_up
INDEX		|2		|triggerid_up

TABLE|users|userid|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|alias		|t_varchar(100)	|''	|NOT NULL	|
FIELD		|name		|t_varchar(100)	|''	|NOT NULL	|
FIELD		|surname	|t_varchar(100)	|''	|NOT NULL	|
FIELD		|passwd		|t_char(32)	|''	|NOT NULL	|
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|
FIELD		|autologout	|t_integer	|'900'	|NOT NULL	|
FIELD		|lang		|t_varchar(5)	|'en_gb'|NOT NULL	|
FIELD		|refresh	|t_integer	|'30'	|NOT NULL	|
FIELD		|type		|t_integer	|'0'	|NOT NULL	|
INDEX		|1		|alias

TABLE|auditlog|auditid|
FIELD		|auditid	|t_id		|'0'	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|action		|t_integer	|'0'	|NOT NULL	|
FIELD		|resourcetype	|t_integer	|'0'	|NOT NULL	|
FIELD		|details	|t_varchar(128)	|'0'	|NOT NULL	|
INDEX		|1		|userid,clock
INDEX		|2		|clock

TABLE|sessions|sessionid|
FIELD		|sessionid	|t_varchar(32)	|''	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|lastaccess	|t_integer	|'0'	|NOT NULL	|

TABLE|rights|rightid|
FIELD		|rightid	|t_id		|'0'	|NOT NULL	|
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|
FIELD		|type		|t_integer	|'0'	|NOT NULL	|
FIELD		|permission	|t_integer	|'0'	|NOT NULL	|
FIELD		|id		|t_id		|	|		|
INDEX		|1		|groupid

TABLE|service_alarms|servicealarmid|
FIELD		|servicealarmid	|t_id		|'0'	|NOT NULL	|
FIELD		|serviceid	|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|value		|t_integer	|'0'	|NOT NULL	|
INDEX		|1		|serviceid,clock
INDEX		|2		|clock

TABLE|profiles|profileid|
FIELD		|profileid	|t_id		|'0'	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|idx		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|
FIELD		|valuetype	|t_integer	|0	|NOT NULL	|
INDEX		|1		|userid,idx

TABLE|screens|screenid|
FIELD		|screenid	|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(255)	|'Screen'|NOT NULL	|
FIELD		|hsize		|t_integer	|'1'	|NOT NULL	|
FIELD		|vsize		|t_integer	|'1'	|NOT NULL	|

TABLE|screens_items|screenitemid|
FIELD		|screenitemid	|t_id		|'0'	|NOT NULL	|
FIELD		|screenid	|t_id		|'0'	|NOT NULL	|
FIELD		|resourcetype	|t_integer	|'0'	|NOT NULL	|
FIELD		|resourceid	|t_id		|'0'	|NOT NULL	|
FIELD		|width		|t_integer	|'320'	|NOT NULL	|
FIELD		|height		|t_integer	|'200'	|NOT NULL	|
FIELD		|x		|t_integer	|'0'	|NOT NULL	|
FIELD		|y		|t_integer	|'0'	|NOT NULL	|
FIELD		|colspan	|t_integer	|'0'	|NOT NULL	|
FIELD		|rowspan	|t_integer	|'0'	|NOT NULL	|
FIELD		|elements	|t_integer	|'25'	|NOT NULL	|
FIELD		|valign		|t_integer	|'0'	|NOT NULL	|
FIELD		|halign		|t_integer	|'0'	|NOT NULL	|
FIELD		|style		|t_integer	|'0'	|NOT NULL	|
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|

TABLE|usrgrp|usrgrpid|
FIELD		|usrgrpid	|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|
INDEX		|1		|name

TABLE|users_groups|id|
FIELD		|id		|t_id		|'0'	|NOT NULL	|
FIELD		|usrgrpid	|t_id		|'0'	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
INDEX		|1		|usrgrpid,userid

TABLE|trends|itemid,clock|DB_NOSYNC
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|num		|t_integer	|'0'	|NOT NULL	|
FIELD		|value_min	|t_double(16,4)	|'0.0000'|NOT NULL	|
FIELD		|value_avg	|t_double(16,4)	|'0.0000'|NOT NULL	|
FIELD		|value_max	|t_double(16,4)	|'0.0000'|NOT NULL	|

TABLE|images|imageid|
FIELD		|imageid	|t_id		|'0'	|NOT NULL	|
FIELD		|imagetype	|t_integer	|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(64)	|'0'	|NOT NULL	|
FIELD		|image		|t_image	|''	|NOT NULL	|
INDEX		|1		|imagetype,name

TABLE|hosts_templates|hosttemplateid|
FIELD		|hosttemplateid	|t_id		|'0'	|NOT NULL	|
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|
INDEX		|1		|hostid,templateid

TABLE|history_log|id|
FIELD		|id		|t_id		|'0'	|NOT NULL	|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|timestamp	|t_integer	|'0'	|NOT NULL	|
FIELD		|source		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|severity	|t_integer	|'0'	|NOT NULL	|
FIELD		|value		|t_history_log	|''	|NOT NULL	|
INDEX		|1		|itemid,clock

TABLE|history_text|id|
FIELD		|id		|t_id		|'0'	|NOT NULL	|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|value		|t_history_text	|''	|NOT NULL	|
INDEX		|1		|itemid,clock

TABLE|hosts_profiles|hostid|
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|
FIELD		|devicetype	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|os		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|serialno	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|tag		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|macaddress	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|hardware	|t_blob		|''	|NOT NULL	|
FIELD		|software	|t_blob		|''	|NOT NULL	|
FIELD		|contact	|t_blob		|''	|NOT NULL	|
FIELD		|location	|t_blob		|''	|NOT NULL	|
FIELD		|notes		|t_blob		|''	|NOT NULL	|

TABLE|autoreg|id|
FIELD		|id		|t_id		|'0'	|NOT NULL	|
FIELD		|priority	|t_integer	|'0'	|NOT NULL	|
FIELD		|pattern	|t_varchar(255)	|''	|NOT NULL	|
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|

TABLE|valuemaps|valuemapid|
FIELD		|valuemapid	|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|
INDEX		|1		|name

TABLE|mappings|mappingid|
FIELD		|mappingid	|t_id		|'0'	|NOT NULL	|
FIELD		|valuemapid	|t_id		|'0'	|NOT NULL	|
FIELD		|value		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|newvalue	|t_varchar(64)	|''	|NOT NULL	|
INDEX		|1		|valuemapid

TABLE|housekeeper|housekeeperid|
FIELD		|housekeeperid	|t_id		|'0'	|NOT NULL	|
FIELD		|tablename	|t_varchar(64)	|''	|NOT NULL	|
FIELD		|field		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|value		|t_integer		|'0'	|NOT NULL	|

TABLE|acknowledges|acknowledgeid|
FIELD		|acknowledgeid	|t_id		|'0'	|NOT NULL	|
FIELD		|userid		|t_id		|'0'	|NOT NULL	|
FIELD		|eventid	|t_id		|'0'	|NOT NULL	|
FIELD		|clock		|t_time		|'0'	|NOT NULL	|
FIELD		|message	|t_varchar(255)	|''	|NOT NULL	|
INDEX		|1		|userid
INDEX		|2		|eventid
INDEX		|3		|clock

TABLE|applications|applicationid|
FIELD		|applicationid	|t_id		|'0'	|NOT NULL	|
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|
INDEX		|1		|templateid
INDEX		|2		|hostid,name

TABLE|items_applications|itemappid|
FIELD		|itemappid	|t_id		|'0'	|NOT NULL	|
FIELD		|applicationid	|t_id		|'0'	|NOT NULL	|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|
INDEX		|1		|applicationid,itemid

TABLE|help_items|itemtype,key_|DB_NOSYNC
FIELD		|itemtype	|t_integer	|'0'	|NOT NULL	|
FIELD		|key_		|t_varchar(64)	|''	|NOT NULL	|
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|
