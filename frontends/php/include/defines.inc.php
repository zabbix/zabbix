<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


define('ZABBIX_VERSION',		'4.0.0alpha6');
define('ZABBIX_API_VERSION',	'4.0.0');
define('ZABBIX_EXPORT_VERSION',	'3.4');
define('ZABBIX_DB_VERSION',		3050045);

define('ZABBIX_COPYRIGHT_FROM',	'2001');
define('ZABBIX_COPYRIGHT_TO',	'2018');

define('ZBX_LOGIN_ATTEMPTS',	5);
define('ZBX_LOGIN_BLOCK',		30); // sec

define('ZBX_MIN_PERIOD',		60); // 1 minute
define('ZBX_MAX_PERIOD',		63072000); // the maximum period for the time bar control, ~2 years (2 * 365 * 86400)
define('ZBX_MIN_INT32',			-2147483648);
define('ZBX_MAX_INT32',			2147483647);
define('ZBX_MAX_DATE',			2147483647); // 19 Jan 2038 05:14:07
define('ZBX_PERIOD_DEFAULT',	3600); // 1 hour

// the maximum period to display history data for the latest data and item overview pages in seconds
// by default set to 86400 seconds (24 hours)
define('ZBX_HISTORY_PERIOD', 86400);

define('ZBX_HISTORY_SOURCE_ELASTIC',	'elastic');
define('ZBX_HISTORY_SOURCE_SQL',		'sql');

define('ELASTICSEARCH_RESPONSE_PLAIN',			0);
define('ELASTICSEARCH_RESPONSE_AGGREGATION',	1);
define('ELASTICSEARCH_RESPONSE_DOCUMENTS',		2);

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
define('ZBX_SCRIPT_EXECUTE_ON_PROXY',	2);

define('ZBX_FLAG_DISCOVERY_NORMAL',		0x0);
define('ZBX_FLAG_DISCOVERY_RULE',		0x1);
define('ZBX_FLAG_DISCOVERY_PROTOTYPE',	0x2);
define('ZBX_FLAG_DISCOVERY_CREATED',	0x4);

define('EXTACK_OPTION_ALL',		0);
define('EXTACK_OPTION_UNACK',	1);
define('EXTACK_OPTION_BOTH',	2);

define('TRIGGERS_OPTION_RECENT_PROBLEM',	1);
define('TRIGGERS_OPTION_ALL',				2);
define('TRIGGERS_OPTION_IN_PROBLEM',		3);

define('ZBX_ACK_STS_ANY',				1);
define('ZBX_ACK_STS_WITH_UNACK',		2);
define('ZBX_ACK_STS_WITH_LAST_UNACK',	3);

define('ZBX_FONT_NAME', 'DejaVuSans');

define('ZBX_AUTH_INTERNAL',	0);
define('ZBX_AUTH_LDAP',		1);
define('ZBX_AUTH_HTTP',		2);

define('ZBX_DB_DB2',		'IBM_DB2');
define('ZBX_DB_MYSQL',		'MYSQL');
define('ZBX_DB_ORACLE',		'ORACLE');
define('ZBX_DB_POSTGRESQL',	'POSTGRESQL');

define('ZBX_DB_MAX_ID', '9223372036854775807');

// maximum number of records for create() or update() API calls
define('ZBX_DB_MAX_INSERTS', 10000);

define('ZBX_SHOW_TECHNICAL_ERRORS', false);

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
define('T_ZBX_CLR',			5);
define('T_ZBX_DBL_BIG',		9);
define('T_ZBX_DBL_STR',		10);
define('T_ZBX_TP',			11);
define('T_ZBX_TU',			12);

define('O_MAND',	0);
define('O_OPT',		1);
define('O_NO',		2);

define('P_SYS',					0x0001);
define('P_UNSET_EMPTY',			0x0002);
define('P_CRLF',				0x0004);
define('P_ACT',					0x0010);
define('P_NZERO',				0x0020);
define('P_NO_TRIM',				0x0040);
define('P_ALLOW_USER_MACRO',	0x0080);
define('P_ALLOW_LLD_MACRO',		0x0100);
define('ZBX_URI_VALID_SCHEMES', 'http,https,ftp,file,mailto,tel,ssh');

// Validate URI against schemes whitelist defined in ZBX_URI_VALID_SCHEMES.
define('VALIDATE_URI_SCHEMES', true);

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

define('ZBX_TCP_HEADER_DATA',		"ZBXD");
define('ZBX_TCP_HEADER_VERSION',	"\1");
define('ZBX_TCP_HEADER',			ZBX_TCP_HEADER_DATA.ZBX_TCP_HEADER_VERSION);
define('ZBX_TCP_HEADER_LEN',		5);
define('ZBX_TCP_DATALEN_LEN',		8);

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
define('AUDIT_RESOURCE_ICON_MAP',		32);
define('AUDIT_RESOURCE_DASHBOARD',		33);
define('AUDIT_RESOURCE_CORRELATION',	34);

define('CONDITION_TYPE_HOST_GROUP',			0);
define('CONDITION_TYPE_HOST',				1);
define('CONDITION_TYPE_TRIGGER',			2);
define('CONDITION_TYPE_TRIGGER_NAME',		3);
define('CONDITION_TYPE_TRIGGER_SEVERITY',	4);
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
define('CONDITION_TYPE_DRULE',				18);
define('CONDITION_TYPE_DCHECK',				19);
define('CONDITION_TYPE_PROXY',				20);
define('CONDITION_TYPE_DOBJECT',			21);
define('CONDITION_TYPE_HOST_NAME',			22);
define('CONDITION_TYPE_EVENT_TYPE',			23);
define('CONDITION_TYPE_HOST_METADATA',		24);
define('CONDITION_TYPE_EVENT_TAG',			25);
define('CONDITION_TYPE_EVENT_TAG_VALUE',	26);

define('CONDITION_OPERATOR_EQUAL',		0);
define('CONDITION_OPERATOR_NOT_EQUAL',	1);
define('CONDITION_OPERATOR_LIKE',		2);
define('CONDITION_OPERATOR_NOT_LIKE',	3);
define('CONDITION_OPERATOR_IN',			4);
define('CONDITION_OPERATOR_MORE_EQUAL',	5);
define('CONDITION_OPERATOR_LESS_EQUAL',	6);
define('CONDITION_OPERATOR_NOT_IN',		7);
define('CONDITION_OPERATOR_REGEXP',		8);

// correlation statuses
define('ZBX_CORRELATION_ENABLED',		0);
define('ZBX_CORRELATION_DISABLED',		1);

// correlation condition types
define('ZBX_CORR_CONDITION_OLD_EVENT_TAG',			0);
define('ZBX_CORR_CONDITION_NEW_EVENT_TAG',			1);
define('ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP',	2);
define('ZBX_CORR_CONDITION_EVENT_TAG_PAIR',			3);
define('ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE',	4);
define('ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE',	5);

// correlation operation types
define('ZBX_CORR_OPERATION_CLOSE_OLD',	0);
define('ZBX_CORR_OPERATION_CLOSE_NEW',	1);

// event type action condition values
define('EVENT_TYPE_ITEM_NOTSUPPORTED',		0);
define('EVENT_TYPE_LLDRULE_NOTSUPPORTED',	2);
define('EVENT_TYPE_TRIGGER_UNKNOWN',		4);

define('HOST_STATUS_MONITORED',		0);
define('HOST_STATUS_NOT_MONITORED',	1);
define('HOST_STATUS_TEMPLATE',		3);
define('HOST_STATUS_PROXY_ACTIVE',	5);
define('HOST_STATUS_PROXY_PASSIVE',	6);

define('HOST_ENCRYPTION_NONE',			1);
define('HOST_ENCRYPTION_PSK',			2);
define('HOST_ENCRYPTION_CERTIFICATE',	4);

define('PSK_MIN_LEN',	32);

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

define('SNMP_BULK_DISABLED',	0);
define('SNMP_BULK_ENABLED',		1);

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

// report periods
define('REPORT_PERIOD_TODAY',			0);
define('REPORT_PERIOD_YESTERDAY',		1);
define('REPORT_PERIOD_CURRENT_WEEK',	2);
define('REPORT_PERIOD_CURRENT_MONTH',	3);
define('REPORT_PERIOD_CURRENT_YEAR',	4);
define('REPORT_PERIOD_LAST_WEEK',		5);
define('REPORT_PERIOD_LAST_MONTH',		6);
define('REPORT_PERIOD_LAST_YEAR',		7);

define('SYSMAP_LABEL_ADVANCED_OFF',	0);
define('SYSMAP_LABEL_ADVANCED_ON',	1);

define('SYSMAP_PROBLEMS_NUMBER',			0);
define('SYSMAP_SINGLE_PROBLEM',				1);
define('SYSMAP_PROBLEMS_NUMBER_CRITICAL',	2);

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

define('SYSMAP_SHAPE_TYPE_RECTANGLE',		0);
define('SYSMAP_SHAPE_TYPE_ELLIPSE',			1);
define('SYSMAP_SHAPE_TYPE_LINE',			2);

define('SYSMAP_SHAPE_BORDER_TYPE_NONE',		0);
define('SYSMAP_SHAPE_BORDER_TYPE_SOLID',	1);
define('SYSMAP_SHAPE_BORDER_TYPE_DOTTED',	2);
define('SYSMAP_SHAPE_BORDER_TYPE_DASHED',	3);

define('SYSMAP_SHAPE_LABEL_HALIGN_CENTER',	0);
define('SYSMAP_SHAPE_LABEL_HALIGN_LEFT',	1);
define('SYSMAP_SHAPE_LABEL_HALIGN_RIGHT',	2);

define('SYSMAP_SHAPE_LABEL_VALIGN_MIDDLE',	0);
define('SYSMAP_SHAPE_LABEL_VALIGN_TOP',		1);
define('SYSMAP_SHAPE_LABEL_VALIGN_BOTTOM',	2);

define('SYSMAP_HIGHLIGHT_OFF',	0);
define('SYSMAP_HIGHLIGHT_ON',	1);

define('SYSMAP_GRID_SHOW_ON',	1);
define('SYSMAP_GRID_SHOW_OFF',	0);

define('SYSMAP_EXPAND_MACROS_OFF',	0);
define('SYSMAP_EXPAND_MACROS_ON',	1);

define('SYSMAP_GRID_ALIGN_ON',	1);
define('SYSMAP_GRID_ALIGN_OFF',	0);

define('PUBLIC_SHARING',	0);
define('PRIVATE_SHARING',	1);

define('ZBX_ITEM_DELAY_DEFAULT',			'30s');
define('ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT',	'50s');
define('ZBX_ITEM_SCHEDULING_DEFAULT',		'wd1-5h9-18');

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
define('ITEM_TYPE_SNMPTRAP',		17);
define('ITEM_TYPE_DEPENDENT',		18);

define('ZBX_DEPENDENT_ITEM_MAX_LEVELS',	3);
define('ZBX_DEPENDENT_ITEM_MAX_COUNT',	999);

define('ITEM_VALUE_TYPE_FLOAT',		0);
define('ITEM_VALUE_TYPE_STR',		1); // aka Character
define('ITEM_VALUE_TYPE_LOG',		2);
define('ITEM_VALUE_TYPE_UINT64',	3);
define('ITEM_VALUE_TYPE_TEXT',		4);

define('ITEM_DATA_TYPE_DECIMAL',		0);
define('ITEM_DATA_TYPE_OCTAL',			1);
define('ITEM_DATA_TYPE_HEXADECIMAL',	2);
define('ITEM_DATA_TYPE_BOOLEAN',		3);

define('ZBX_DEFAULT_KEY_DB_MONITOR',			'db.odbc.select[<unique short description>,<dsn>]');
define('ZBX_DEFAULT_KEY_DB_MONITOR_DISCOVERY',	'db.odbc.discovery[<unique short description>,<dsn>]');
define('ZBX_DEFAULT_KEY_SSH',					'ssh.run[<unique short description>,<ip>,<port>,<encoding>]');
define('ZBX_DEFAULT_KEY_TELNET',				'telnet.run[<unique short description>,<ip>,<port>,<encoding>]');

define('ZBX_DEFAULT_JMX_ENDPOINT',	'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi');

define('SYSMAP_ELEMENT_USE_ICONMAP_ON',		1);
define('SYSMAP_ELEMENT_USE_ICONMAP_OFF',	0);

define('ZBX_ICON_PREVIEW_HEIGHT',	24);
define('ZBX_ICON_PREVIEW_WIDTH',	24);

define('ITEM_STATUS_ACTIVE',		0);
define('ITEM_STATUS_DISABLED',		1);

/**
 * Starting from Zabbix 2.2 items could not have ITEM_STATUS_NOTSUPPORTED status
 * this constant is left for importing data from versions 1.8 and 2.0.
 */
define('ITEM_STATUS_NOTSUPPORTED',	3);

define('ITEM_STATE_NORMAL',			0);
define('ITEM_STATE_NOTSUPPORTED',	1);

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

define('ITEM_DELAY_FLEXIBLE',	0);
define('ITEM_DELAY_SCHEDULING',	1);

// item pre-processing
define('ZBX_PREPROC_MULTIPLIER',	1);
define('ZBX_PREPROC_RTRIM',			2);
define('ZBX_PREPROC_LTRIM',			3);
define('ZBX_PREPROC_TRIM',			4);
define('ZBX_PREPROC_REGSUB',		5);
define('ZBX_PREPROC_BOOL2DEC',		6);
define('ZBX_PREPROC_OCT2DEC',		7);
define('ZBX_PREPROC_HEX2DEC',		8);
define('ZBX_PREPROC_DELTA_VALUE',	9);
define('ZBX_PREPROC_DELTA_SPEED',	10);
define('ZBX_PREPROC_XPATH',			11);
define('ZBX_PREPROC_JSONPATH',		12);

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

define('SERVICE_SLA', '99.9000');

define('SERVICE_SHOW_SLA_OFF',	0);
define('SERVICE_SHOW_SLA_ON',	1);

define('SERVICE_STATUS_OK', 0);

define('TRIGGER_MULT_EVENT_DISABLED',	0);
define('TRIGGER_MULT_EVENT_ENABLED',	1);

define('ZBX_TRIGGER_CORRELATION_NONE',	0);
define('ZBX_TRIGGER_CORRELATION_TAG',	1);

define('ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED',	0);
define('ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED',		1);

define('ZBX_RECOVERY_MODE_EXPRESSION',			0);
define('ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION',	1);
define('ZBX_RECOVERY_MODE_NONE',				2);

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

define('EVENT_CUSTOM_COLOR_DISABLED',	0);
define('EVENT_CUSTOM_COLOR_ENABLED',	1);

define('ALERT_STATUS_NOT_SENT', 0);
define('ALERT_STATUS_SENT',		1);
define('ALERT_STATUS_FAILED',	2);
define('ALERT_STATUS_NEW',		3);

define('ALERT_TYPE_MESSAGE',	0);
define('ALERT_TYPE_COMMAND',	1);

define('MEDIA_STATUS_ACTIVE',	0);
define('MEDIA_STATUS_DISABLED',	1);

define('MEDIA_TYPE_STATUS_ACTIVE',		0);
define('MEDIA_TYPE_STATUS_DISABLED',	1);

define('MEDIA_TYPE_EMAIL',		0);
define('MEDIA_TYPE_EXEC',		1);
define('MEDIA_TYPE_SMS',		2);
define('MEDIA_TYPE_JABBER',		3);
define('MEDIA_TYPE_EZ_TEXTING',	100);

define('SMTP_CONNECTION_SECURITY_NONE',		0);
define('SMTP_CONNECTION_SECURITY_STARTTLS',	1);
define('SMTP_CONNECTION_SECURITY_SSL_TLS',	2);

define('SMTP_AUTHENTICATION_NONE',		0);
define('SMTP_AUTHENTICATION_NORMAL',	1);

define('EZ_TEXTING_LIMIT_USA',		0);
define('EZ_TEXTING_LIMIT_CANADA',	1);

define('ACTION_DEFAULT_SUBJ_AUTOREG', 'Auto registration: {HOST.HOST}');
define('ACTION_DEFAULT_SUBJ_DISCOVERY', 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}');
define('ACTION_DEFAULT_SUBJ_ACKNOWLEDGE', 'Acknowledged: {TRIGGER.NAME}');
define('ACTION_DEFAULT_SUBJ_PROBLEM', 'Problem: {TRIGGER.NAME}');
define('ACTION_DEFAULT_SUBJ_RECOVERY', 'Resolved: {TRIGGER.NAME}');

define('ACTION_DEFAULT_MSG_AUTOREG', "Host name: {HOST.HOST}\nHost IP: {HOST.IP}\nAgent port: {HOST.PORT}");
define('ACTION_DEFAULT_MSG_DISCOVERY', "Discovery rule: {DISCOVERY.RULE.NAME}\n\nDevice IP:{DISCOVERY.DEVICE.IPADDRESS}\n".
		"Device DNS: {DISCOVERY.DEVICE.DNS}\nDevice status: {DISCOVERY.DEVICE.STATUS}\n".
		"Device uptime: {DISCOVERY.DEVICE.UPTIME}\n\nDevice service name: {DISCOVERY.SERVICE.NAME}\n".
		"Device service port: {DISCOVERY.SERVICE.PORT}\nDevice service status: {DISCOVERY.SERVICE.STATUS}\n".
		"Device service uptime: {DISCOVERY.SERVICE.UPTIME}"
);
define('ACTION_DEFAULT_MSG_ACKNOWLEDGE',
		"{USER.FULLNAME} acknowledged problem at {ACK.DATE} {ACK.TIME} with the following message:\n".
		"{ACK.MESSAGE}\n\n".
		"Current problem status is {EVENT.STATUS}"
);
define('ACTION_DEFAULT_MSG_PROBLEM', "Problem started at {EVENT.TIME} on {EVENT.DATE}\nProblem name: {TRIGGER.NAME}\n".
		"Host: {HOST.NAME}\nSeverity: {TRIGGER.SEVERITY}\n\nOriginal problem ID: {EVENT.ID}\n{TRIGGER.URL}");
define('ACTION_DEFAULT_MSG_RECOVERY', "Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
		"Problem name: {TRIGGER.NAME}\nHost: {HOST.NAME}\nSeverity: {TRIGGER.SEVERITY}\n\n".
		"Original problem ID: {EVENT.ID}\n{TRIGGER.URL}");

define('ACTION_STATUS_ENABLED',		0);
define('ACTION_STATUS_DISABLED',	1);

define('ACTION_MAINTENANCE_MODE_NORMAL',	0);
define('ACTION_MAINTENANCE_MODE_PAUSE',		1);

define('OPERATION_TYPE_MESSAGE',			0);
define('OPERATION_TYPE_COMMAND',			1);
define('OPERATION_TYPE_HOST_ADD',			2);
define('OPERATION_TYPE_HOST_REMOVE',		3);
define('OPERATION_TYPE_GROUP_ADD',			4);
define('OPERATION_TYPE_GROUP_REMOVE',		5);
define('OPERATION_TYPE_TEMPLATE_ADD',		6);
define('OPERATION_TYPE_TEMPLATE_REMOVE',	7);
define('OPERATION_TYPE_HOST_ENABLE',		8);
define('OPERATION_TYPE_HOST_DISABLE',		9);
define('OPERATION_TYPE_HOST_INVENTORY',		10);
define('OPERATION_TYPE_RECOVERY_MESSAGE',	11);
define('OPERATION_TYPE_ACK_MESSAGE',		12);

define('ACTION_OPERATION',					0);
define('ACTION_RECOVERY_OPERATION',			1);
define('ACTION_ACKNOWLEDGE_OPERATION',		2);

define('CONDITION_EVAL_TYPE_AND_OR',		0);
define('CONDITION_EVAL_TYPE_AND',			1);
define('CONDITION_EVAL_TYPE_OR',			2);
define('CONDITION_EVAL_TYPE_EXPRESSION', 	3);

// screen
define('SCREEN_RESOURCE_GRAPH',				0);
define('SCREEN_RESOURCE_SIMPLE_GRAPH',		1);
define('SCREEN_RESOURCE_MAP',				2);
define('SCREEN_RESOURCE_PLAIN_TEXT',		3);
define('SCREEN_RESOURCE_HOST_INFO',		4);
define('SCREEN_RESOURCE_TRIGGER_INFO',		5);
define('SCREEN_RESOURCE_SERVER_INFO',		6);
define('SCREEN_RESOURCE_CLOCK',				7);
define('SCREEN_RESOURCE_SCREEN',			8);
define('SCREEN_RESOURCE_TRIGGER_OVERVIEW',	9);
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
define('SCREEN_RESOURCE_LLD_SIMPLE_GRAPH',	19);
define('SCREEN_RESOURCE_LLD_GRAPH',			20);
// used in Monitoring > Web > Details (httpdetails.php)
define('SCREEN_RESOURCE_HTTPTEST_DETAILS',	21);
// used in Monitoring > Discovery
define('SCREEN_RESOURCE_DISCOVERY',			22);
// used in Monitoring > Web
define('SCREEN_RESOURCE_HTTPTEST',			23);
// used in Monitoring > Problems
define('SCREEN_RESOURCE_PROBLEM',			24);

define('SCREEN_SORT_TRIGGERS_DATE_DESC',			0);
define('SCREEN_SORT_TRIGGERS_SEVERITY_DESC',		1);
define('SCREEN_SORT_TRIGGERS_HOST_NAME_ASC',		2);
define('SCREEN_SORT_TRIGGERS_TIME_ASC',				3);
define('SCREEN_SORT_TRIGGERS_TIME_DESC',			4);
define('SCREEN_SORT_TRIGGERS_TYPE_ASC',				5);
define('SCREEN_SORT_TRIGGERS_TYPE_DESC',			6);
define('SCREEN_SORT_TRIGGERS_STATUS_ASC',			7);
define('SCREEN_SORT_TRIGGERS_STATUS_DESC',			8);
define('SCREEN_SORT_TRIGGERS_RECIPIENT_ASC',		11);
define('SCREEN_SORT_TRIGGERS_RECIPIENT_DESC',		12);
define('SCREEN_SORT_TRIGGERS_SEVERITY_ASC',			13);
define('SCREEN_SORT_TRIGGERS_HOST_NAME_DESC',		14);
define('SCREEN_SORT_TRIGGERS_NAME_ASC',				15);
define('SCREEN_SORT_TRIGGERS_NAME_DESC',			16);

define('SCREEN_MODE_PREVIEW',	0);
define('SCREEN_MODE_EDIT',		1);
define('SCREEN_MODE_SLIDESHOW',		2);
define('SCREEN_MODE_JS',		3);

define('SCREEN_SIMPLE_ITEM',	0);
define('SCREEN_DYNAMIC_ITEM',	1);

define('SCREEN_REFRESH_TIMEOUT',		30);
define('SCREEN_REFRESH_RESPONSIVENESS',	10);

define('SCREEN_SURROGATE_MAX_COLUMNS_MIN', 1);
define('SCREEN_SURROGATE_MAX_COLUMNS_DEFAULT', 3);
define('SCREEN_SURROGATE_MAX_COLUMNS_MAX', 100);

define('SCREEN_MIN_SIZE', 1);
define('SCREEN_MAX_SIZE', 100);

// default, minimum and maximum number of lines for dashboard widgets
define('ZBX_DEFAULT_WIDGET_LINES', 25);
define('ZBX_MIN_WIDGET_LINES', 1);
define('ZBX_MAX_WIDGET_LINES', 100);

// dashboards
define('DASHBOARD_MAX_ROWS', 64);
define('DASHBOARD_MAX_COLUMNS', 12);

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
define('PERM_NONE',			-1);

define('PARAM_TYPE_TIME',		0);
define('PARAM_TYPE_COUNTS',		1);

define('ZBX_DEFAULT_AGENT', 'Zabbix');
define('ZBX_AGENT_OTHER', -1);

define('HTTPTEST_AUTH_NONE',	0);
define('HTTPTEST_AUTH_BASIC',	1);
define('HTTPTEST_AUTH_NTLM',	2);

define('HTTPTEST_STATUS_ACTIVE',	0);
define('HTTPTEST_STATUS_DISABLED',	1);

define('ZBX_HTTPFIELD_HEADER',	0);
define('ZBX_HTTPFIELD_VARIABLE',	1);
define('ZBX_HTTPFIELD_POST_FIELD',	2);
define('ZBX_HTTPFIELD_QUERY_FIELD',	3);

define('ZBX_POSTTYPE_RAW',	0);
define('ZBX_POSTTYPE_FORM',	1);

define('HTTPSTEP_ITEM_TYPE_RSPCODE',	0);
define('HTTPSTEP_ITEM_TYPE_TIME',		1);
define('HTTPSTEP_ITEM_TYPE_IN',			2);
define('HTTPSTEP_ITEM_TYPE_LASTSTEP',	3);
define('HTTPSTEP_ITEM_TYPE_LASTERROR',	4);

define('HTTPTEST_STEP_RETRIEVE_MODE_CONTENT', 0);
define('HTTPTEST_STEP_RETRIEVE_MODE_HEADERS', 1);

define('HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF', 0);
define('HTTPTEST_STEP_FOLLOW_REDIRECTS_ON', 1);

define('HTTPTEST_VERIFY_PEER_OFF', 0);
define('HTTPTEST_VERIFY_PEER_ON', 1);

define('HTTPTEST_VERIFY_HOST_OFF', 0);
define('HTTPTEST_VERIFY_HOST_ON', 1);

define('EVENT_ACK_DISABLED',	'0');
define('EVENT_ACK_ENABLED',		'1');

define('EVENT_NOT_ACKNOWLEDGED',	'0');
define('EVENT_ACKNOWLEDGED',		'1');

define('ZBX_ACKNOWLEDGE_SELECTED',	0);
define('ZBX_ACKNOWLEDGE_PROBLEM',	1);

define('ZBX_ACKNOWLEDGE_ACTION_NONE',			0x00);
define('ZBX_ACKNOWLEDGE_ACTION_CLOSE_PROBLEM',	0x01);

define('ZBX_TM_TASK_CLOSE_PROBLEM', 1);
define('ZBX_TM_TASK_ACKNOWLEDGE',	4);
define('ZBX_TM_TASK_CHECK_NOW',		6);

define('ZBX_TM_STATUS_NEW',			1);
define('ZBX_TM_STATUS_INPROGRESS',	2);

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

// Problem and event tag constants.
define('TAG_EVAL_TYPE_AND',		0);
define('TAG_EVAL_TYPE_OR',		1);
define('TAG_OPERATOR_LIKE',		0);
define('TAG_OPERATOR_EQUAL',	1);

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

define('BR_DISTRIBUTION_MULTIPLE_PERIODS',	1);
define('BR_DISTRIBUTION_MULTIPLE_ITEMS',	2);
define('BR_COMPARE_VALUE_MULTIPLE_PERIODS',	3);

define('GRAPH_3D_ANGLE', 70);

define('GRAPH_STACKED_ALFA', 15); // 0..100 transparency

define('GRAPH_ZERO_LINE_COLOR_LEFT',	'AAAAAA');
define('GRAPH_ZERO_LINE_COLOR_RIGHT',	'888888');

define('GRAPH_TRIGGER_LINE_OPPOSITE_COLOR', '000000');

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

define('TRIGGER_EXPRESSION',			0);
define('TRIGGER_RECOVERY_EXPRESSION',	1);

define('EXPRESSION_TYPE_INCLUDED',		0);
define('EXPRESSION_TYPE_ANY_INCLUDED',	1);
define('EXPRESSION_TYPE_NOT_INCLUDED',	2);
define('EXPRESSION_TYPE_TRUE',			3);
define('EXPRESSION_TYPE_FALSE',			4);

define('HOST_INVENTORY_DISABLED',	-1);
define('HOST_INVENTORY_MANUAL',		0);
define('HOST_INVENTORY_AUTOMATIC',	1);

define('EXPRESSION_HOST_UNKNOWN',			'#ERROR_HOST#');
define('EXPRESSION_HOST_ITEM_UNKNOWN',		'#ERROR_ITEM#');
define('EXPRESSION_NOT_A_MACRO_ERROR',		'#ERROR_MACRO#');
define('EXPRESSION_FUNCTION_UNKNOWN',		'#ERROR_FUNCTION#');
define('EXPRESSION_UNSUPPORTED_VALUE_TYPE',	'#ERROR_VALUE_TYPE#');

/**
 * @deprecated use either a literal space " " or a non-breakable space "&nbsp;" instead
 */
define('SPACE',	'&nbsp;');

/**
 * Symbol used to separate name pairs such as "host: item" or "proxy: host".
 *
 * Should not be used as just a colon.
 */
define('NAME_DELIMITER', ': ');

define('UNKNOWN_VALUE', '');

// suffixes
define('ZBX_BYTE_SUFFIXES', 'KMGT');
define('ZBX_TIME_SUFFIXES', 'smhdw');

// preg
define('ZBX_PREG_PRINT', '^\x00-\x1F');
define('ZBX_PREG_MACRO_NAME', '([A-Z0-9\._]+)');
define('ZBX_PREG_MACRO_NAME_LLD', '([A-Z0-9\._]+)');
define('ZBX_PREG_INTERNAL_NAMES', '([0-9a-zA-Z_\. \-]+)'); // !!! Don't forget sync code with C !!!
define('ZBX_PREG_NUMBER', '([\-+]?[0-9]+[.]?[0-9]*['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?)');
define('ZBX_PREG_INT', '([\-+]?[0-9]+['.ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES.']?)');
define('ZBX_PREG_DEF_FONT_STRING', '/^[0-9\.:% ]+$/');
define('ZBX_PREG_DNS_FORMAT', '([0-9a-zA-Z_\.\-$]|\{\$?'.ZBX_PREG_MACRO_NAME.'\})*');
define('ZBX_PREG_HOST_FORMAT', ZBX_PREG_INTERNAL_NAMES);
define('ZBX_PREG_MACRO_NAME_FORMAT', '(\{[A-Z\.]+\})');
define('ZBX_PREG_EXPRESSION_LLD_MACROS', '(\{\#'.ZBX_PREG_MACRO_NAME_LLD.'\})');

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

define('ZBX_HAVE_IPV6', true);
define('ZBX_DISCOVERER_IPRANGE_LIMIT', 65536);

define('ZBX_SOCKET_TIMEOUT',        3);         // socket timeout limit
define('ZBX_SOCKET_BYTES_LIMIT',    1048576);   // socket response size limit, 1048576 is 1MB in bytes

// value is also used in servercheck.js file
define('SERVER_CHECK_INTERVAL', 10);

define('DATE_TIME_FORMAT_SECONDS_XML', 'Y-m-d\TH:i:s\Z');

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

// XML validation
define('XML_STRING',		0x01);
define('XML_ARRAY',			0x02);
define('XML_INDEXED_ARRAY',	0x04);
define('XML_REQUIRED',		0x08);

// API validation
// multiple types
define('API_MULTIPLE',			0);
// scalar data types
define('API_STRING_UTF8',		1);
define('API_INT32',				2);
define('API_ID',				3);
define('API_BOOLEAN',			4);
define('API_FLAG',				5);
// arrays
define('API_OBJECT',			6);
define('API_IDS',				7);
define('API_OBJECTS',			8);
define('API_STRINGS_UTF8',		9);
define('API_INTS32',			10);
// specific types
define('API_HG_NAME',			11);
define('API_SCRIPT_NAME',		12);
define('API_USER_MACRO',		13);
define('API_TIME_PERIOD',		14);
define('API_REGEX',				15);
define('API_HTTP_POST',			16);
define('API_VARIABLE_NAME',		17);
define('API_OUTPUT',			18);
define('API_TIME_UNIT',			19);
define('API_URL',				20);

// flags
define('API_REQUIRED',			0x01);
define('API_NOT_EMPTY',			0x02);
define('API_ALLOW_NULL',		0x04);
define('API_NORMALIZE',			0x08);
define('API_DEPRECATED',		0x10);
define('API_ALLOW_USER_MACRO',	0x20);
define('API_ALLOW_COUNT',	0x40);

// JSON error codes.
if (!defined('JSON_ERROR_NONE')) {
	define('JSON_ERROR_NONE', 0);
}
if (!defined('JSON_ERROR_SYNTAX')) {
	define('JSON_ERROR_SYNTAX', 4);
}

// API errors
define('ZBX_API_ERROR_INTERNAL',	111);
define('ZBX_API_ERROR_PARAMETERS',	100);
define('ZBX_API_ERROR_PERMISSIONS',	120);
define('ZBX_API_ERROR_NO_AUTH',		200);
define('ZBX_API_ERROR_NO_METHOD',	300);

define('API_OUTPUT_EXTEND',		'extend');
define('API_OUTPUT_COUNT',		'count');

define('SEC_PER_MIN',			60);
define('SEC_PER_HOUR',			3600);
define('SEC_PER_DAY',			86400);
define('SEC_PER_WEEK',			604800);
define('SEC_PER_MONTH',			2592000);
define('SEC_PER_YEAR',			31536000);

define('ZBX_JAN_2038', 2145916800);

define('DAY_IN_YEAR', 365);

define('ZBX_MIN_PORT_NUMBER', 0);
define('ZBX_MAX_PORT_NUMBER', 65535);

// input fields
define('ZBX_TEXTAREA_MACRO_WIDTH',				200);
define('ZBX_TEXTAREA_MACRO_VALUE_WIDTH',		250);
define('ZBX_TEXTAREA_COLOR_WIDTH',				96);
define('ZBX_TEXTAREA_FILTER_SMALL_WIDTH',		150);
define('ZBX_TEXTAREA_FILTER_STANDARD_WIDTH',	300);
define('ZBX_TEXTAREA_TINY_WIDTH',				75);
define('ZBX_TEXTAREA_SMALL_WIDTH',				150);
define('ZBX_TEXTAREA_TAG_WIDTH',				218);
define('ZBX_TEXTAREA_MEDIUM_WIDTH',				270);
define('ZBX_TEXTAREA_STANDARD_WIDTH',			453);
define('ZBX_TEXTAREA_BIG_WIDTH',				540);
define('ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH',	75);
define('ZBX_TEXTAREA_NUMERIC_BIG_WIDTH',		150);
define('ZBX_TEXTAREA_2DIGITS_WIDTH',			35);	// please use for date selector only
define('ZBX_TEXTAREA_4DIGITS_WIDTH',			50);	// please use for date selector only
define('ZBX_TEXTAREA_INTERFACE_IP_WIDTH',		225);
define('ZBX_TEXTAREA_INTERFACE_DNS_WIDTH',		175);
define('ZBX_TEXTAREA_INTERFACE_PORT_WIDTH',		100);
define('ZBX_TEXTAREA_STANDARD_ROWS',			7);

// decoration borders
define('ZBX_HOST_INTERFACE_WIDTH',				750);

// overviews help
define('ZBX_OVERVIEW_HELP_MIN_WIDTH',			125);

// dashboard widgets
define('WIDGET_DISCOVERY_STATUS',		'dscvry');
define('WIDGET_FAVOURITE_GRAPHS',		'favgrph');
define('WIDGET_FAVOURITE_MAPS',			'favmap');
define('WIDGET_FAVOURITE_SCREENS',		'favscr');
define('WIDGET_HOST_STATUS',			'hoststat');
define('WIDGET_PROBLEMS',				'problems');
define('WIDGET_SYSTEM_STATUS',			'syssum');
define('WIDGET_WEB_OVERVIEW',			'webovr');
define('WIDGET_ZABBIX_STATUS',			'stszbx');
define('WIDGET_GRAPH',					'graph');
define('WIDGET_CLOCK',					'clock');
define('WIDGET_SYSMAP',					'sysmap');
define('WIDGET_NAVIGATION_TREE',		'navigationtree');
define('WIDGET_PLAIN_TEXT',				'plaintext');
define('WIDGET_URL',					'url');
define('WIDGET_ACTION_LOG',				'actlog');
define('WIDGET_DATA_OVERVIEW',			'dataover');
define('WIDGET_TRIG_OVERVIEW',			'trigover');

// sysmap widget source types
define('WIDGET_SYSMAP_SOURCETYPE_MAP',	1);
define('WIDGET_SYSMAP_SOURCETYPE_FILTER',	2);

// widget select resource field types
define('WIDGET_FIELD_SELECT_RES_SYSMAP',		1);
define('WIDGET_FIELD_SELECT_RES_ITEM',			2);
define('WIDGET_FIELD_SELECT_RES_GRAPH',			3);
define('WIDGET_FIELD_SELECT_RES_SIMPLE_GRAPH',  4);

// max depth of navigation tree
define('WIDGET_NAVIGATION_TREE_MAX_DEPTH', 10);

// event details widgets
define('WIDGET_HAT_TRIGGERDETAILS',		'hat_triggerdetails');
define('WIDGET_HAT_EVENTDETAILS',		'hat_eventdetails');
define('WIDGET_HAT_EVENTACK',			'hat_eventack');
define('WIDGET_HAT_EVENTACTIONMSGS',	'hat_eventactionmsgs');
define('WIDGET_HAT_EVENTACTIONMCMDS',	'hat_eventactionmcmds');
define('WIDGET_HAT_EVENTLIST',			'hat_eventlist');
// search widget
define('WIDGET_SEARCH_HOSTS',			'search_hosts');
define('WIDGET_SEARCH_HOSTGROUP',		'search_hostgroup');
define('WIDGET_SEARCH_TEMPLATES',		'search_templates');
// slideshow
define('WIDGET_SLIDESHOW',				'hat_slides');

// Dashboard widget dynamic state
define('WIDGET_SIMPLE_ITEM',	0);
define('WIDGET_DYNAMIC_ITEM',	1);

// widget field types
define('ZBX_WIDGET_FIELD_TYPE_INT32',			0);
define('ZBX_WIDGET_FIELD_TYPE_STR',				1);
define('ZBX_WIDGET_FIELD_TYPE_GROUP',			2);
define('ZBX_WIDGET_FIELD_TYPE_HOST',			3);
define('ZBX_WIDGET_FIELD_TYPE_ITEM',			4);
define('ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE',	5);
define('ZBX_WIDGET_FIELD_TYPE_GRAPH',			6);
define('ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE',	7);
define('ZBX_WIDGET_FIELD_TYPE_MAP',				8);

define('ZBX_WIDGET_FIELD_RESOURCE_GRAPH',				0);
define('ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH',		1);

// validation
define('DB_ID',		"({}>=0&&bccomp({},\"9223372036854775807\")<=0)&&");
define('NOT_EMPTY',	"({}!='')&&");
define('NOT_ZERO',	"({}!=0)&&");

define('ZBX_VALID_OK',		0);
define('ZBX_VALID_ERROR',	1);
define('ZBX_VALID_WARNING',	2);

// user default theme
define('THEME_DEFAULT', 'default');

// the default theme
define('ZBX_DEFAULT_THEME', 'blue-theme');

define('ZBX_DEFAULT_URL', 'zabbix.php?action=dashboard.view');

// non translatable date formats
define('TIMESTAMP_FORMAT', 'YmdHis');
define('TIMESTAMP_FORMAT_ZERO_TIME', 'Ymd0000');

// date format context, usable for translators
define('DATE_FORMAT_CONTEXT', 'Date format (see http://php.net/date)');

// availability report modes
define('AVAILABILITY_REPORT_BY_HOST', 0);
define('AVAILABILITY_REPORT_BY_TEMPLATE', 1);

// monitoring modes
define('ZBX_MONITORED_BY_ANY', 0);
define('ZBX_MONITORED_BY_SERVER', 1);
define('ZBX_MONITORED_BY_PROXY', 2);

// queue modes
define('QUEUE_OVERVIEW', 0);
define('QUEUE_OVERVIEW_BY_PROXY', 1);
define('QUEUE_DETAILS', 2);

// item count to display in the details queue
define('QUEUE_DETAIL_ITEM_COUNT', 500);

// constants for element "copy to..." target types
define('COPY_TYPE_TO_HOST', 0);
define('COPY_TYPE_TO_TEMPLATE', 2);
define('COPY_TYPE_TO_HOST_GROUP', 1);

define('HISTORY_GRAPH', 'showgraph');
define('HISTORY_BATCH_GRAPH', 'batchgraph');
define('HISTORY_VALUES', 'showvalues');
define('HISTORY_LATEST', 'showlatest');

// configuration -> maps default add icon name
define('MAP_DEFAULT_ICON', 'Server_(96)');

// CSS styles
define('ZBX_STYLE_ACTION_BUTTONS', 'action-buttons');
define('ZBX_STYLE_ADM_IMG', 'adm-img');
define('ZBX_STYLE_AVERAGE_BG', 'average-bg');
define('ZBX_STYLE_ARROW_DOWN', 'arrow-down');
define('ZBX_STYLE_ARROW_LEFT', 'arrow-left');
define('ZBX_STYLE_ARROW_RIGHT', 'arrow-right');
define('ZBX_STYLE_ARROW_UP', 'arrow-up');
define('ZBX_STYLE_BLUE', 'blue');
define('ZBX_STYLE_BTN_ADD_FAV', 'btn-add-fav');
define('ZBX_STYLE_BTN_ALT', 'btn-alt');
define('ZBX_STYLE_BTN_BACK_MAP', 'btn-back-map');
define('ZBX_STYLE_BTN_BACK_MAP_CONTAINER', 'btn-back-map-container');
define('ZBX_STYLE_BTN_BACK_MAP_CONTENT', 'btn-back-map-content');
define('ZBX_STYLE_BTN_BACK_MAP_ICON', 'btn-back-map-icon');
define('ZBX_STYLE_BTN_CONF', 'btn-conf');
define('ZBX_STYLE_BTN_ACTION', 'btn-action');
define('ZBX_STYLE_BTN_DASHBRD_CONF', 'btn-dashbrd-conf');
define('ZBX_STYLE_BTN_DASHBRD_NORMAL', 'btn-dashbrd-normal');
define('ZBX_STYLE_BTN_DEBUG', 'btn-debug');
define('ZBX_STYLE_BTN_GREY', 'btn-grey');
define('ZBX_STYLE_BTN_INFO', 'btn-info');
define('ZBX_STYLE_BTN_LINK', 'btn-link');
define('ZBX_STYLE_BTN_KIOSK', 'btn-kiosk');
define('ZBX_STYLE_BTN_MAX', 'btn-max');
define('ZBX_STYLE_BTN_MIN', 'btn-min');
define('ZBX_STYLE_BTN_REMOVE_FAV', 'btn-remove-fav');
define('ZBX_STYLE_BTN_RESET', 'btn-reset');
define('ZBX_STYLE_BTN_SEARCH', 'btn-search');
define('ZBX_STYLE_BTN_WIDGET_ACTION', 'btn-widget-action');
define('ZBX_STYLE_BTN_WIDGET_COLLAPSE', 'btn-widget-collapse');
define('ZBX_STYLE_BTN_WIDGET_EXPAND', 'btn-widget-expand');
define('ZBX_STYLE_BOTTOM', 'bottom');
define('ZBX_STYLE_BROWSER_LOGO_CHROME', 'browser-logo-chrome');
define('ZBX_STYLE_BROWSER_LOGO_FF', 'browser-logo-ff');
define('ZBX_STYLE_BROWSER_LOGO_IE', 'browser-logo-ie');
define('ZBX_STYLE_BROWSER_LOGO_OPERA', 'browser-logo-opera');
define('ZBX_STYLE_BROWSER_LOGO_SAFARI', 'browser-logo-safari');
define('ZBX_STYLE_BROWSER_WARNING_CONTAINER', 'browser-warning-container');
define('ZBX_STYLE_BROWSER_WARNING_FOOTER', 'browser-warning-footer');
define('ZBX_STYLE_CB_SECOND_COLUMN_LABEL', 'cb-second-column-label');
define('ZBX_STYLE_CELL', 'cell');
define('ZBX_STYLE_CELL_WIDTH', 'cell-width');
define('ZBX_STYLE_CENTER', 'center');
define('ZBX_STYLE_CHECKBOX_RADIO', 'checkbox-radio');
define('ZBX_STYLE_CLOCK', 'clock');
define('ZBX_STYLE_SYSMAP', 'sysmap');
define('ZBX_STYLE_NAVIGATIONTREE', 'navtree');
define('ZBX_STYLE_CLOCK_SVG', 'clock-svg');
define('ZBX_STYLE_CLOCK_FACE', 'clock-face');
define('ZBX_STYLE_CLOCK_HAND', 'clock-hand');
define('ZBX_STYLE_CLOCK_HAND_SEC', 'clock-hand-sec');
define('ZBX_STYLE_CLOCK_LINES', 'clock-lines');
define('ZBX_STYLE_COLOR_PICKER', 'color-picker');
define('ZBX_STYLE_COMPACT_VIEW', 'compact-view');
define('ZBX_STYLE_CURSOR_MOVE', 'cursor-move');
define('ZBX_STYLE_CURSOR_POINTER', 'cursor-pointer');
define('ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER', 'dashbrd-grid-widget-container');
define('ZBX_STYLE_DASHBRD_WIDGET_HEAD', 'dashbrd-widget-head');
define('ZBX_STYLE_DASHBRD_WIDGET_FOOT', 'dashbrd-widget-foot');
define('ZBX_STYLE_DASHBRD_EDIT', 'dashbrd-edit');
define('ZBX_STYLE_DASHBRD_WIDGET_GRAPH_LINK', 'dashbrd-widget-graph-link');
define('ZBX_STYLE_DASHED_BORDER', 'dashed-border');
define('ZBX_STYLE_DEBUG_OUTPUT', 'debug-output');
define('ZBX_STYLE_DISABLED', 'disabled');
define('ZBX_STYLE_DISASTER_BG', 'disaster-bg');
define('ZBX_STYLE_DRAG_ICON', 'drag-icon');
define('ZBX_STYLE_PROBLEM_UNACK_FG', 'problem-unack-fg');
define('ZBX_STYLE_PROBLEM_ACK_FG', 'problem-ack-fg');
define('ZBX_STYLE_OK_UNACK_FG', 'ok-unack-fg');
define('ZBX_STYLE_OK_ACK_FG', 'ok-ack-fg');
define('ZBX_STYLE_PLUS_ICON', 'plus-icon');
define('ZBX_STYLE_DRAG_DROP_AREA', 'drag-drop-area');
define('ZBX_STYLE_TABLE_FORMS_SEPARATOR', 'table-forms-separator');
define('ZBX_STYLE_FILTER_ACTIVE', 'filter-active');
define('ZBX_STYLE_FILTER_BTN_CONTAINER', 'filter-btn-container');
define('ZBX_STYLE_FILTER_CB_SECOND_COLUMN', 'filter-cb-second-column');
define('ZBX_STYLE_FILTER_CONTAINER', 'filter-container');
define('ZBX_STYLE_FILTER_HIGHLIGHT_ROW_CB', 'filter-highlight-row-cb');
define('ZBX_STYLE_FILTER_FORMS', 'filter-forms');
define('ZBX_STYLE_FILTER_TRIGGER', 'filter-trigger');
define('ZBX_STYLE_FLOAT_LEFT', 'float-left');
define('ZBX_STYLE_FORM_INPUT_MARGIN', 'form-input-margin');
define('ZBX_STYLE_FORM_NEW_GROUP', 'form-new-group');
define('ZBX_STYLE_GREEN', 'green');
define('ZBX_STYLE_GREEN_BG', 'green-bg');
define('ZBX_STYLE_GREY', 'grey');
define('ZBX_STYLE_TEAL', 'teal');
define('ZBX_STYLE_HEADER_LOGO', 'header-logo');
define('ZBX_STYLE_HEADER_TITLE', 'header-title');
define('ZBX_STYLE_HIDDEN', 'hidden');
define('ZBX_STYLE_HIGH_BG', 'high-bg');
define('ZBX_STYLE_HOR_LIST', 'hor-list');
define('ZBX_STYLE_HOVER_NOBG', 'hover-nobg');
define('ZBX_STYLE_ICON_ACKN', 'icon-ackn');
define('ZBX_STYLE_ICON_CAL', 'icon-cal');
define('ZBX_STYLE_ICON_DEPEND_DOWN', 'icon-depend-down');
define('ZBX_STYLE_ICON_DEPEND_UP', 'icon-depend-up');
define('ZBX_STYLE_ICON_INFO', 'icon-info');
define('ZBX_STYLE_ICON_MAINT', 'icon-maint');
define('ZBX_STYLE_ICON_WZRD_ACTION', 'icon-wzrd-action');
define('ZBX_STYLE_INACTIVE_BG', 'inactive-bg');
define('ZBX_STYLE_INFO_BG', 'info-bg');
define('ZBX_STYLE_INPUT_COLOR_PICKER', 'input-color-picker');
define('ZBX_STYLE_LEFT', 'left');
define('ZBX_STYLE_LINK_ACTION', 'link-action');
define('ZBX_STYLE_LINK_ALT', 'link-alt');
define('ZBX_STYLE_LIST_HOR_CHECK_RADIO', 'list-hor-check-radio');
define('ZBX_STYLE_LIST_CHECK_RADIO', 'list-check-radio');
define('ZBX_STYLE_LIST_TABLE', 'list-table');
define('ZBX_STYLE_LOCAL_CLOCK', 'local-clock');
define('ZBX_STYLE_LOG_NA_BG', 'log-na-bg');
define('ZBX_STYLE_LOG_INFO_BG', 'log-info-bg');
define('ZBX_STYLE_LOG_WARNING_BG', 'log-warning-bg');
define('ZBX_STYLE_LOG_HIGH_BG', 'log-high-bg');
define('ZBX_STYLE_LOG_DISASTER_BG', 'log-disaster-bg');
define('ZBX_STYLE_LOGO', 'logo');
define('ZBX_STYLE_MAP_AREA', 'map-area');
define('ZBX_STYLE_MIDDLE', 'middle');
define('ZBX_STYLE_MSG_GOOD', 'msg-good');
define('ZBX_STYLE_MSG_BAD', 'msg-bad');
define('ZBX_STYLE_MSG_BAD_GLOBAL', 'msg-bad-global');
define('ZBX_STYLE_MSG_DETAILS', 'msg-details');
define('ZBX_STYLE_MSG_DETAILS_BORDER', 'msg-details-border');
define('ZBX_STYLE_NA_BG', 'na-bg');
define('ZBX_STYLE_NAV', 'nav');
define('ZBX_STYLE_NORMAL_BG', 'normal-bg');
define('ZBX_STYLE_NOTIF_BODY', 'notif-body');
define('ZBX_STYLE_NOTIF_INDIC', 'notif-indic');
define('ZBX_STYLE_NOTIF_INDIC_CONTAINER', 'notif-indic-container');
define('ZBX_STYLE_NOTHING_TO_SHOW', 'nothing-to-show');
define('ZBX_STYLE_NOWRAP', 'nowrap');
define('ZBX_STYLE_ORANGE', 'orange');
define('ZBX_STYLE_OVERLAY_CLOSE_BTN', 'overlay-close-btn');
define('ZBX_STYLE_OVERLAY_DESCR', 'overlay-descr');
define('ZBX_STYLE_OVERLAY_DESCR_URL', 'overlay-descr-url');
define('ZBX_STYLE_OVERFLOW_ELLIPSIS', 'overflow-ellipsis');
define('ZBX_STYLE_OBJECT_GROUP', 'object-group');
define('ZBX_STYLE_PAGING_BTN_CONTAINER', 'paging-btn-container');
define('ZBX_STYLE_PAGING_SELECTED', 'paging-selected');
define('ZBX_STYLE_PRELOADER', 'preloader');
define('ZBX_STYLE_PAGE_TITLE', 'page-title-general');
define('ZBX_STYLE_PROGRESS_BAR_BG', 'progress-bar-bg');
define('ZBX_STYLE_PROGRESS_BAR_CONTAINER', 'progress-bar-container');
define('ZBX_STYLE_PROGRESS_BAR_LABEL', 'progress-bar-label');
define('ZBX_STYLE_RADIO_SEGMENTED', 'radio-segmented');
define('ZBX_STYLE_RED', 'red');
define('ZBX_STYLE_RED_BG', 'red-bg');
define('ZBX_STYLE_REL_CONTAINER', 'rel-container');
define('ZBX_STYLE_REMOVE_BTN', 'remove-btn');
define('ZBX_STYLE_RIGHT', 'right');
define('ZBX_STYLE_ROW', 'row');
define('ZBX_STYLE_SCREEN_TABLE', 'screen-table');
define('ZBX_STYLE_SEARCH', 'search');
define('ZBX_STYLE_SELECTED', 'selected');
define('ZBX_STYLE_SELECTED_ITEM_COUNT', 'selected-item-count');
define('ZBX_STYLE_SERVER_NAME', 'server-name');
define('ZBX_STYLE_SETUP_CONTAINER', 'setup-container');
define('ZBX_STYLE_SETUP_FOOTER', 'setup-footer');
define('ZBX_STYLE_SETUP_LEFT', 'setup-left');
define('ZBX_STYLE_SETUP_LEFT_CURRENT', 'setup-left-current');
define('ZBX_STYLE_SETUP_RIGHT', 'setup-right');
define('ZBX_STYLE_SETUP_RIGHT_BODY', 'setup-right-body');
define('ZBX_STYLE_SETUP_TITLE', 'setup-title');
define('ZBX_STYLE_SIGNIN_CONTAINER', 'signin-container');
define('ZBX_STYLE_SIGNIN_LINKS', 'signin-links');
define('ZBX_STYLE_SIGNIN_LOGO', 'signin-logo');
define('ZBX_STYLE_SIGN_IN_TXT', 'sign-in-txt');
define('ZBX_STYLE_STATUS_AVERAGE_BG', 'status-average-bg');
define('ZBX_STYLE_STATUS_CONTAINER', 'status-container');
define('ZBX_STYLE_STATUS_DARK_GREY', 'status-dark-grey');
define('ZBX_STYLE_STATUS_DISABLED_BG', 'status-disabled-bg');
define('ZBX_STYLE_STATUS_DISASTER_BG', 'status-disaster-bg');
define('ZBX_STYLE_STATUS_GREEN', 'status-green');
define('ZBX_STYLE_STATUS_GREY', 'status-grey');
define('ZBX_STYLE_STATUS_HIGH_BG', 'status-high-bg');
define('ZBX_STYLE_STATUS_INFO_BG', 'status-info-bg');
define('ZBX_STYLE_STATUS_NA_BG', 'status-na-bg');
define('ZBX_STYLE_STATUS_RED', 'status-red');
define('ZBX_STYLE_STATUS_WARNING_BG', 'status-warning-bg');
define('ZBX_STYLE_STATUS_YELLOW', 'status-yellow');
define('ZBX_STYLE_SUBFILTER_ENABLED', 'subfilter-enabled');
define('ZBX_STYLE_TABLE', 'table');
define('ZBX_STYLE_TABLE_FORMS', 'table-forms');
define('ZBX_STYLE_TABLE_FORMS_CONTAINER', 'table-forms-container');
define('ZBX_STYLE_TABLE_FORMS_TD_LEFT', 'table-forms-td-left');
define('ZBX_STYLE_TABLE_FORMS_TD_RIGHT', 'table-forms-td-right');
define('ZBX_STYLE_TABLE_PAGING', 'table-paging');
define('ZBX_STYLE_TABLE_STATS', 'table-stats');
define('ZBX_STYLE_TABS_NAV', 'tabs-nav');
define('ZBX_STYLE_TAG', 'tag');
define('ZBX_STYLE_TFOOT_BUTTONS', 'tfoot-buttons');
define('ZBX_STYLE_TD_DRAG_ICON', 'td-drag-icon');
define('ZBX_STYLE_TIME_ZONE', 'time-zone');
define('ZBX_STYLE_TIMELINE_AXIS', 'timeline-axis');
define('ZBX_STYLE_TIMELINE_DATE', 'timeline-date');
define('ZBX_STYLE_TIMELINE_DOT', 'timeline-dot');
define('ZBX_STYLE_TIMELINE_DOT_BIG', 'timeline-dot-big');
define('ZBX_STYLE_TIMELINE_TD', 'timeline-td');
define('ZBX_STYLE_TIMELINE_TH', 'timeline-th');
define('ZBX_STYLE_TOP', 'top');
define('ZBX_STYLE_TOP_NAV', 'top-nav');
define('ZBX_STYLE_TOP_NAV_CONTAINER', 'top-nav-container');
define('ZBX_STYLE_TOP_NAV_HELP', 'top-nav-help');
define('ZBX_STYLE_TOP_NAV_ICONS', 'top-nav-icons');
define('ZBX_STYLE_TOP_NAV_PROFILE', 'top-nav-profile');
define('ZBX_STYLE_TOP_NAV_SIGNOUT', 'top-nav-signout');
define('ZBX_STYLE_TOP_NAV_ZBBSHARE', 'top-nav-zbbshare');
define('ZBX_STYLE_TOP_SUBNAV', 'top-subnav');
define('ZBX_STYLE_TOP_SUBNAV_CONTAINER', 'top-subnav-container');
define('ZBX_STYLE_TREEVIEW', 'treeview');
define('ZBX_STYLE_TREEVIEW_PLUS', 'treeview-plus');
define('ZBX_STYLE_UPPERCASE', 'uppercase');
define('ZBX_STYLE_WARNING_BG', 'warning-bg');
define('ZBX_STYLE_BLINK_HIDDEN', 'blink-hidden');
define('ZBX_STYLE_YELLOW', 'yellow');
define('ZBX_STYLE_FIELD_LABEL_ASTERISK', 'form-label-asterisk');

// server variables
define('HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off');

// configuration -> hosts (macro inheritance)
define('MACRO_TYPE_INHERITED',	0x01);
define('MACRO_TYPE_HOSTMACRO',	0x02);
define('MACRO_TYPE_BOTH',		0x03);	// MACRO_TYPE_INHERITED | MACRO_TYPE_HOSTMACRO

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

// Maximum number of tags to display in events list.
define('EVENTS_LIST_TAGS_COUNT', 3);

// Number of tags to display in Problems widget and Monitoring > Problems.
define('PROBLEMS_SHOW_TAGS_NONE', 0);
define('PROBLEMS_SHOW_TAGS_1', 1);
define('PROBLEMS_SHOW_TAGS_2', 2);
define('PROBLEMS_SHOW_TAGS_3', 3);

// HTTP headers
/*
 * Value of HTTP X-Frame-options header.
 *
 * Supported options:
 *  - SAMEORIGIN (string) - compatible with rfc7034.
 *  - DENY (string) - compatible with rfc7034.
 *  - a list (string) of comma-separated hostnames. If hostname is not between allowed, the SAMEORIGIN option is used.
 *  - null - disable X-Frame-options header.
 */
define('X_FRAME_OPTIONS', 'SAMEORIGIN');
