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

TABLE|slideshows|slideshowid|ZBX_SYNC
FIELD		|slideshowid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|slides|slideid|ZBX_SYNC
FIELD		|slideid	|t_id		|'0'	|NOT NULL	|0
FIELD		|slideshowid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|slideshows
FIELD		|screenid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|screens
FIELD		|step		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|slides_1	|slideshowid

TABLE|drules|druleid|ZBX_SYNC
FIELD		|druleid	|t_id		|'0'	|NOT NULL	|0
FIELD		|proxy_hostid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|hosts
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|iprange	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|nextcheck	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|unique_dcheckid|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|dchecks

TABLE|dchecks|dcheckid|ZBX_SYNC
FIELD		|dcheckid	|t_id		|'0'	|NOT NULL	|0
FIELD		|druleid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|drules
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|key_		|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmp_community	|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ports		|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_securityname|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_securitylevel|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_authpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_privpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|druleid

TABLE|dhosts|dhostid|ZBX_SYNC
FIELD		|dhostid	|t_id		|'0'	|NOT NULL	|0
FIELD		|druleid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|drules
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastup		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastdown	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|druleid

TABLE|dservices|dserviceid|ZBX_SYNC
FIELD		|dserviceid	|t_id		|'0'	|NOT NULL	|0
FIELD		|dhostid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|dhosts
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|key_		|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|port		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastup		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastdown	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|dcheckid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|dchecks
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
UNIQUE		|1		|dcheckid,type,key_,ip,port
INDEX		|2		|dhostid

TABLE|ids|nodeid,table_name,field_name|
FIELD		|nodeid		|t_integer	|'0'	|NOT NULL	|0			|nodes
FIELD		|table_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|field_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|nextid		|t_id		|'0'	|NOT NULL	|0

TABLE|httptest|httptestid|ZBX_SYNC
FIELD		|httptestid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|applicationid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|applications
FIELD		|lastcheck	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|nextcheck	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|curstate	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|curstep	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastfailedstep	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|delay		|t_integer	|'60'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|macros		|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|agent		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|time		|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|error		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|authentication	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|http_user	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|http_password	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|httptest_1	|applicationid
INDEX		|2		|name
INDEX		|3		|status

TABLE|httpstep|httpstepid|ZBX_SYNC
FIELD		|httpstepid	|t_id		|'0'	|NOT NULL	|0
FIELD		|httptestid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|httptest
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|no		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|timeout	|t_integer	|'30'	|NOT NULL	|ZBX_SYNC
FIELD		|posts		|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|required	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|status_codes	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|httpstep_1	|httptestid

TABLE|httpstepitem|httpstepitemid|ZBX_SYNC
FIELD		|httpstepitemid	|t_id		|'0'	|NOT NULL	|0
FIELD		|httpstepid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|httpstep
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
UNIQUE		|httpstepitem_1	|httpstepid,itemid

TABLE|httptestitem|httptestitemid|ZBX_SYNC
FIELD		|httptestitemid	|t_id		|'0'	|NOT NULL	|0
FIELD		|httptestid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|httptest
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
UNIQUE		|httptestitem_1	|httptestid,itemid


TABLE|nodes|nodeid|
FIELD		|nodeid		|t_integer	|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|'0'	|NOT NULL	|0
FIELD		|timezone	|t_integer	|'0'	|NOT NULL	|0
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|0
FIELD		|port		|t_integer	|'10051'|NOT NULL	|0
FIELD		|slave_history	|t_integer	|'30'	|NOT NULL	|0
FIELD		|slave_trends	|t_integer	|'365'	|NOT NULL	|0
FIELD		|nodetype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|masterid	|t_integer	|'0'	|NOT NULL	|0

TABLE|node_cksum||0
FIELD		|nodeid		|t_integer	|'0'	|NOT NULL	|0			|nodes
FIELD		|tablename	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|recordid	|t_id		|'0'	|NOT NULL	|0
FIELD		|cksumtype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|cksum		|t_cksum_text	|''	|NOT NULL	|0
FIELD		|sync		|t_char(128)	|''	|NOT NULL	|0
INDEX		|1		|nodeid,cksumtype,tablename,recordid

TABLE|services_times|timeid|ZBX_SYNC
FIELD		|timeid		|t_id		|'0'	|NOT NULL	|0
FIELD		|serviceid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|services
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ts_from	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ts_to		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|note		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|times_1	|serviceid,type,ts_from,ts_to

-- History tables

TABLE|alerts|alertid|ZBX_HISTORY
FIELD		|alertid	|t_id		|'0'	|NOT NULL	|0
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|0			|actions
FIELD		|eventid	|t_id		|'0'	|NOT NULL	|0			|events
FIELD		|userid		|t_id		|'0'	|NOT NULL	|0			|users
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|mediatypeid	|t_id		|'0'	|NOT NULL	|0			|media_type
FIELD		|sendto		|t_varchar(100)	|''	|NOT NULL	|0
FIELD		|subject	|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|message	|t_blob		|''	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
FIELD		|retries	|t_integer	|'0'	|NOT NULL	|0
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|0
FIELD		|nextcheck	|t_integer	|'0'	|NOT NULL	|0
FIELD		|esc_step	|t_integer	|'0'	|NOT NULL	|0
FIELD		|alerttype	|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|actionid
INDEX		|2		|clock
INDEX		|3		|eventid
INDEX		|4		|status,retries
INDEX		|5		|mediatypeid
INDEX		|6		|userid

TABLE|history||0
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_double	|'0.0000'|NOT NULL	|0
INDEX		|1		|itemid,clock

TABLE|history_sync|id|ZBX_HISTORY_SYNC
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|0			|nodes
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC	|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|value		|t_double	|'0.0000'|NOT NULL	|ZBX_HISTORY_SYNC
INDEX		|1		|nodeid,id

TABLE|history_uint||0
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_bigint	|'0'	|NOT NULL	|0
INDEX		|1		|itemid,clock

TABLE|history_uint_sync|id|ZBX_HISTORY_SYNC
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|0			|nodes
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC	|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|value		|t_bigint	|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
INDEX		|1		|nodeid,id

TABLE|history_str||0
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|0
INDEX		|1		|itemid,clock

TABLE|history_str_sync|id|ZBX_HISTORY_SYNC
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|nodeid		|t_id		|'0'	|NOT NULL	|0			|nodes
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC	|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|ZBX_HISTORY_SYNC
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_HISTORY_SYNC
INDEX		|1		|nodeid,id

TABLE|history_log|id|ZBX_HISTORY
FIELD		|id		|t_id		|'0'	|NOT NULL	|0
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|timestamp	|t_time		|'0'	|NOT NULL	|0
FIELD		|source		|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|severity	|t_integer	|'0'	|NOT NULL	|0
FIELD		|value		|t_history_log	|''	|NOT NULL	|0
FIELD		|logeventid	|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|itemid,clock
UNIQUE		|2		|itemid,id

TABLE|history_text|id|ZBX_HISTORY
FIELD		|id		|t_id		|'0'	|NOT NULL	|0
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_history_text	|''	|NOT NULL	|0
INDEX		|1		|itemid,clock
UNIQUE		|2		|itemid,id

TABLE|proxy_history|id|0
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|timestamp	|t_time		|'0'	|NOT NULL	|0
FIELD		|source		|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|severity	|t_integer	|'0'	|NOT NULL	|0
FIELD		|value		|t_history_log	|''	|NOT NULL	|0
FIELD		|logeventid	|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|clock

TABLE|proxy_dhistory|id|0
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|druleid	|t_id		|'0'	|NOT NULL	|0			|drules
FIELD		|type		|t_integer	|'0'	|NOT NULL	|0
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|0
FIELD		|port		|t_integer	|'0'	|NOT NULL	|0
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
FIELD		|dcheckid	|t_id		|'0'	|NOT NULL	|0			|dchecks
INDEX		|1		|clock

TABLE|events|eventid|ZBX_HISTORY
FIELD		|eventid	|t_id		|'0'	|NOT NULL	|0
FIELD		|source		|t_integer	|'0'	|NOT NULL	|0
FIELD		|object		|t_integer	|'0'	|NOT NULL	|0
FIELD		|objectid	|t_id		|'0'	|NOT NULL	|0
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_integer	|'0'	|NOT NULL	|0
FIELD		|acknowledged	|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|object,objectid,eventid
INDEX		|2		|clock

TABLE|trends|itemid,clock|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|num		|t_integer	|'0'	|NOT NULL	|0
FIELD		|value_min	|t_double	|'0.0000'|NOT NULL	|0
FIELD		|value_avg	|t_double	|'0.0000'|NOT NULL	|0
FIELD		|value_max	|t_double	|'0.0000'|NOT NULL	|0

TABLE|trends_uint|itemid,clock|
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0			|items
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|num		|t_integer	|'0'	|NOT NULL	|0
FIELD		|value_min	|t_bigint	|'0'	|NOT NULL	|0
FIELD		|value_avg	|t_bigint	|'0'	|NOT NULL	|0
FIELD		|value_max	|t_bigint	|'0'	|NOT NULL	|0

TABLE|acknowledges|acknowledgeid|ZBX_HISTORY
FIELD		|acknowledgeid	|t_id		|'0'	|NOT NULL	|0
FIELD		|userid		|t_id		|'0'	|NOT NULL	|0			|users
FIELD		|eventid	|t_id		|'0'	|NOT NULL	|0			|events
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|message	|t_varchar(255)	|''	|NOT NULL	|0
INDEX		|1		|userid
INDEX		|2		|eventid
INDEX		|3		|clock

TABLE|auditlog|auditid|ZBX_HISTORY
FIELD		|auditid	|t_id		|'0'	|NOT NULL	|0
FIELD		|userid		|t_id		|'0'	|NOT NULL	|0			|users
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|action		|t_integer	|'0'	|NOT NULL	|0
FIELD		|resourcetype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|details	|t_varchar(128) |'0'	|NOT NULL	|0
FIELD		|ip		|t_varchar(39)	|''	|NOT NULL	|0
FIELD		|resourceid	|t_id		|'0'	|NOT NULL	|0
FIELD		|resourcename	|t_varchar(255)	|''	|NOT NULL	|0
INDEX		|1		|userid,clock
INDEX		|2		|clock

TABLE|auditlog_details|auditdetailid|ZBX_HISTORY
FIELD		|auditdetailid	|t_id		|'0'	|NOT NULL	|0
FIELD		|auditid	|t_id		|'0'	|NOT NULL	|0			|auditlog
FIELD		|table_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|field_name	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|oldvalue	|t_blob		|''	|NOT NULL	|0
FIELD		|newvalue	|t_blob		|''	|NOT NULL	|0
INDEX		|1		|auditid

TABLE|service_alarms|servicealarmid|ZBX_HISTORY
FIELD		|servicealarmid	|t_id		|'0'	|NOT NULL	|0
FIELD		|serviceid	|t_id		|'0'	|NOT NULL	|0			|services
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|value		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|serviceid,clock
INDEX		|2		|clock

-- Other tables

TABLE|actions|actionid|ZBX_SYNC
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|eventsource	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|evaltype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|esc_period	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|def_shortdata	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|def_longdata	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|recovery_msg	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|r_shortdata	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|r_longdata	|t_blob		|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|eventsource,status

TABLE|operations|operationid|ZBX_SYNC
FIELD		|operationid	|t_id		|'0'	|NOT NULL	|0
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|actions
FIELD		|operationtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|object		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|objectid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|shortdata	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|longdata	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|esc_period	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|esc_step_from	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|esc_step_to	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|default_msg	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|evaltype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|actionid

TABLE|opconditions|opconditionid|ZBX_SYNC
FIELD		|opconditionid	|t_id		|'0'	|NOT NULL	|0
FIELD		|operationid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|operations
FIELD		|conditiontype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|operator	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|operationid

TABLE|opmediatypes|opmediatypeid|ZBX_SYNC
FIELD		|opmediatypeid	|t_id		|'0'	|NOT NULL	|0
FIELD		|operationid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|operations
FIELD		|mediatypeid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|media_type
UNIQUE		|1		|operationid

TABLE|escalations|escalationid|0
FIELD		|escalationid	|t_id		|'0'	|NOT NULL	|0
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|0			|actions
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|0			|triggers
FIELD		|eventid	|t_id		|'0'	|NOT NULL	|0			|events
FIELD		|r_eventid	|t_id		|'0'	|NOT NULL	|0			|events
FIELD		|nextcheck	|t_time		|'0'	|NOT NULL	|0
FIELD		|esc_step	|t_integer	|'0'	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|actionid,triggerid
INDEX		|2		|status,nextcheck

TABLE|applications|applicationid|ZBX_SYNC
FIELD		|applicationid	|t_id		|'0'	|NOT NULL	|0
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|hosts
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|applications
INDEX		|1		|templateid
UNIQUE		|2		|hostid,name

TABLE|conditions|conditionid|ZBX_SYNC
FIELD		|conditionid	|t_id		|'0'	|NOT NULL	|0
FIELD		|actionid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|actions
FIELD		|conditiontype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|operator	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|actionid

TABLE|config|configid|ZBX_SYNC
FIELD		|configid	|t_id		|'0'	|NOT NULL	|0
FIELD		|alert_history	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|event_history	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|refresh_unsupported|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|work_period	|t_varchar(100)	|'1-5,00:00-24:00'|NOT NULL	|ZBX_SYNC
FIELD		|alert_usrgrpid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|usrgrp
FIELD		|event_ack_enable|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|event_expire	|t_integer	|'7'	|NOT NULL	|ZBX_SYNC
FIELD		|event_show_max	|t_integer	|'100'	|NOT NULL	|ZBX_SYNC
FIELD		|default_theme	|t_varchar(128)	|'default.css'|NOT NULL	|ZBX_SYNC
FIELD		|authentication_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_host	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_port	|t_integer	|389	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_base_dn	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_bind_dn	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_bind_password|t_varchar(128)|''	|NOT NULL	|ZBX_SYNC
FIELD		|ldap_search_attribute|t_varchar(128)|''|NOT NULL	|ZBX_SYNC
FIELD		|dropdown_first_entry|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|dropdown_first_remember|t_integer|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|discovery_groupid|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|groups
FIELD		|max_in_table	|t_integer	|'50'	|NOT NULL	|ZBX_SYNC
FIELD		|search_limit	|t_integer	|'1000'	|NOT NULL	|ZBX_SYNC

TABLE|functions|functionid|ZBX_SYNC
FIELD		|functionid	|t_id		|'0'	|NOT NULL	|0
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|triggers
FIELD		|lastvalue	|t_varchar(255)	|	|		|0
FIELD		|function	|t_varchar(12)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|parameter	|t_varchar(255)	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|triggerid
INDEX		|2		|itemid,function,parameter

TABLE|graphs|graphid|ZBX_SYNC
FIELD		|graphid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|width		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|height		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|yaxismin	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|yaxismax	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|graphs
FIELD		|show_work_period|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|show_triggers	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|graphtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|show_legend	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|show_3d	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|percent_left	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|percent_right	|t_double	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ymin_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ymax_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ymin_itemid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items
FIELD		|ymax_itemid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items
INDEX		|graphs_1	|name

TABLE|graphs_items|gitemid|ZBX_SYNC
FIELD		|gitemid	|t_id		|'0'	|NOT NULL	|0
FIELD		|graphid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|graphs
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items
FIELD		|drawtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|sortorder	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|color		|t_varchar(6)	|'009600'|NOT NULL	|ZBX_SYNC
FIELD		|yaxisside	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|calc_fnc	|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|periods_cnt	|t_integer	|'5'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|itemid
INDEX		|2		|graphid

TABLE|graph_theme|graphthemeid|0
FIELD		|graphthemeid	|t_id		|'0'	|NOT NULL	|0
FIELD		|description	|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|theme		|t_varchar(64)	|''	|NOT NULL	|0
FIELD		|backgroundcolor|t_varchar(6)	|'F0F0F0'|NOT NULL	|0
FIELD		|graphcolor	|t_varchar(6)	|'FFFFFF'|NOT NULL	|0
FIELD		|graphbordercolor|t_varchar(6)	|'222222'|NOT NULL	|0
FIELD		|gridcolor	|t_varchar(6)	|'CCCCCC'|NOT NULL	|0
FIELD		|maingridcolor	|t_varchar(6)	|'AAAAAA'|NOT NULL	|0
FIELD		|gridbordercolor|t_varchar(6)	|'000000'|NOT NULL	|0
FIELD		|textcolor	|t_varchar(6)	|'202020'|NOT NULL	|0
FIELD		|highlightcolor	|t_varchar(6)	|'AA4444'|NOT NULL	|0
FIELD		|leftpercentilecolor|t_varchar(6)|'11CC11'|NOT NULL	|0
FIELD		|rightpercentilecolor|t_varchar(6)|'CC1111'|NOT NULL	|0
FIELD		|noneworktimecolor|t_varchar(6)	|'CCCCCC'|NOT NULL	|0
FIELD		|gridview	|t_integer	|1	|NOT NULL	|0
FIELD		|legendview	|t_integer	|1	|NOT NULL	|0
INDEX		|1		|description
INDEX		|2		|theme


TABLE|groups|groupid|ZBX_SYNC
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|internal	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|help_items|itemtype,key_|0
FIELD		|itemtype	|t_integer	|'0'	|NOT NULL	|0
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|0

TABLE|hosts|hostid|ZBX_SYNC
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|0
FIELD		|proxy_hostid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|hosts
FIELD		|host		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|dns		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|useip		|t_integer	|'1'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ip		|t_varchar(39)	|'127.0.0.1'|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|port		|t_integer	|'10050'|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|disable_until	|t_integer	|'0'	|NOT NULL	|0
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|available	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|errors_from	|t_integer	|'0'	|NOT NULL	|0
FIELD		|lastaccess	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|inbytes	|t_bigint	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|outbytes	|t_bigint	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|useipmi	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_port	|t_integer	|'623'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_authtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_privilege	|t_integer	|'2'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_username	|t_varchar(16)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_password	|t_varchar(20)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_disable_until|t_integer	|'0'	|NOT NULL	|0
FIELD		|ipmi_available	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|snmp_disable_until|t_integer	|'0'	|NOT NULL	|0
FIELD		|snmp_available	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|maintenanceid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|maintenances
FIELD		|maintenance_status|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|maintenance_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|maintenance_from|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|ipmi_ip	|t_varchar(64)	|'127.0.0.1'|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_errors_from|t_integer	|'0'	|NOT NULL	|0
FIELD		|snmp_errors_from|t_integer	|'0'	|NOT NULL	|0
FIELD		|ipmi_error	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|snmp_error	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|host
INDEX		|2		|status
INDEX		|3		|proxy_hostid

TABLE|globalmacro|globalmacroid|ZBX_SYNC
FIELD		|globalmacroid	|t_id		|'0'	|NOT NULL	|0
FIELD		|macro		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|macro

TABLE|hostmacro|hostmacroid|ZBX_SYNC
FIELD		|hostmacroid	|t_id		|'0'	|NOT NULL	|0
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|hosts
FIELD		|macro		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|value		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|hostid,macro

TABLE|hosts_groups|hostgroupid|ZBX_SYNC
FIELD		|hostgroupid	|t_id		|'0'	|NOT NULL	|0
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|hosts
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|groups
INDEX		|1	|hostid,groupid
INDEX		|2	|groupid

TABLE|hosts_profiles|hostid|ZBX_SYNC
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|0			|hosts
FIELD		|devicetype	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|os		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|serialno	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|tag		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|macaddress	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|hardware	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|software	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|contact	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|location	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|notes		|t_blob		|''	|NOT NULL	|ZBX_SYNC

TABLE|hosts_profiles_ext|hostid|ZBX_SYNC
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|0			|hosts
FIELD		|device_alias	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_type	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_chassis	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_os	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_os_short|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_hw_arch	|t_varchar(32)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_serial	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_model	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_tag	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_vendor	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_contract|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_who	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_status	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_app_01	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_app_02	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_app_03	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_app_04	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_app_05	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_url_1	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_url_2	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_url_3	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_networks|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_notes	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_hardware|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|device_software|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|ip_subnet_mask	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ip_router	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|ip_macaddress	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|oob_ip	|t_varchar(39)		|''	|NOT NULL	|ZBX_SYNC
FIELD		|oob_subnet_mask|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|oob_router	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_buy	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_install|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_expiry	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|date_hw_decomm	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_street_1	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_street_2	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_street_3	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_city	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_state	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_country	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_zip	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_rack	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|site_notes	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_name	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_email	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_phone_1	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_phone_2	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_cell	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_screen	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_1_notes	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_name	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_email	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_phone_1	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_phone_2	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_cell	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_screen	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|poc_2_notes	|t_blob		|''	|NOT NULL	|ZBX_SYNC

TABLE|hosts_templates|hosttemplateid|ZBX_SYNC
FIELD		|hosttemplateid	|t_id		|'0'	|NOT NULL	|0
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|hosts
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|hosts
UNIQUE		|1		|hostid,templateid
INDEX		|2		|templateid

TABLE|housekeeper|housekeeperid|0
FIELD		|housekeeperid	|t_id		|'0'	|NOT NULL	|0
FIELD		|tablename	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|field		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items

TABLE|images|imageid|ZBX_SYNC
FIELD		|imageid	|t_id		|'0'	|NOT NULL	|0
FIELD		|imagetype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|name		|t_varchar(64)	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|image		|t_image	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|imagetype,name

TABLE|items|itemid|ZBX_SYNC
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|0
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmp_community	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmp_oid	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmp_port	|t_integer	|'161'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|hosts
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|key_		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|delay		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|history	|t_integer	|'90'	|NOT NULL	|ZBX_SYNC
FIELD		|trends		|t_integer	|'365'	|NOT NULL	|ZBX_SYNC
FIELD		|lastvalue	|t_varchar(255)	|	|NULL		|0
FIELD		|lastclock	|t_time		|	|NULL		|0
FIELD		|prevvalue	|t_varchar(255)	|	|NULL		|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|value_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|trapper_hosts	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|units		|t_varchar(10)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|multiplier	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|delta		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|prevorgvalue	|t_varchar(255)	|	|NULL		|0
FIELD		|snmpv3_securityname|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_securitylevel|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_authpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|snmpv3_privpassphrase|t_varchar(64)|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|formula	|t_varchar(255)	|'1'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|lastlogsize	|t_integer	|'0'	|NOT NULL	|0
FIELD		|logtimefmt	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|items
FIELD		|valuemapid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY	|valuemaps
FIELD		|delay_flex	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|params		|t_item_param	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|ipmi_sensor	|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|data_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|authtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|username	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|password	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|publickey	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|privatekey	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|mtime		|t_integer	|'0'	|NOT NULL	|0
UNIQUE		|1		|hostid,key_
INDEX		|3		|status
INDEX		|4		|templateid

TABLE|items_applications|itemappid|ZBX_SYNC
FIELD		|itemappid	|t_id		|'0'	|NOT NULL	|0
FIELD		|applicationid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|applications
FIELD		|itemid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|items
INDEX		|1		|applicationid,itemid
INDEX		|2		|itemid

TABLE|mappings|mappingid|ZBX_SYNC
FIELD		|mappingid	|t_id		|'0'	|NOT NULL	|0
FIELD		|valuemapid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|valuemaps
FIELD		|value		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|newvalue	|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|valuemapid

TABLE|media|mediaid|ZBX_SYNC
FIELD		|mediaid	|t_id		|'0'	|NOT NULL	|0
FIELD		|userid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|users
FIELD		|mediatypeid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|media_type
FIELD		|sendto		|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|active		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|severity	|t_integer	|'63'	|NOT NULL	|ZBX_SYNC
FIELD		|period		|t_varchar(100)	|'1-7,00:00-23:59'|NOT NULL	|ZBX_SYNC
INDEX		|1		|userid
INDEX		|2		|mediatypeid

TABLE|media_type|mediatypeid|ZBX_SYNC
FIELD		|mediatypeid	|t_id		|'0'	|NOT NULL	|0
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|description	|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|smtp_server	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|smtp_helo	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|smtp_email	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|exec_path	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|gsm_modem	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|username	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|passwd		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC

TABLE|profiles|profileid|0
FIELD		|profileid	|t_id		|'0'	|NOT NULL	|0
FIELD		|userid		|t_id		|'0'	|NOT NULL	|0			|users
FIELD		|idx		|t_varchar(96)	|''	|NOT NULL	|0
FIELD		|idx2		|t_id		|'0'	|NOT NULL	|0
FIELD		|value_id	|t_id		|'0'	|NOT NULL	|0
FIELD		|value_int	|t_integer	|'0'	|NOT NULL	|0
FIELD		|value_str	|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|source		|t_varchar(96)	|''	|NOT NULL	|0
FIELD		|type		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|userid,idx,idx2
INDEX		|2		|userid,profileid

TABLE|rights|rightid|ZBX_SYNC
FIELD		|rightid	|t_id		|'0'	|NOT NULL	|0
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|usrgrp
FIELD		|permission	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|id		|t_id		|	|		|ZBX_SYNC		|groups
INDEX		|1		|groupid
INDEX		|2		|id

TABLE|scripts|scriptid|ZBX_SYNC
FIELD		|scriptid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|command	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|host_access	|t_integer	|'2'	|NOT NULL	|ZBX_SYNC
FIELD		|usrgrpid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|usrgrp
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|groups

TABLE|screens|screenid|ZBX_SYNC
FIELD		|screenid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(255)	|'Screen'|NOT NULL	|ZBX_SYNC
FIELD		|hsize		|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
FIELD		|vsize		|t_integer	|'1'	|NOT NULL	|ZBX_SYNC

TABLE|screens_items|screenitemid|ZBX_SYNC
FIELD		|screenitemid	|t_id		|'0'	|NOT NULL	|0
FIELD		|screenid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|screens
FIELD		|resourcetype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|resourceid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|width		|t_integer	|'320'	|NOT NULL	|ZBX_SYNC
FIELD		|height		|t_integer	|'200'	|NOT NULL	|ZBX_SYNC
FIELD		|x		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|y		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|colspan	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|rowspan	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|elements	|t_integer	|'25'	|NOT NULL	|ZBX_SYNC
FIELD		|valign		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|halign		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|style		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|dynamic	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|services|serviceid|ZBX_SYNC
FIELD		|serviceid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|algorithm	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|triggerid	|t_id		|	|		|ZBX_SYNC		|triggers
FIELD		|showsla	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|goodsla	|t_double	|'99.9'	|NOT NULL	|ZBX_SYNC
FIELD		|sortorder	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|triggerid

TABLE|services_links|linkid|ZBX_SYNC
FIELD		|linkid		|t_id		|'0'	|NOT NULL	|0
FIELD		|serviceupid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|services
FIELD		|servicedownid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|services
FIELD		|soft		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|links_1	|servicedownid
UNIQUE		|links_2	|serviceupid,servicedownid

TABLE|sessions|sessionid|0
FIELD		|sessionid	|t_varchar(32)	|''	|NOT NULL	|0
FIELD		|userid		|t_id		|'0'	|NOT NULL	|0			|users
FIELD		|lastaccess	|t_integer	|'0'	|NOT NULL	|0
FIELD		|status		|t_integer	|'0'	|NOT NULL	|0
INDEX		|1		|userid, status

TABLE|sysmaps_links|linkid|ZBX_SYNC
FIELD		|linkid		|t_id		|'0'	|NOT NULL	|0
FIELD		|sysmapid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|sysmaps
FIELD		|selementid1	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|sysmaps_elements
FIELD		|selementid2	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|sysmaps_elements
FIELD		|drawtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|color		|t_varchar(6)	|'000000'|NOT NULL	|ZBX_SYNC
FIELD		|label		|t_varchar(255)|''	|NOT NULL	|ZBX_SYNC

TABLE|sysmaps_link_triggers|linktriggerid|ZBX_SYNC
FIELD		|linktriggerid	|t_id		|'0'	|NOT NULL	|0
FIELD		|linkid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|sysmaps_links
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|triggers
FIELD		|drawtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|color		|t_varchar(6)	|'000000'|NOT NULL	|ZBX_SYNC
UNIQUE		|1		|linkid,triggerid

TABLE|sysmaps_elements|selementid|ZBX_SYNC
FIELD		|selementid	|t_id		|'0'	|NOT NULL	|0
FIELD		|sysmapid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|sysmaps
FIELD		|elementid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|elementtype	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|iconid_off	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|images
FIELD		|iconid_on	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|images
FIELD		|iconid_unknown	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|images
FIELD		|label		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|label_location	|t_integer	|	|NULL		|ZBX_SYNC
FIELD		|x		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|y		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|iconid_disabled|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|images
FIELD		|iconid_maintenance|t_id	|'0'	|NOT NULL	|ZBX_SYNC		|images

TABLE|sysmaps|sysmapid|ZBX_SYNC
FIELD		|sysmapid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|width		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|height		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|backgroundid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|images
FIELD		|label_type	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|label_location	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|highlight	|t_integer	|'1'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|triggers|triggerid|ZBX_SYNC
FIELD		|triggerid	|t_id		|'0'	|NOT NULL	|0
FIELD		|expression	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|description	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|status		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|value		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|priority	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|lastchange	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|dep_level	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|comments	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|error		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|templateid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|triggers
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|status
INDEX		|2		|value

TABLE|trigger_depends|triggerdepid|ZBX_SYNC
FIELD		|triggerdepid	|t_id		|'0'	|NOT NULL	|0
FIELD		|triggerid_down	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|triggers
FIELD		|triggerid_up	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|triggers
INDEX		|1		|triggerid_down,triggerid_up
INDEX		|2		|triggerid_up

TABLE|users|userid|ZBX_SYNC
FIELD		|userid		|t_id		|'0'	|NOT NULL	|0
FIELD		|alias		|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|name		|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|surname	|t_varchar(100)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|passwd		|t_char(32)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|url		|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|autologin	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|autologout	|t_integer	|'900'	|NOT NULL	|ZBX_SYNC
FIELD		|lang		|t_varchar(5)	|'en_gb'|NOT NULL	|ZBX_SYNC
FIELD		|refresh	|t_integer	|'30'	|NOT NULL	|ZBX_SYNC
FIELD		|type		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|theme		|t_varchar(128)	|'default.css'|NOT NULL	|ZBX_SYNC
FIELD		|attempt_failed	|t_integer	|0	|NOT NULL	|ZBX_SYNC
FIELD		|attempt_ip	|t_varchar(39)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|attempt_clock	|t_integer	|0	|NOT NULL	|ZBX_SYNC
FIELD		|rows_per_page	|t_integer	|50	|NOT NULL	|ZBX_SYNC
INDEX		|1		|alias

TABLE|usrgrp|usrgrpid|ZBX_SYNC
FIELD		|usrgrpid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|gui_access	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|users_status	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|api_access	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|debug_mode	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|users_groups|id|ZBX_SYNC
FIELD		|id		|t_id		|'0'	|NOT NULL	|0
FIELD		|usrgrpid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|usrgrp
FIELD		|userid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|users
INDEX		|1		|usrgrpid,userid

TABLE|valuemaps|valuemapid|ZBX_SYNC
FIELD		|valuemapid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|maintenances|maintenanceid|ZBX_SYNC
FIELD		|maintenanceid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC
FIELD		|maintenance_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|description	|t_blob		|''	|NOT NULL	|ZBX_SYNC
FIELD		|active_since	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|active_till	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
INDEX		|1		|active_since,active_till

TABLE|maintenances_hosts|maintenance_hostid|ZBX_SYNC
FIELD		|maintenance_hostid|t_id	|'0'	|NOT NULL	|0
FIELD		|maintenanceid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|maintenances
FIELD		|hostid		|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|hosts
INDEX		|1		|maintenanceid,hostid

TABLE|maintenances_groups|maintenance_groupid|ZBX_SYNC
FIELD		|maintenance_groupid|t_id	|'0'	|NOT NULL	|0
FIELD		|maintenanceid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|maintenances
FIELD		|groupid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|groups
INDEX		|1		|maintenanceid,groupid

TABLE|maintenances_windows|maintenance_timeperiodid|ZBX_SYNC
FIELD		|maintenance_timeperiodid|t_id	|'0'	|NOT NULL	|0
FIELD		|maintenanceid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|maintenances
FIELD		|timeperiodid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|timeperiods
INDEX		|1		|maintenanceid,timeperiodid

TABLE|timeperiods|timeperiodid|ZBX_SYNC
FIELD		|timeperiodid	|t_id		|'0'	|NOT NULL	|0
FIELD		|timeperiod_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|every		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|month		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|dayofweek	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|day		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|start_time	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|period		|t_integer	|'0'	|NOT NULL	|ZBX_SYNC
FIELD		|start_date	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC

TABLE|regexps|regexpid|ZBX_SYNC
FIELD		|regexpid	|t_id		|'0'	|NOT NULL	|0
FIELD		|name		|t_varchar(128)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|test_string	|t_blob		|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|name

TABLE|user_history|userhistoryid|0
FIELD		|userhistoryid	|t_id		|'0'	|NOT NULL	|0
FIELD		|userid		|t_id		|'0'	|NOT NULL	|0			|users
FIELD		|title1		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url1		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title2		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url2		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title3		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url3		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title4		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url4		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|title5		|t_varchar(255)	|''	|NOT NULL	|0
FIELD		|url5		|t_varchar(255)	|''	|NOT NULL	|0
UNIQUE		|1		|userid

TABLE|expressions|expressionid|ZBX_SYNC
FIELD		|expressionid	|t_id		|'0'	|NOT NULL	|0
FIELD		|regexpid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY		|regexps
FIELD		|expression	|t_varchar(255)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|expression_type|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|exp_delimiter	|t_varchar(1)	|''	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
FIELD		|case_sensitive	|t_integer	|'0'	|NOT NULL	|ZBX_SYNC,ZBX_PROXY
INDEX		|1		|regexpid

TABLE|autoreg_host|autoreg_hostid|ZBX_SYNC
FIELD		|autoreg_hostid	|t_id		|'0'	|NOT NULL	|0
FIELD		|proxy_hostid	|t_id		|'0'	|NOT NULL	|ZBX_SYNC		|hosts
FIELD		|host		|t_varchar(64)	|''	|NOT NULL	|ZBX_SYNC
INDEX		|1		|proxy_hostid,host

TABLE|proxy_autoreg_host|id|0
FIELD		|id		|t_serial	|	|NOT NULL	|0
FIELD		|clock		|t_time		|'0'	|NOT NULL	|0
FIELD		|host		|t_varchar(64)	|''	|NOT NULL	|0
INDEX		|1		|clock

