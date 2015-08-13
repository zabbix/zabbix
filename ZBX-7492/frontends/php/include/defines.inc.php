<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

define('ZABBIX_VERSION',     '2.2.11rc1');
define('ZABBIX_API_VERSION', '2.2.11');
define('ZABBIX_DB_VERSION',	 2020000);

define('ZABBIX_COPYRIGHT_FROM', '2001');
define('ZABBIX_COPYRIGHT_TO',   '2015');

define('ZBX_LOGIN_ATTEMPTS',	5);
define('ZBX_LOGIN_BLOCK',		30); // sec

define('ZBX_MIN_PERIOD',		3600); // 1 hour
define('ZBX_MAX_PERIOD',		63072000); // the maximum period for the time bar control, ~2 years (2 * 365 * 86400)
define('ZBX_MAX_DATE',			2147483647); // 19 Jan 2038 05:14:07
define('ZBX_PERIOD_DEFAULT',	3600); // 1 hour

// the maximum period to display history data for the latest data and item overview pages in seconds
// by default set to 86400 seconds (24 hours)
define('ZBX_HISTORY_PERIOD', 86400);

define('ZBX_WIDGET_ROWS', 20);

define('ZBX_FONTPATH',				realpath('fonts')); // where to search for font (GD > 2.0.18)
define('ZBX_GRAPH_FONT_NAME',		'DejaVuSans'); // font file name
define('ZBX_GRAPH_LEGEND_HEIGHT',	120); // when graph height is less then this value, some legend will not show up

define('ZBX_SCRIPT_TIMEOUT',		60); // in seconds

define('GRAPH_YAXIS_SIDE_DEFAULT', 0); // 0 - LEFT SIDE, 1 - RIGHT SIDE

define('ZBX_MAX_IMAGE_SIZE', 1048576); // 1024 * 1024

define('ZBX_UNITS_ROUNDOFF_THRESHOLD',		0.01);
define('ZBX_UNITS_ROUNDOFF_UPPER_LIMIT',	2);
define('ZBX_UNITS_ROUNDOFF_MIDDLE_LIMIT',	4);
define('ZBX_UNITS_ROUNDOFF_LOWER_LIMIT',	6);

define('ZBX_PRECISION_10',	10);

define('ZBX_DEFAULT_INTERVAL', '1-7,00:00-24:00');

define('ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT',	0);
define('ZBX_SCRIPT_TYPE_IPMI',			1);
define('ZBX_SCRIPT_TYPE_SSH',			2);
define('ZBX_SCRIPT_TYPE_TELNET',		3);
define('ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT',	4);

define('ZBX_SCRIPT_EXECUTE_ON_AGENT',	0);
define('ZBX_SCRIPT_EXECUTE_ON_SERVER',	1);

define('ZBX_FLAG_DISCOVERY_NORMAL',		0x0);
define('ZBX_FLAG_DISCOVERY_RULE',		0x1);
define('ZBX_FLAG_DISCOVERY_PROTOTYPE',	0x2);
define('ZBX_FLAG_DISCOVERY_CREATED',	0x4);

define('EXTACK_OPTION_ALL',		0);
define('EXTACK_OPTION_UNACK',	1);
define('EXTACK_OPTION_BOTH',	2);

define('TRIGGERS_OPTION_ONLYTRUE',	1);
define('TRIGGERS_OPTION_ALL',		2);

define('ZBX_ACK_STS_ANY',				1);
define('ZBX_ACK_STS_WITH_UNACK',		2);
define('ZBX_ACK_STS_WITH_LAST_UNACK',	3);

define('EVENTS_OPTION_NOEVENT', 1);
define('EVENTS_OPTION_ALL',		2);
define('EVENTS_OPTION_NOT_ACK', 3);

define('ZBX_FONT_NAME', 'DejaVuSans');

define('ZBX_AUTH_INTERNAL',	0);
define('ZBX_AUTH_LDAP',		1);
define('ZBX_AUTH_HTTP',		2);

define('ZBX_DB_DB2',		'IBM_DB2');
define('ZBX_DB_MYSQL',		'MYSQL');
define('ZBX_DB_ORACLE',		'ORACLE');
define('ZBX_DB_POSTGRESQL',	'POSTGRESQL');
define('ZBX_DB_SQLITE3',	'SQLITE3');

define('ZBX_STANDALONE_MAX_IDS', '9223372036854775807');
define('ZBX_DM_MAX_HISTORY_IDS', '100000000000000');
define('ZBX_DM_MAX_CONFIG_IDS', '100000000000');

define('PAGE_TYPE_HTML',				0);
define('PAGE_TYPE_IMAGE',				1);
define('PAGE_TYPE_XML',					2);
define('PAGE_TYPE_JS',					3); // javascript
define('PAGE_TYPE_CSS',					4);
define('PAGE_TYPE_HTML_BLOCK',			5); // simple block of html (as text)
define('PAGE_TYPE_JSON',				6); // simple JSON
define('PAGE_TYPE_JSON_RPC',			7); // api call
define('PAGE_TYPE_TEXT_FILE',			8); // api call
define('PAGE_TYPE_TEXT',				9); // simple text
define('PAGE_TYPE_CSV',					10); // CSV format
define('PAGE_TYPE_TEXT_RETURN_JSON',	11); // input plaintext output json

define('ZBX_SESSION_ACTIVE',	0);
define('ZBX_SESSION_PASSIVE',	1);

define('ZBX_DROPDOWN_FIRST_NONE',	0);
define('ZBX_DROPDOWN_FIRST_ALL',	1);

define('T_ZBX_STR',			0);
define('T_ZBX_INT',			1);
define('T_ZBX_DBL',			2);
define('T_ZBX_PERIOD',		3);
define('T_ZBX_IP',			4);
define('T_ZBX_CLR',			5);
define('T_ZBX_IP_RANGE',	7);
define('T_ZBX_INT_RANGE',	8);
define('T_ZBX_DBL_BIG',		9);
define('T_ZBX_DBL_STR',		10);

define('O_MAND',	0);
define('O_OPT',		1);
define('O_NO',		2);

define('P_SYS',				1);
define('P_UNSET_EMPTY',		2);
define('P_ACT',				16);
define('P_NZERO',			32);

//	misc parameters
define('IMAGE_FORMAT_PNG',	'PNG');
define('IMAGE_FORMAT_JPEG',	'JPEG');
define('IMAGE_FORMAT_TEXT',	'JPEG');

define('IMAGE_TYPE_ICON',			1);
define('IMAGE_TYPE_BACKGROUND',		2);

define('ITEM_CONVERT_WITH_UNITS',	0); // - do not convert empty units
define('ITEM_CONVERT_NO_UNITS',		1); // - no units

define('ZBX_SORT_UP',	'ASC');
define('ZBX_SORT_DOWN',	'DESC');

define('AUDIT_ACTION_ADD',		0);
define('AUDIT_ACTION_UPDATE',	1);
define('AUDIT_ACTION_DELETE',	2);
define('AUDIT_ACTION_LOGIN',	3);
define('AUDIT_ACTION_LOGOUT',	4);
define('AUDIT_ACTION_ENABLE',	5);
define('AUDIT_ACTION_DISABLE',	6);

define('AUDIT_RESOURCE_USER',			0);
define('AUDIT_RESOURCE_ZABBIX_CONFIG',	2);
define('AUDIT_RESOURCE_MEDIA_TYPE',		3);
define('AUDIT_RESOURCE_HOST',			4);
define('AUDIT_RESOURCE_ACTION',			5);
define('AUDIT_RESOURCE_GRAPH',			6);
define('AUDIT_RESOURCE_GRAPH_ELEMENT',	7);
define('AUDIT_RESOURCE_USER_GROUP',		11);
define('AUDIT_RESOURCE_APPLICATION',	12);
define('AUDIT_RESOURCE_TRIGGER',		13);
define('AUDIT_RESOURCE_HOST_GROUP',		14);
define('AUDIT_RESOURCE_ITEM',			15);
define('AUDIT_RESOURCE_IMAGE',			16);
define('AUDIT_RESOURCE_VALUE_MAP',		17);
define('AUDIT_RESOURCE_IT_SERVICE',		18);
define('AUDIT_RESOURCE_MAP',			19);
define('AUDIT_RESOURCE_SCREEN',			20);
define('AUDIT_RESOURCE_NODE',			21);
define('AUDIT_RESOURCE_SCENARIO',		22);
define('AUDIT_RESOURCE_DISCOVERY_RULE',	23);
define('AUDIT_RESOURCE_SLIDESHOW',		24);
define('AUDIT_RESOURCE_SCRIPT',			25);
define('AUDIT_RESOURCE_PROXY',			26);
define('AUDIT_RESOURCE_MAINTENANCE',	27);
define('AUDIT_RESOURCE_REGEXP',			28);
define('AUDIT_RESOURCE_MACRO',			29);
define('AUDIT_RESOURCE_TEMPLATE',		30);
define('AUDIT_RESOURCE_TRIGGER_PROTOTYPE', 31);

define('CONDITION_TYPE_HOST_GROUP',			0);
define('CONDITION_TYPE_HOST',				1);
define('CONDITION_TYPE_TRIGGER',			2);
define('CONDITION_TYPE_TRIGGER_NAME',		3);
define('CONDITION_TYPE_TRIGGER_SEVERITY',	4);
define('CONDITION_TYPE_TRIGGER_VALUE',		5);
define('CONDITION_TYPE_TIME_PERIOD',		6);
define('CONDITION_TYPE_DHOST_IP',			7);
define('CONDITION_TYPE_DSERVICE_TYPE',		8);
define('CONDITION_TYPE_DSERVICE_PORT',		9);
define('CONDITION_TYPE_DSTATUS',			10);
define('CONDITION_TYPE_DUPTIME',			11);
define('CONDITION_TYPE_DVALUE',				12);
define('CONDITION_TYPE_TEMPLATE',			13);
define('CONDITION_TYPE_EVENT_ACKNOWLEDGED',	14);
define('CONDITION_TYPE_APPLICATION',		15);
define('CONDITION_TYPE_MAINTENANCE',		16);
define('CONDITION_TYPE_NODE',				17);
define('CONDITION_TYPE_DRULE',				18);
define('CONDITION_TYPE_DCHECK',				19);
define('CONDITION_TYPE_PROXY',				20);
define('CONDITION_TYPE_DOBJECT',			21);
define('CONDITION_TYPE_HOST_NAME',			22);
define('CONDITION_TYPE_EVENT_TYPE',			23);
define('CONDITION_TYPE_HOST_METADATA',		24);

define('CONDITION_OPERATOR_EQUAL',		0);
define('CONDITION_OPERATOR_NOT_EQUAL',	1);
define('CONDITION_OPERATOR_LIKE',		2);
define('CONDITION_OPERATOR_NOT_LIKE',	3);
define('CONDITION_OPERATOR_IN',			4);
define('CONDITION_OPERATOR_MORE_EQUAL',	5);
define('CONDITION_OPERATOR_LESS_EQUAL',	6);
define('CONDITION_OPERATOR_NOT_IN',		7);

// event type action condition values
define('EVENT_TYPE_ITEM_NOTSUPPORTED',		0);
define('EVENT_TYPE_ITEM_NORMAL',			1);
define('EVENT_TYPE_LLDRULE_NOTSUPPORTED',	2);
define('EVENT_TYPE_LLDRULE_NORMAL',			3);
define('EVENT_TYPE_TRIGGER_UNKNOWN',		4);
define('EVENT_TYPE_TRIGGER_NORMAL',			5);

define('HOST_STATUS_MONITORED',		0);
define('HOST_STATUS_NOT_MONITORED',	1);
define('HOST_STATUS_TEMPLATE',		3);
define('HOST_STATUS_PROXY_ACTIVE',	5);
define('HOST_STATUS_PROXY_PASSIVE',	6);

define('HOST_MAINTENANCE_STATUS_OFF',	0);
define('HOST_MAINTENANCE_STATUS_ON',	1);

define('INTERFACE_SECONDARY',	0);
define('INTERFACE_PRIMARY',		1);

define('INTERFACE_USE_DNS',	0);
define('INTERFACE_USE_IP',	1);

define('INTERFACE_TYPE_ANY',		-1);
define('INTERFACE_TYPE_UNKNOWN',	0);
define('INTERFACE_TYPE_AGENT',		1);
define('INTERFACE_TYPE_SNMP',		2);
define('INTERFACE_TYPE_IPMI',		3);
define('INTERFACE_TYPE_JMX',		4);

define('MAINTENANCE_STATUS_ACTIVE',		0);
define('MAINTENANCE_STATUS_APPROACH',	1);
define('MAINTENANCE_STATUS_EXPIRED',	2);

define('HOST_AVAILABLE_UNKNOWN',	0);
define('HOST_AVAILABLE_TRUE',		1);
define('HOST_AVAILABLE_FALSE',		2);

define('MAINTENANCE_TYPE_NORMAL',	0);
define('MAINTENANCE_TYPE_NODATA',	1);

define('TIMEPERIOD_TYPE_ONETIME',	0);
define('TIMEPERIOD_TYPE_HOURLY',	1);
define('TIMEPERIOD_TYPE_DAILY',		2);
define('TIMEPERIOD_TYPE_WEEKLY',	3);
define('TIMEPERIOD_TYPE_MONTHLY',	4);
define('TIMEPERIOD_TYPE_YEARLY',	5);

define('SYSMAP_LABEL_ADVANCED_OFF',	0);
define('SYSMAP_LABEL_ADVANCED_ON',	1);

define('MAP_LABEL_TYPE_LABEL',		0);
define('MAP_LABEL_TYPE_IP',			1);
define('MAP_LABEL_TYPE_NAME',		2);
define('MAP_LABEL_TYPE_STATUS',		3);
define('MAP_LABEL_TYPE_NOTHING',	4);
define('MAP_LABEL_TYPE_CUSTOM',		5);

define('MAP_LABEL_LOC_DEFAULT', -1);
define('MAP_LABEL_LOC_BOTTOM',	0);
define('MAP_LABEL_LOC_LEFT',	1);
define('MAP_LABEL_LOC_RIGHT',	2);
define('MAP_LABEL_LOC_TOP',		3);

define('SYSMAP_ELEMENT_TYPE_HOST',		0);
define('SYSMAP_ELEMENT_TYPE_MAP',		1);
define('SYSMAP_ELEMENT_TYPE_TRIGGER',	2);
define('SYSMAP_ELEMENT_TYPE_HOST_GROUP',3);
define('SYSMAP_ELEMENT_TYPE_IMAGE',		4);

define('SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP',				0);
define('SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS',	1);

define('SYSMAP_ELEMENT_AREA_TYPE_FIT',		0);
define('SYSMAP_ELEMENT_AREA_TYPE_CUSTOM',	1);

define('SYSMAP_ELEMENT_AREA_VIEWTYPE_GRID', 0);

define('SYSMAP_ELEMENT_ICON_ON',			0);
define('SYSMAP_ELEMENT_ICON_OFF',			1);
define('SYSMAP_ELEMENT_ICON_MAINTENANCE',	3);
define('SYSMAP_ELEMENT_ICON_DISABLED',		4);

define('SYSMAP_HIGHLIGHT_OFF',	0);
define('SYSMAP_HIGHLIGHT_ON',	1);

define('SYSMAP_GRID_SHOW_ON',	1);
define('SYSMAP_GRID_SHOW_OFF',	0);

define('SYSMAP_EXPAND_MACROS_OFF',	0);
define('SYSMAP_EXPAND_MACROS_ON',	1);

define('SYSMAP_GRID_ALIGN_ON',	1);
define('SYSMAP_GRID_ALIGN_OFF',	0);

define('ZBX_ITEM_DELAY_DEFAULT', 30);

define('ITEM_TYPE_ZABBIX',			0);
define('ITEM_TYPE_SNMPV1',			1);
define('ITEM_TYPE_TRAPPER',			2);
define('ITEM_TYPE_SIMPLE',			3);
define('ITEM_TYPE_SNMPV2C',			4);
define('ITEM_TYPE_INTERNAL',		5);
define('ITEM_TYPE_SNMPV3',			6);
define('ITEM_TYPE_ZABBIX_ACTIVE',	7);
define('ITEM_TYPE_AGGREGATE',		8);
define('ITEM_TYPE_HTTPTEST',		9);
define('ITEM_TYPE_EXTERNAL',		10);
define('ITEM_TYPE_DB_MONITOR',		11);
define('ITEM_TYPE_IPMI',			12);
define('ITEM_TYPE_SSH',				13);
define('ITEM_TYPE_TELNET',			14);
define('ITEM_TYPE_CALCULATED',		15);
define('ITEM_TYPE_JMX',				16);

define('ITEM_VALUE_TYPE_FLOAT',		0);
define('ITEM_VALUE_TYPE_STR',		1); // aka Character
define('ITEM_VALUE_TYPE_LOG',		2);
define('ITEM_VALUE_TYPE_UINT64',	3);
define('ITEM_VALUE_TYPE_TEXT',		4);

define('ITEM_DATA_TYPE_DECIMAL',		0);
define('ITEM_DATA_TYPE_OCTAL',			1);
define('ITEM_DATA_TYPE_HEXADECIMAL',	2);
define('ITEM_DATA_TYPE_BOOLEAN',		3);

define('ZBX_DEFAULT_KEY_DB_MONITOR',	'db.odbc.select[<unique short description>,<dsn>]');
define('ZBX_DEFAULT_KEY_SSH',			'ssh.run[<unique short description>,<ip>,<port>,<encoding>]');
define('ZBX_DEFAULT_KEY_TELNET',		'telnet.run[<unique short description>,<ip>,<port>,<encoding>]');
define('ZBX_DEFAULT_KEY_JMX',			'jmx[<object name>,<attribute name>]');

define('SYSMAP_ELEMENT_USE_ICONMAP_ON',		1);
define('SYSMAP_ELEMENT_USE_ICONMAP_OFF',	0);

define('ZBX_ICON_PREVIEW_HEIGHT',	24);
define('ZBX_ICON_PREVIEW_WIDTH',	24);

define('ITEM_STATUS_ACTIVE',		0);
define('ITEM_STATUS_DISABLED',		1);
define('ITEM_STATUS_NOTSUPPORTED',	3);

define('ITEM_STATE_NORMAL',			0);
define('ITEM_STATE_NOTSUPPORTED',	1);

define('ITEM_TYPE_SNMPTRAP', 17);

define('ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV',	0);
define('ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV',		1);
define('ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV',		2);

define('ITEM_AUTHTYPE_PASSWORD',	0);
define('ITEM_AUTHTYPE_PUBLICKEY',	1);

define('ITEM_AUTHPROTOCOL_MD5', 0);
define('ITEM_AUTHPROTOCOL_SHA', 1);

define('ITEM_PRIVPROTOCOL_DES', 0);
define('ITEM_PRIVPROTOCOL_AES', 1);

define('ITEM_LOGTYPE_INFORMATION',		1);
define('ITEM_LOGTYPE_WARNING',			2);
define('ITEM_LOGTYPE_ERROR',			4);
define('ITEM_LOGTYPE_FAILURE_AUDIT',	7);
define('ITEM_LOGTYPE_SUCCESS_AUDIT',	8);
define('ITEM_LOGTYPE_CRITICAL',			9);
define('ITEM_LOGTYPE_VERBOSE',			10);

define('GRAPH_ITEM_DRAWTYPE_LINE',			0);
define('GRAPH_ITEM_DRAWTYPE_FILLED_REGION',	1);
define('GRAPH_ITEM_DRAWTYPE_BOLD_LINE',		2);
define('GRAPH_ITEM_DRAWTYPE_DOT',			3);
define('GRAPH_ITEM_DRAWTYPE_DASHED_LINE',	4);
define('GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE',	5);
define('GRAPH_ITEM_DRAWTYPE_BOLD_DOT',		6);

define('MAP_LINK_DRAWTYPE_LINE',			0);
define('MAP_LINK_DRAWTYPE_BOLD_LINE',		2);
define('MAP_LINK_DRAWTYPE_DOT',				3);
define('MAP_LINK_DRAWTYPE_DASHED_LINE',		4);

define('SERVICE_ALGORITHM_NONE',	0); // do not calculate
define('SERVICE_ALGORITHM_MAX',		1); // problem, if one children has a problem
define('SERVICE_ALGORITHM_MIN',		2); // problem, if all children have problems

define('SERVICE_SLA', 99.05);

define('SERVICE_SHOW_SLA_OFF',	0);
define('SERVICE_SHOW_SLA_ON',	1);

define('SERVICE_STATUS_OK', 0);

define('TRIGGER_MULT_EVENT_DISABLED',	0);
define('TRIGGER_MULT_EVENT_ENABLED',	1);

define('TRIGGER_STATUS_ENABLED',	0);
define('TRIGGER_STATUS_DISABLED',	1);

define('TRIGGER_VALUE_FALSE',	0);
define('TRIGGER_VALUE_TRUE',	1);

define('TRIGGER_STATE_NORMAL',	0);
define('TRIGGER_STATE_UNKNOWN',	1);

define('TRIGGER_SEVERITY_NOT_CLASSIFIED',	0);
define('TRIGGER_SEVERITY_INFORMATION',		1);
define('TRIGGER_SEVERITY_WARNING',			2);
define('TRIGGER_SEVERITY_AVERAGE',			3);
define('TRIGGER_SEVERITY_HIGH',				4);
define('TRIGGER_SEVERITY_DISASTER',			5);
define('TRIGGER_SEVERITY_COUNT',			6);

define('ALERT_MAX_RETRIES', 3);

define('ALERT_STATUS_NOT_SENT', 0);
define('ALERT_STATUS_SENT',		1);
define('ALERT_STATUS_FAILED',	2);

define('ALERT_TYPE_MESSAGE',	0);
define('ALERT_TYPE_COMMAND',	1);

define('MEDIA_TYPE_STATUS_ACTIVE',		0);
define('MEDIA_TYPE_STATUS_DISABLED',	1);

define('MEDIA_TYPE_EMAIL',		0);
define('MEDIA_TYPE_EXEC',		1);
define('MEDIA_TYPE_SMS',		2);
define('MEDIA_TYPE_JABBER',		3);
define('MEDIA_TYPE_EZ_TEXTING',	100);

define('EZ_TEXTING_LIMIT_USA',		0);
define('EZ_TEXTING_LIMIT_CANADA',	1);

define('ACTION_DEFAULT_SUBJ_TRIGGER', '{TRIGGER.STATUS}: {TRIGGER.NAME}');
define('ACTION_DEFAULT_SUBJ_AUTOREG', 'Auto registration: {HOST.HOST}');
define('ACTION_DEFAULT_SUBJ_DISCOVERY', 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}');

define('ACTION_DEFAULT_MSG_TRIGGER', "Trigger: {TRIGGER.NAME}\nTrigger status: {TRIGGER.STATUS}\n".
		"Trigger severity: {TRIGGER.SEVERITY}\nTrigger URL: {TRIGGER.URL}\n\nItem values:\n\n".
		"1. {ITEM.NAME1} ({HOST.NAME1}:{ITEM.KEY1}): {ITEM.VALUE1}\n".
		"2. {ITEM.NAME2} ({HOST.NAME2}:{ITEM.KEY2}): {ITEM.VALUE2}\n".
		"3. {ITEM.NAME3} ({HOST.NAME3}:{ITEM.KEY3}): {ITEM.VALUE3}\n\n".
		"Original event ID: {EVENT.ID}"
);
define('ACTION_DEFAULT_MSG_AUTOREG', "Host name: {HOST.HOST}\nHost IP: {HOST.IP}\nAgent port: {HOST.PORT}");
define('ACTION_DEFAULT_MSG_DISCOVERY', "Discovery rule: {DISCOVERY.RULE.NAME}\n\nDevice IP:{DISCOVERY.DEVICE.IPADDRESS}\n".
		"Device DNS: {DISCOVERY.DEVICE.DNS}\nDevice status: {DISCOVERY.DEVICE.STATUS}\n".
		"Device uptime: {DISCOVERY.DEVICE.UPTIME}\n\nDevice service name: {DISCOVERY.SERVICE.NAME}\n".
		"Device service port: {DISCOVERY.SERVICE.PORT}\nDevice service status: {DISCOVERY.SERVICE.STATUS}\n".
		"Device service uptime: {DISCOVERY.SERVICE.UPTIME}"
);

define('ACTION_STATUS_ENABLED',		0);
define('ACTION_STATUS_DISABLED',	1);

define('OPERATION_TYPE_MESSAGE',		0);
define('OPERATION_TYPE_COMMAND',		1);
define('OPERATION_TYPE_HOST_ADD',		2);
define('OPERATION_TYPE_HOST_REMOVE',	3);
define('OPERATION_TYPE_GROUP_ADD',		4);
define('OPERATION_TYPE_GROUP_REMOVE',	5);
define('OPERATION_TYPE_TEMPLATE_ADD',	6);
define('OPERATION_TYPE_TEMPLATE_REMOVE',7);
define('OPERATION_TYPE_HOST_ENABLE',	8);
define('OPERATION_TYPE_HOST_DISABLE',	9);

define('ACTION_EVAL_TYPE_AND_OR',	0);
define('ACTION_EVAL_TYPE_AND',		1);
define('ACTION_EVAL_TYPE_OR',		2);

// screen
define('SCREEN_RESOURCE_GRAPH',				0);
define('SCREEN_RESOURCE_SIMPLE_GRAPH',		1);
define('SCREEN_RESOURCE_MAP',				2);
define('SCREEN_RESOURCE_PLAIN_TEXT',		3);
define('SCREEN_RESOURCE_HOSTS_INFO',		4);
define('SCREEN_RESOURCE_TRIGGERS_INFO',		5);
define('SCREEN_RESOURCE_SERVER_INFO',		6);
define('SCREEN_RESOURCE_CLOCK',				7);
define('SCREEN_RESOURCE_SCREEN',			8);
define('SCREEN_RESOURCE_TRIGGERS_OVERVIEW',	9);
define('SCREEN_RESOURCE_DATA_OVERVIEW',		10);
define('SCREEN_RESOURCE_URL',				11);
define('SCREEN_RESOURCE_ACTIONS',			12);
define('SCREEN_RESOURCE_EVENTS',			13);
define('SCREEN_RESOURCE_HOSTGROUP_TRIGGERS',14);
define('SCREEN_RESOURCE_SYSTEM_STATUS',		15);
define('SCREEN_RESOURCE_HOST_TRIGGERS',		16);
// used in Monitoring > Latest data > Graph (history.php)
define('SCREEN_RESOURCE_HISTORY',			17);
define('SCREEN_RESOURCE_CHART',				18);

define('SCREEN_SORT_TRIGGERS_DATE_DESC',			0);
define('SCREEN_SORT_TRIGGERS_SEVERITY_DESC',		1);
define('SCREEN_SORT_TRIGGERS_HOST_NAME_ASC',		2);
define('SCREEN_SORT_TRIGGERS_TIME_ASC',				3);
define('SCREEN_SORT_TRIGGERS_TIME_DESC',			4);
define('SCREEN_SORT_TRIGGERS_TYPE_ASC',				5);
define('SCREEN_SORT_TRIGGERS_TYPE_DESC',			6);
define('SCREEN_SORT_TRIGGERS_STATUS_ASC',			7);
define('SCREEN_SORT_TRIGGERS_STATUS_DESC',			8);
define('SCREEN_SORT_TRIGGERS_RETRIES_LEFT_ASC',		9);
define('SCREEN_SORT_TRIGGERS_RETRIES_LEFT_DESC',	10);
define('SCREEN_SORT_TRIGGERS_RECIPIENT_ASC',		11);
define('SCREEN_SORT_TRIGGERS_RECIPIENT_DESC',		12);

define('SCREEN_MODE_PREVIEW',	0);
define('SCREEN_MODE_EDIT',		1);
define('SCREEN_MODE_SLIDESHOW',		2);
define('SCREEN_MODE_JS',		3);

define('SCREEN_SIMPLE_ITEM',	0);
define('SCREEN_DYNAMIC_ITEM',	1);

define('SCREEN_REFRESH_TIMEOUT',		30);
define('SCREEN_REFRESH_RESPONSIVENESS',	10);

define('DEFAULT_LATEST_ISSUES_CNT', 20);

// alignments
define('HALIGN_DEFAULT',	0);
define('HALIGN_CENTER',		0);
define('HALIGN_LEFT',		1);
define('HALIGN_RIGHT',		2);

define('VALIGN_DEFAULT',	0);
define('VALIGN_MIDDLE',		0);
define('VALIGN_TOP',		1);
define('VALIGN_BOTTOM',		2);

// info module style
define('STYLE_HORIZONTAL',	0);
define('STYLE_VERTICAL',	1);

// view style [Overview]
define('STYLE_LEFT',	0);
define('STYLE_TOP',		1);

// time module type
define('TIME_TYPE_LOCAL',	0);
define('TIME_TYPE_SERVER',	1);
define('TIME_TYPE_HOST',	2);

define('FILTER_TASK_SHOW',			0);
define('FILTER_TASK_HIDE',			1);
define('FILTER_TASK_MARK',			2);
define('FILTER_TASK_INVERT_MARK',	3);

define('MARK_COLOR_RED',	1);
define('MARK_COLOR_GREEN',	2);
define('MARK_COLOR_BLUE',	3);

define('PROFILE_TYPE_ID',			1);
define('PROFILE_TYPE_INT',			2);
define('PROFILE_TYPE_STR',			3);

define('CALC_FNC_MIN', 1);
define('CALC_FNC_AVG', 2);
define('CALC_FNC_MAX', 4);
define('CALC_FNC_ALL', 7);
define('CALC_FNC_LST', 9);

define('SERVICE_TIME_TYPE_UPTIME',				0);
define('SERVICE_TIME_TYPE_DOWNTIME',			1);
define('SERVICE_TIME_TYPE_ONETIME_DOWNTIME',	2);

define('USER_TYPE_ZABBIX_USER',		1);
define('USER_TYPE_ZABBIX_ADMIN',	2);
define('USER_TYPE_SUPER_ADMIN',		3);

define('ZBX_NOT_INTERNAL_GROUP',	0);
define('ZBX_INTERNAL_GROUP',		1);

define('GROUP_STATUS_DISABLED', 1);
define('GROUP_STATUS_ENABLED',	0);

define('LINE_TYPE_NORMAL',	0);
define('LINE_TYPE_BOLD',	1);

// IMPORTANT!!! by priority DESC
define('GROUP_GUI_ACCESS_SYSTEM',	0);
define('GROUP_GUI_ACCESS_INTERNAL', 1);
define('GROUP_GUI_ACCESS_DISABLED', 2);

/**
 * @see access_deny()
 */
define('ACCESS_DENY_OBJECT', 0);
define('ACCESS_DENY_PAGE', 1);

define('GROUP_DEBUG_MODE_DISABLED', 0);
define('GROUP_DEBUG_MODE_ENABLED',	1);

define('PERM_READ_WRITE',	3);
define('PERM_READ',			2);
define('PERM_DENY',			0);

define('PERM_RES_IDS_ARRAY',	1); // return array of nodes id - array(1,2,3,4)
define('PERM_RES_DATA_ARRAY',	2);

define('PARAM_TYPE_TIME',		0);
define('PARAM_TYPE_COUNTS',		1);

define('ZBX_NODE_CHILD',	0);
define('ZBX_NODE_LOCAL',	1);
define('ZBX_NODE_MASTER',	2);

define('HTTPTEST_AUTH_NONE',	0);
define('HTTPTEST_AUTH_BASIC',	1);
define('HTTPTEST_AUTH_NTLM',	2);

define('HTTPTEST_STATUS_ACTIVE',	0);
define('HTTPTEST_STATUS_DISABLED',	1);

define('HTTPSTEP_ITEM_TYPE_RSPCODE',	0);
define('HTTPSTEP_ITEM_TYPE_TIME',		1);
define('HTTPSTEP_ITEM_TYPE_IN',			2);
define('HTTPSTEP_ITEM_TYPE_LASTSTEP',	3);
define('HTTPSTEP_ITEM_TYPE_LASTERROR',	4);

define('EVENT_ACK_DISABLED',	'0');
define('EVENT_ACK_ENABLED',		'1');

define('EVENT_NOT_ACKNOWLEDGED',	'0');
define('EVENT_ACKNOWLEDGED',		'1');

define('EVENT_SOURCE_TRIGGERS',				0);
define('EVENT_SOURCE_DISCOVERY',			1);
define('EVENT_SOURCE_AUTO_REGISTRATION',	2);
define('EVENT_SOURCE_INTERNAL', 			3);

define('EVENT_OBJECT_TRIGGER',			0);
define('EVENT_OBJECT_DHOST',			1);
define('EVENT_OBJECT_DSERVICE',			2);
define('EVENT_OBJECT_AUTOREGHOST',		3);
define('EVENT_OBJECT_ITEM',				4);
define('EVENT_OBJECT_LLDRULE',			5);

define('GRAPH_YAXIS_TYPE_CALCULATED',	0);
define('GRAPH_YAXIS_TYPE_FIXED',		1);
define('GRAPH_YAXIS_TYPE_ITEM_VALUE',	2);

define('GRAPH_YAXIS_SIDE_LEFT',		0);
define('GRAPH_YAXIS_SIDE_RIGHT',	1);

define('GRAPH_ITEM_SIMPLE',			0);
define('GRAPH_ITEM_SUM',			2);

define('GRAPH_TYPE_NORMAL',			0);
define('GRAPH_TYPE_STACKED',		1);
define('GRAPH_TYPE_PIE',			2);
define('GRAPH_TYPE_EXPLODED',		3);
define('GRAPH_TYPE_3D',				4);
define('GRAPH_TYPE_3D_EXPLODED',	5);
define('GRAPH_TYPE_BAR',			6);
define('GRAPH_TYPE_COLUMN',			7);
define('GRAPH_TYPE_BAR_STACKED',	8);
define('GRAPH_TYPE_COLUMN_STACKED',	9);

define('GRAPH_3D_ANGLE', 70);

define('GRAPH_STACKED_ALFA', 15); // 0..100 transparency

define('GRAPH_ZERO_LINE_COLOR_LEFT',	'AAAAAA');
define('GRAPH_ZERO_LINE_COLOR_RIGHT',	'888888');

define('GRAPH_TRIGGER_LINE_OPPOSITE_COLOR', '000');

define('ZBX_MAX_TREND_DIFF', 3600);

define('ZBX_GRAPH_MAX_SKIP_CELL',	16);
define('ZBX_GRAPH_MAX_SKIP_DELAY',	4);

define('DOBJECT_STATUS_UP',			0);
define('DOBJECT_STATUS_DOWN',		1);
define('DOBJECT_STATUS_DISCOVER',	2); // only for events
define('DOBJECT_STATUS_LOST',		3); // generated by discovery

define('DRULE_STATUS_ACTIVE',		0);
define('DRULE_STATUS_DISABLED',		1);

define('DSVC_STATUS_ACTIVE',		0);
define('DSVC_STATUS_DISABLED',		1);

define('SVC_SSH',		0);
define('SVC_LDAP',		1);
define('SVC_SMTP',		2);
define('SVC_FTP',		3);
define('SVC_HTTP',		4);
define('SVC_POP',		5);
define('SVC_NNTP',		6);
define('SVC_IMAP',		7);
define('SVC_TCP',		8);
define('SVC_AGENT',		9);
define('SVC_SNMPv1',	10);
define('SVC_SNMPv2c',	11);
define('SVC_ICMPPING',	12);
define('SVC_SNMPv3',	13);
define('SVC_HTTPS',		14);
define('SVC_TELNET',	15);

define('DHOST_STATUS_ACTIVE',	0);
define('DHOST_STATUS_DISABLED', 1);

define('IM_FORCED',			0);
define('IM_ESTABLISHED',	1);
define('IM_TREE',			2);

define('EXPRESSION_TYPE_INCLUDED',		0);
define('EXPRESSION_TYPE_ANY_INCLUDED',	1);
define('EXPRESSION_TYPE_NOT_INCLUDED',	2);
define('EXPRESSION_TYPE_TRUE',			3);
define('EXPRESSION_TYPE_FALSE',			4);

define('HOST_INVENTORY_DISABLED',	-1);
define('HOST_INVENTORY_MANUAL',		0);
define('HOST_INVENTORY_AUTOMATIC',	1);

define('EXPRESSION_HOST_UNKNOWN',		'#ERROR_HOST#');
define('EXPRESSION_HOST_ITEM_UNKNOWN',	'#ERROR_ITEM#');
define('EXPRESSION_NOT_A_MACRO_ERROR',	'#ERROR_MACRO#');
define('EXPRESSION_FUNCTION_UNKNOWN',	'#ERROR_FUNCTION#');

define('SBR',	"<br/>\n");
define('SPACE',	'&nbsp;');
define('RARR',	'&rArr;');
define('SQUAREBRACKETS', '%5B%5D');
define('NAME_DELIMITER', ': ');
define('UNKNOWN_VALUE', '-');

define('REGEXP_INCLUDE', 0);
define('REGEXP_EXCLUDE', 1);

// suffixes
define('ZBX_BYTE_SUFFIXES', 'KMGT');
define('ZBX_TIME_SUFFIXES', 'smhdw');

// preg
define('ZBX_PREG_PRINT', '^\x{00}-\x{1F}');
define('ZBX_PREG_MACRO_NAME', '([A-Z0-9\._]+)');
define('ZBX_PREG_MACRO_NAME_LLD', '([A-Z0-9\._]+)');
define('ZBX_PREG_INTERNAL_NAMES', '([0-9a-zA-Z_\. \-]+)'); // !!! Don't forget sync code with C !!!
define('ZBX_PREG_PARAMS', '(['.ZBX_PREG_PRINT.']+?)?');
define('ZBX_PREG_NUMBER', '([\-+]?[0-9]+[.]?[0-9]*['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?)');
define('ZBX_PREG_DEF_FONT_STRING', '/^[0-9\.:% ]+$/');
define('ZBX_PREG_DNS_FORMAT', '([0-9a-zA-Z_\.\-$]|\{\$?'.ZBX_PREG_MACRO_NAME.'\})*');
define('ZBX_PREG_HOST_FORMAT', ZBX_PREG_INTERNAL_NAMES);
define('ZBX_PREG_NODE_FORMAT', ZBX_PREG_INTERNAL_NAMES);
define('ZBX_PREG_MACRO_NAME_FORMAT', '(\{[A-Z\.]+\})');
define('ZBX_PREG_EXPRESSION_USER_MACROS', '(\{\$'.ZBX_PREG_MACRO_NAME.'\})');

// !!! should be used with "x" modifier
define('ZBX_PREG_ITEM_KEY_PARAMETER_FORMAT', '(
	(?P>param) # match recursive parameter group
	|
	(\" # match quoted string
		(
			((\\\\)+?[^\\\\]) # match any amount of backslash with non-backslash ending
			|
			[^\"\\\\] # match any character except \ or "
		)*? # match \" or any character except "
	\")
	|
	[^\"\[\],][^,\]]*? #match unquoted string - any character except " [ ] and , at begining and any character except , and ] afterwards
	|
	() # match empty and only empty part
)');
define('ZBX_PREG_ITEM_KEY_FORMAT', '([0-9a-zA-Z_\. \-]+? # match key
(?P<param>( # name parameter group used in recursion
	\[ # match opening bracket
		(
			\s*?'.ZBX_PREG_ITEM_KEY_PARAMETER_FORMAT .' # match spaces and parameter
			(
				\s*?,\s*? # match spaces, comma and spaces
				'.ZBX_PREG_ITEM_KEY_PARAMETER_FORMAT .' # match parameter
			)*? # match spaces, comma, spaces, parameter zero or more times
			\s*? #matches spaces
		)
	\] # match closing bracket
))*? # matches non comma seperated brackets with parameters zero or more times
)');

define('ZBX_USER_ONLINE_TIME', 600); // 10min
define('ZBX_GUEST_USER','guest');

// IPMI
define('IPMI_AUTHTYPE_DEFAULT',		-1);
define('IPMI_AUTHTYPE_NONE',		0);
define('IPMI_AUTHTYPE_MD2',			1);
define('IPMI_AUTHTYPE_MD5',			2);
define('IPMI_AUTHTYPE_STRAIGHT',	4);
define('IPMI_AUTHTYPE_OEM',			5);
define('IPMI_AUTHTYPE_RMCP_PLUS',	6);

define('IPMI_PRIVILEGE_CALLBACK',	1);
define('IPMI_PRIVILEGE_USER',		2);
define('IPMI_PRIVILEGE_OPERATOR',	3);
define('IPMI_PRIVILEGE_ADMIN',		4);
define('IPMI_PRIVILEGE_OEM',		5);

define('ZBX_HAVE_IPV6', 1);

define('ZBX_SOCKET_TIMEOUT',        3);         // socket timeout limit
define('ZBX_SOCKET_BYTES_LIMIT',    1048576);   // socket response size limit, 1048576 is 1MB in bytes

// value is also used in servercheck.js file
define('SERVER_CHECK_INTERVAL', 10);

// XML export|import tags
define('XML_TAG_MACRO',				'macro');
define('XML_TAG_HOST',				'host');
define('XML_TAG_HOSTINVENTORY',		'host_inventory');
define('XML_TAG_ITEM',				'item');
define('XML_TAG_TRIGGER',			'trigger');
define('XML_TAG_GRAPH',				'graph');
define('XML_TAG_GRAPH_ELEMENT',		'graph_element');
define('XML_TAG_DEPENDENCY',		'dependency');

define('ZBX_DEFAULT_IMPORT_HOST_GROUP', 'Imported hosts');

// XML import flags
// See ZBX-8151. Old version of libxml suffered from setting DTDLOAD and NOENT flags by default, which allowed
// performing XXE attacks. Calling libxml_disable_entity_loader(true) also had no affect if flags passed to libxml
// calls were 0 - so for better security with legacy libxml we need to call libxml_disable_entity_loader(true) AND
// pass the LIBXML_NONET flag. Please keep in mind that LIBXML_NOENT actually EXPANDS entities, opposite to it's name -
// so this flag is not needed here.
define('LIBXML_IMPORT_FLAGS', LIBXML_NONET);

// API errors
define('ZBX_API_ERROR_INTERNAL',	111);
define('ZBX_API_ERROR_PARAMETERS',	100);
define('ZBX_API_ERROR_PERMISSIONS',	120);
define('ZBX_API_ERROR_NO_AUTH',		200);
define('ZBX_API_ERROR_NO_METHOD',	300);

define('API_OUTPUT_REFER',		'refer');
define('API_OUTPUT_EXTEND',		'extend');
define('API_OUTPUT_COUNT',		'count');

define('SEC_PER_MIN',	60);
define('SEC_PER_HOUR',	3600);
define('SEC_PER_DAY',	86400);
define('SEC_PER_WEEK',	604800); // 7 * SEC_PER_DAY
define('SEC_PER_MONTH',	2592000); // 30 * SEC_PER_DAY
define('SEC_PER_YEAR',	31536000); // 365 * SEC_PER_DAY

define('ZBX_JAN_2038', 2145916800);

define('DAY_IN_YEAR', 365);

define('ZBX_MIN_PORT_NUMBER', 0);
define('ZBX_MAX_PORT_NUMBER', 65535);

// input fields
define('ZBX_TEXTBOX_STANDARD_SIZE',		50);
define('ZBX_TEXTBOX_SMALL_SIZE',		25);
define('ZBX_TEXTBOX_FILTER_SIZE',		20);
define('ZBX_TEXTAREA_STANDARD_WIDTH',	312);
define('ZBX_TEXTAREA_BIG_WIDTH',		524);
define('ZBX_TEXTAREA_STANDARD_ROWS',	7);

// validation
define('DB_ID',		"({}>=0&&bccomp({},\"100000000000000000\")<0)&&");
define('NOT_EMPTY',	"({}!='')&&");
define('NOT_ZERO',	"({}!=0)&&");
define('NO_TRIM',	'NO_TRIM');

define('ZBX_VALID_OK',		0);
define('ZBX_VALID_ERROR',	1);
define('ZBX_VALID_WARNING',	2);

// user default theme
define('THEME_DEFAULT', 'default');

// the default theme
define('ZBX_DEFAULT_THEME', 'originalblue');

define('ZABBIX_HOMEPAGE', 'http://www.zabbix.com');

// non translatable date formats
define('TIMESTAMP_FORMAT', 'YmdHis');
define('TIMESTAMP_FORMAT_ZERO_TIME', 'Ymd0000');

// actions
define('LONG_DESCRIPTION',	0);
define('SHORT_DESCRIPTION',	1);

// availability report modes
define('AVAILABILITY_REPORT_BY_HOST', 0);
define('AVAILABILITY_REPORT_BY_TEMPLATE', 1);

// queue modes
define('QUEUE_OVERVIEW', 0);
define('QUEUE_OVERVIEW_BY_PROXY', 1);
define('QUEUE_DETAILS', 2);

// item count to display in the details queue
define('QUEUE_DETAIL_ITEM_COUNT', 500);

// configuration -> maps default add icon name
define('MAP_DEFAULT_ICON', 'Server_(96)');

// server variables
define('HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off');

// if magic quotes on, then get rid of them
if (get_magic_quotes_gpc()) {
	function zbx_stripslashes($value) {
		$value = is_array($value) ? array_map('zbx_stripslashes', $value) : stripslashes($value);
		return $value;
	}
	$_GET = zbx_stripslashes($_GET);
	$_POST = zbx_stripslashes($_POST);
	$_COOKIE = zbx_stripslashes($_COOKIE);
}

// init $_REQUEST
ini_set('variables_order', 'GP');
$_REQUEST = $_POST + $_GET;

// init precision
ini_set('precision', 14);

// BC Math scale. bcscale() can be undefined prior requirement check in setup.
if (function_exists('bcscale')) {
	bcscale(7);
}
