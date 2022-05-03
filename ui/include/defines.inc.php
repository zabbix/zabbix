<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

define('ZABBIX_VERSION',		'6.0.4');
define('ZABBIX_API_VERSION',	'6.0.4');
define('ZABBIX_EXPORT_VERSION',	'6.0');

define('ZABBIX_DB_VERSION',		6000000);

define('DB_VERSION_SUPPORTED',				0);
define('DB_VERSION_LOWER_THAN_MINIMUM',		1);
define('DB_VERSION_HIGHER_THAN_MAXIMUM',	2);
define('DB_VERSION_FAILED_TO_RETRIEVE',		3);
define('DB_VERSION_NOT_SUPPORTED_ERROR',	4);
define('DB_VERSION_NOT_SUPPORTED_WARNING',	5);

define('ZABBIX_COPYRIGHT_FROM',	'2001');
define('ZABBIX_COPYRIGHT_TO',	'2022');

define('ZBX_BCRYPT_COST',		10);
define('ZBX_MD5_SIZE',			32);

define('ZBX_SESSION_NAME', 'zbx_session'); // Session cookie name for Zabbix front-end.

define('ZBX_KIBIBYTE',	'1024');
define('ZBX_MEBIBYTE',	'1048576');
define('ZBX_GIBIBYTE',	'1073741824');
define('ZBX_TEBIBYTE',	'1099511627776');

define('ZBX_MIN_PERIOD',		60); // 1 minute

define('ZBX_MIN_INT32',			-2147483648);
define('ZBX_MAX_INT32',			2147483647);
define('ZBX_MAX_UINT64',		'18446744073709551615');

// Double precision 64-bit float.
define('ZBX_FLOAT_DIG', PHP_FLOAT_DIG);
define('ZBX_FLOAT_MIN', PHP_FLOAT_MIN);
define('ZBX_FLOAT_MAX', PHP_FLOAT_MAX);

define('ZBX_MAX_DATE',		ZBX_MAX_INT32); // 19 Jan 2038 03:14:07 UTC
define('ZBX_MIN_TIMESHIFT',	-788400000); // Min valid timeshift value in seconds (25 years).
define('ZBX_MAX_TIMESHIFT',	788400000); // Max valid timeshift value in seconds (25 years).

define('ZBX_GEOMAP_MAX_ZOOM', 30); // Max zoom level for geomap.

define('ZBX_MAX_GRAPHS_PER_PAGE', 20);

// Date and time format separators must be synced with setSDateFromOuterObj() in class.calendar.js.
define('ZBX_FULL_DATE_TIME',	'Y-m-d H:i:s'); // Time selector full date and time presentation format.
define('ZBX_DATE_TIME',			'Y-m-d H:i'); // Time selector date and time without seconds presentation format.
define('ZBX_DATE',				'Y-m-d'); // Time selector date without minutes and seconds presentation format.

// TTL timeout in seconds used to invalidate data cache of Vault response. Set 0 to disable Vault response caching.
define('ZBX_DATA_CACHE_TTL', 60);

define('ZBX_HISTORY_SOURCE_ELASTIC',	'elastic');
define('ZBX_HISTORY_SOURCE_SQL',		'sql');

define('ELASTICSEARCH_RESPONSE_PLAIN',			0);
define('ELASTICSEARCH_RESPONSE_AGGREGATION',	1);
define('ELASTICSEARCH_RESPONSE_DOCUMENTS',		2);

define('ZBX_FONTPATH',				realpath('assets/fonts')); // where to search for font (GD > 2.0.18)
define('ZBX_GRAPH_FONT_NAME',		'DejaVuSans'); // font file name
define('ZBX_GRAPH_LEGEND_HEIGHT',	120); // when graph height is less then this value, some legend will not show up

define('GRAPH_YAXIS_SIDE_DEFAULT', 0); // 0 - LEFT SIDE, 1 - RIGHT SIDE

define('ZBX_MAX_IMAGE_SIZE', ZBX_MEBIBYTE);

define('ZBX_UNITS_ROUNDOFF_SUFFIXED',		2);
define('ZBX_UNITS_ROUNDOFF_UNSUFFIXED',		4);

define('ZBX_DEFAULT_INTERVAL', '1-7,00:00-24:00');

define('ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT',	0);
define('ZBX_SCRIPT_TYPE_IPMI',			1);
define('ZBX_SCRIPT_TYPE_SSH',			2);
define('ZBX_SCRIPT_TYPE_TELNET',		3);
define('ZBX_SCRIPT_TYPE_WEBHOOK',		5);

define('ZBX_SCRIPT_SCOPE_ACTION', 0x1);
define('ZBX_SCRIPT_SCOPE_HOST', 0x2);
define('ZBX_SCRIPT_SCOPE_EVENT', 0x4);

define('ZBX_SEARCH_TYPE_STRICT',	0);
define('ZBX_SEARCH_TYPE_PATTERN',	1);

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

define('WIDGET_PROBLEMS_BY_SV_SHOW_GROUPS',	0);
define('WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS',	1);

define('TRIGGERS_OPTION_RECENT_PROBLEM',	1);
define('TRIGGERS_OPTION_ALL',				2);
define('TRIGGERS_OPTION_IN_PROBLEM',		3);

define('ZBX_FONT_NAME', 'DejaVuSans');

define('ZBX_AUTH_INTERNAL',	0);
define('ZBX_AUTH_LDAP',		1);
define('ZBX_AUTH_HTTP_DISABLED',	0);
define('ZBX_AUTH_HTTP_ENABLED',		1);
define('ZBX_AUTH_LDAP_DISABLED',	0);
define('ZBX_AUTH_LDAP_ENABLED',		1);
define('ZBX_AUTH_SAML_DISABLED',	0);
define('ZBX_AUTH_SAML_ENABLED',		1);
define('ZBX_AUTH_FORM_ZABBIX',	0);
define('ZBX_AUTH_FORM_HTTP',	1);
define('ZBX_AUTH_CASE_INSENSITIVE',	0);
define('ZBX_AUTH_CASE_SENSITIVE',	1);

// password policy
define('PASSWD_CHECK_CASE', 0x01);
define('PASSWD_CHECK_DIGITS', 0x02);
define('PASSWD_CHECK_SPECIAL', 0x04);
define('PASSWD_CHECK_SIMPLE', 0x08);

define('ZBX_DB_MYSQL',		'MYSQL');
define('ZBX_DB_ORACLE',		'ORACLE');
define('ZBX_DB_POSTGRESQL',	'POSTGRESQL');

define('ZBX_DB_EXTENSION_TIMESCALEDB', 'timescaledb');

define('ZBX_DB_MAX_ID', '9223372036854775807');

// maximum number of records for create() or update() API calls
define('ZBX_DB_MAX_INSERTS', 10000);

// Default db and field character set (MYSQL & POSTGRESQL)
define('ZBX_DB_POSTGRESQL_ALLOWED_CHARSET', 'UTF8');
define('ZBX_DB_MYSQL_ALLOWED_CHARSETS', ['UTF8', 'UTF8MB3', 'UTF8MB4']);
define('ZBX_DB_MYSQL_ALLOWED_COLLATIONS', ['utf8_bin', 'utf8mb3_bin', 'utf8mb4_bin']);

// Default db defines for Oracle DB
define('ORACLE_MAX_STRING_SIZE', 4000);
define('ORACLE_UTF8_CHARSET', 'AL32UTF8');
define('ORACLE_CESU8_CHARSET', 'UTF8');

define('DB_STORE_CREDS_CONFIG', 0);
define('DB_STORE_CREDS_VAULT', 1);

define('PAGE_TYPE_HTML',				0);
define('PAGE_TYPE_IMAGE',				1);
define('PAGE_TYPE_JS',					3); // javascript
define('PAGE_TYPE_CSS',					4);
define('PAGE_TYPE_HTML_BLOCK',			5); // simple block of html (as text)
define('PAGE_TYPE_JSON',				6); // simple JSON
define('PAGE_TYPE_JSON_RPC',			7); // api call
define('PAGE_TYPE_TEXT',				9); // simple text
define('PAGE_TYPE_TEXT_RETURN_JSON',	11); // input plaintext output json

define('ZBX_SESSION_ACTIVE',	0);
define('ZBX_SESSION_PASSIVE',	1);

define('T_ZBX_STR',			0);
define('T_ZBX_INT',			1);
define('T_ZBX_DBL',			2);
define('T_ZBX_RANGE_TIME',	3);
define('T_ZBX_TU',			12);
define('T_ZBX_ABS_TIME',	13);

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

//	misc parameters
define('IMAGE_FORMAT_PNG',	'PNG');
define('IMAGE_FORMAT_JPEG',	'JPEG');
define('IMAGE_FORMAT_TEXT',	'JPEG');
define('IMAGE_FORMAT_GIF',	'GIF');

define('IMAGE_TYPE_ICON',			1);
define('IMAGE_TYPE_BACKGROUND',		2);

define('ITEM_CONVERT_WITH_UNITS',	0); // - do not convert empty units
define('ITEM_CONVERT_NO_UNITS',		1); // - no units

define('ZBX_SORT_UP',	'ASC');
define('ZBX_SORT_DOWN',	'DESC');

// Maximum number of tags to display.
define('ZBX_TAG_COUNT_DEFAULT', 3);

define('ZBX_TCP_HEADER_DATA',		"ZBXD");
define('ZBX_TCP_HEADER_VERSION',	"\1");
define('ZBX_TCP_HEADER',			ZBX_TCP_HEADER_DATA.ZBX_TCP_HEADER_VERSION);
define('ZBX_TCP_HEADER_LEN',		5);
define('ZBX_TCP_DATALEN_LEN',		8);

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
define('CONDITION_TYPE_SUPPRESSED',			16);
define('CONDITION_TYPE_DRULE',				18);
define('CONDITION_TYPE_DCHECK',				19);
define('CONDITION_TYPE_PROXY',				20);
define('CONDITION_TYPE_DOBJECT',			21);
define('CONDITION_TYPE_HOST_NAME',			22);
define('CONDITION_TYPE_EVENT_TYPE',			23);
define('CONDITION_TYPE_HOST_METADATA',		24);
define('CONDITION_TYPE_EVENT_TAG',			25);
define('CONDITION_TYPE_EVENT_TAG_VALUE',	26);
define('CONDITION_TYPE_SERVICE',			27);
define('CONDITION_TYPE_SERVICE_NAME',		28);

define('CONDITION_OPERATOR_EQUAL',		0);
define('CONDITION_OPERATOR_NOT_EQUAL',	1);
define('CONDITION_OPERATOR_LIKE',		2);
define('CONDITION_OPERATOR_NOT_LIKE',	3);
define('CONDITION_OPERATOR_IN',			4);
define('CONDITION_OPERATOR_MORE_EQUAL',	5);
define('CONDITION_OPERATOR_LESS_EQUAL',	6);
define('CONDITION_OPERATOR_NOT_IN',		7);
define('CONDITION_OPERATOR_REGEXP',		8);
define('CONDITION_OPERATOR_NOT_REGEXP',	9);
define('CONDITION_OPERATOR_YES',		10);
define('CONDITION_OPERATOR_NO',			11);
define('CONDITION_OPERATOR_EXISTS',		12);
define('CONDITION_OPERATOR_NOT_EXISTS',	13);

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

define('HOST_DISCOVER',		0);
define('HOST_NO_DISCOVER',	1);

define('HOST_ENCRYPTION_NONE',			1);
define('HOST_ENCRYPTION_PSK',			2);
define('HOST_ENCRYPTION_CERTIFICATE',	4);

define('HOST_COMPRESSION_ON', 1);

define('PSK_MIN_LEN',	32);

define('HOST_MAINTENANCE_STATUS_OFF',	0);
define('HOST_MAINTENANCE_STATUS_ON',	1);

define('INTERFACE_SECONDARY',	0);
define('INTERFACE_PRIMARY',		1);

define('INTERFACE_USE_DNS',	0);
define('INTERFACE_USE_IP',	1);

define('INTERFACE_TYPE_OPT',		-2);
define('INTERFACE_TYPE_ANY',		-1);
define('INTERFACE_TYPE_UNKNOWN',	0);
define('INTERFACE_TYPE_AGENT',		1);
define('INTERFACE_TYPE_SNMP',		2);
define('INTERFACE_TYPE_IPMI',		3);
define('INTERFACE_TYPE_JMX',		4);

define('HOST_PROT_INTERFACES_INHERIT',	0);
define('HOST_PROT_INTERFACES_CUSTOM',	1);

define('SNMP_BULK_DISABLED',	0);
define('SNMP_BULK_ENABLED',		1);

define('MAINTENANCE_STATUS_ACTIVE',		0);
define('MAINTENANCE_STATUS_APPROACH',	1);
define('MAINTENANCE_STATUS_EXPIRED',	2);

// Modules.
define('MODULE_STATUS_DISABLED', 0);
define('MODULE_STATUS_ENABLED',	1);

define('INTERFACE_AVAILABLE_UNKNOWN',	0);
define('INTERFACE_AVAILABLE_TRUE',		1);
define('INTERFACE_AVAILABLE_FALSE',		2);
define('INTERFACE_AVAILABLE_MIXED',		3);

// Logo.
define('LOGO_TYPE_NORMAL',			0);
define('LOGO_TYPE_SIDEBAR',			1);
define('LOGO_TYPE_SIDEBAR_COMPACT',	2);

define('MAINTENANCE_TAG_EVAL_TYPE_AND_OR',	0);
define('MAINTENANCE_TAG_EVAL_TYPE_OR',		2);
define('MAINTENANCE_TAG_OPERATOR_EQUAL',	0);
define('MAINTENANCE_TAG_OPERATOR_LIKE',		2);

define('MAINTENANCE_TYPE_NORMAL',	0);
define('MAINTENANCE_TYPE_NODATA',	1);

define('TIMEPERIOD_TYPE_ONETIME',	0);
define('TIMEPERIOD_TYPE_HOURLY',	1);
define('TIMEPERIOD_TYPE_DAILY',		2);
define('TIMEPERIOD_TYPE_WEEKLY',	3);
define('TIMEPERIOD_TYPE_MONTHLY',	4);
define('TIMEPERIOD_TYPE_YEARLY',	5);

define('MONTH_WEEK_FIRST',	1);
define('MONTH_WEEK_SECOND',	2);
define('MONTH_WEEK_THIRD',	3);
define('MONTH_WEEK_FOURTH',	4);
define('MONTH_WEEK_LAST',	5);

define('MONTH_MAX_DAY',	31);

// report periods
define('REPORT_PERIOD_TODAY',			0);
define('REPORT_PERIOD_YESTERDAY',		1);
define('REPORT_PERIOD_CURRENT_WEEK',	2);
define('REPORT_PERIOD_CURRENT_MONTH',	3);
define('REPORT_PERIOD_CURRENT_YEAR',	4);
define('REPORT_PERIOD_LAST_WEEK',		5);
define('REPORT_PERIOD_LAST_MONTH',		6);
define('REPORT_PERIOD_LAST_YEAR',		7);

// scheduled reports
define('ZBX_REPORT_FILTER_SHOW_ALL',	0);
define('ZBX_REPORT_FILTER_SHOW_MY',		1);

define('ZBX_REPORT_STATUS_ENABLED',		0);
define('ZBX_REPORT_STATUS_DISABLED',	1);
define('ZBX_REPORT_STATUS_EXPIRED',		2);

define('ZBX_REPORT_PERIOD_DAY',		0);
define('ZBX_REPORT_PERIOD_WEEK',	1);
define('ZBX_REPORT_PERIOD_MONTH',	2);
define('ZBX_REPORT_PERIOD_YEAR',	3);

define('ZBX_REPORT_CYCLE_DAILY',	0);
define('ZBX_REPORT_CYCLE_WEEKLY',	1);
define('ZBX_REPORT_CYCLE_MONTHLY',	2);
define('ZBX_REPORT_CYCLE_YEARLY',	3);

define('ZBX_REPORT_STATE_UNKNOWN',		0);
define('ZBX_REPORT_STATE_SENT',			1);
define('ZBX_REPORT_STATE_ERROR',		2);
define('ZBX_REPORT_STATE_SUCCESS_INFO',	3);

define('ZBX_REPORT_RECIPIENT_TYPE_USER',		0);
define('ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP',	1);

define('ZBX_REPORT_CREATOR_TYPE_USER',		0);
define('ZBX_REPORT_CREATOR_TYPE_RECIPIENT',	1);

define('ZBX_REPORT_EXCLUDE_USER_FALSE',	0);
define('ZBX_REPORT_EXCLUDE_USER_TRUE',	1);

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

define('ZBX_ITEM_DELAY_DEFAULT',			'1m');
define('ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT',	'50s');
define('ZBX_ITEM_SCHEDULING_DEFAULT',		'wd1-5h9-18');

define('ITEM_TYPE_ZABBIX',			0);
define('ITEM_TYPE_SNMPV1',			1); // Deprecated. Now only used in XML converters. Use ITEM_TYPE_SNMP instead.
define('ITEM_TYPE_TRAPPER',			2);
define('ITEM_TYPE_SIMPLE',			3);
define('ITEM_TYPE_SNMPV2C',			4); // Deprecated. Now only used in XML converters. Use ITEM_TYPE_SNMP instead.
define('ITEM_TYPE_INTERNAL',		5);
define('ITEM_TYPE_SNMPV3',			6); // Deprecated. Now only used in XML converters. Use ITEM_TYPE_SNMP instead.
define('ITEM_TYPE_ZABBIX_ACTIVE',	7);
define('ITEM_TYPE_AGGREGATE',		8); // Deprecated. Now only used in XML converters. Use ITEM_TYPE_CALCULATED instead.
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
define('ITEM_TYPE_HTTPAGENT',		19);
define('ITEM_TYPE_SNMP',			20);
define('ITEM_TYPE_SCRIPT',			21);

define('SNMP_V1', 1);
define('SNMP_V2C', 2);
define('SNMP_V3', 3);

define('ZBX_DEPENDENT_ITEM_MAX_LEVELS',	3);
define('ZBX_DEPENDENT_ITEM_MAX_COUNT',	29999);

define('ITEM_VALUE_TYPE_FLOAT',		0);
define('ITEM_VALUE_TYPE_STR',		1); // aka Character
define('ITEM_VALUE_TYPE_LOG',		2);
define('ITEM_VALUE_TYPE_UINT64',	3);
define('ITEM_VALUE_TYPE_TEXT',		4);

define('ITEM_DATA_TYPE_DECIMAL',		0);
define('ITEM_DATA_TYPE_OCTAL',			1);
define('ITEM_DATA_TYPE_HEXADECIMAL',	2);
define('ITEM_DATA_TYPE_BOOLEAN',		3);

define('ZBX_DEFAULT_KEY_DB_MONITOR',			'db.odbc.select[<unique short description>,<dsn>,<connection string>]');
define('ZBX_DEFAULT_KEY_DB_MONITOR_DISCOVERY',	'db.odbc.discovery[<unique short description>,<dsn>,<connection string>]');
define('ZBX_DEFAULT_KEY_SSH',					'ssh.run[<unique short description>,<ip>,<port>,<encoding>]');
define('ZBX_DEFAULT_KEY_TELNET',				'telnet.run[<unique short description>,<ip>,<port>,<encoding>]');

define('ZBX_DEFAULT_JMX_ENDPOINT',	'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi');

define('SYSMAP_ELEMENT_USE_ICONMAP_ON',		1);
define('SYSMAP_ELEMENT_USE_ICONMAP_OFF',	0);

define('ZBX_ICON_PREVIEW_HEIGHT',	24);
define('ZBX_ICON_PREVIEW_WIDTH',	24);

define('ITEM_STATUS_ACTIVE',		0);
define('ITEM_STATUS_DISABLED',		1);
define('ITEM_DISCOVER',	0);
define('ITEM_NO_DISCOVER',	1);

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

define('ITEM_SNMPV3_AUTHPROTOCOL_MD5',		0);
define('ITEM_SNMPV3_AUTHPROTOCOL_SHA1',		1);
define('ITEM_SNMPV3_AUTHPROTOCOL_SHA224',	2);
define('ITEM_SNMPV3_AUTHPROTOCOL_SHA256',	3);
define('ITEM_SNMPV3_AUTHPROTOCOL_SHA384',	4);
define('ITEM_SNMPV3_AUTHPROTOCOL_SHA512',	5);

define('ITEM_SNMPV3_PRIVPROTOCOL_DES',		0);
define('ITEM_SNMPV3_PRIVPROTOCOL_AES128',	1);
define('ITEM_SNMPV3_PRIVPROTOCOL_AES192',	2);
define('ITEM_SNMPV3_PRIVPROTOCOL_AES256',	3);
define('ITEM_SNMPV3_PRIVPROTOCOL_AES192C',	4);
define('ITEM_SNMPV3_PRIVPROTOCOL_AES256C',	5);

define('ITEM_LOGTYPE_INFORMATION',		1);
define('ITEM_LOGTYPE_WARNING',			2);
define('ITEM_LOGTYPE_ERROR',			4);
define('ITEM_LOGTYPE_FAILURE_AUDIT',	7);
define('ITEM_LOGTYPE_SUCCESS_AUDIT',	8);
define('ITEM_LOGTYPE_CRITICAL',			9);
define('ITEM_LOGTYPE_VERBOSE',			10);

define('ITEM_DELAY_FLEXIBLE',	0);
define('ITEM_DELAY_SCHEDULING',	1);

// Item pre-processing types.
define('ZBX_PREPROC_MULTIPLIER',				1);
define('ZBX_PREPROC_RTRIM',						2);
define('ZBX_PREPROC_LTRIM',						3);
define('ZBX_PREPROC_TRIM',						4);
define('ZBX_PREPROC_REGSUB',					5);
define('ZBX_PREPROC_BOOL2DEC',					6);
define('ZBX_PREPROC_OCT2DEC',					7);
define('ZBX_PREPROC_HEX2DEC',					8);
define('ZBX_PREPROC_DELTA_VALUE',				9);
define('ZBX_PREPROC_DELTA_SPEED',				10);
define('ZBX_PREPROC_XPATH',						11);
define('ZBX_PREPROC_JSONPATH',					12);
define('ZBX_PREPROC_VALIDATE_RANGE',			13);
define('ZBX_PREPROC_VALIDATE_REGEX',			14);
define('ZBX_PREPROC_VALIDATE_NOT_REGEX',		15);
define('ZBX_PREPROC_ERROR_FIELD_JSON',			16);
define('ZBX_PREPROC_ERROR_FIELD_XML',			17);
define('ZBX_PREPROC_ERROR_FIELD_REGEX',			18);
define('ZBX_PREPROC_THROTTLE_VALUE',			19);
define('ZBX_PREPROC_THROTTLE_TIMED_VALUE',		20);
define('ZBX_PREPROC_SCRIPT',					21);
define('ZBX_PREPROC_PROMETHEUS_PATTERN',		22);
define('ZBX_PREPROC_PROMETHEUS_TO_JSON',		23);
define('ZBX_PREPROC_CSV_TO_JSON',				24);
define('ZBX_PREPROC_STR_REPLACE',				25);
define('ZBX_PREPROC_VALIDATE_NOT_SUPPORTED',	26);
define('ZBX_PREPROC_XML_TO_JSON',				27);

// Item pre-processing error handlers.
define('ZBX_PREPROC_FAIL_DEFAULT',			0);
define('ZBX_PREPROC_FAIL_DISCARD_VALUE',	1);
define('ZBX_PREPROC_FAIL_SET_VALUE',		2);
define('ZBX_PREPROC_FAIL_SET_ERROR',		3);

define('ZBX_PREPROC_CSV_NO_HEADER',	0);
define('ZBX_PREPROC_CSV_HEADER',	1);

define('ZBX_PREPROC_PROMETHEUS_VALUE', 'value');
define('ZBX_PREPROC_PROMETHEUS_LABEL', 'label');
define('ZBX_PREPROC_PROMETHEUS_FUNCTION', 'function');

define('ZBX_PREPROC_PROMETHEUS_SUM',   'sum');
define('ZBX_PREPROC_PROMETHEUS_MIN',   'min');
define('ZBX_PREPROC_PROMETHEUS_MAX',   'max');
define('ZBX_PREPROC_PROMETHEUS_AVG',   'avg');
define('ZBX_PREPROC_PROMETHEUS_COUNT', 'count');

// LLD rule overrides.
define('ZBX_LLD_OVERRIDE_STOP_NO',	0);
define('ZBX_LLD_OVERRIDE_STOP_YES',	1);
define('ZBX_PROTOTYPE_STATUS_ENABLED', 0);
define('ZBX_PROTOTYPE_STATUS_DISABLED', 1);
define('ZBX_PROTOTYPE_DISCOVER', 0);
define('ZBX_PROTOTYPE_NO_DISCOVER', 1);
define('OPERATION_OBJECT_ITEM_PROTOTYPE', 0);
define('OPERATION_OBJECT_TRIGGER_PROTOTYPE', 1);
define('OPERATION_OBJECT_GRAPH_PROTOTYPE', 2);
define('OPERATION_OBJECT_HOST_PROTOTYPE', 3);

define('GRAPH_DISCOVER',	0);
define('GRAPH_NO_DISCOVER',	1);

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

define('ZBX_SLA_MAX_REPORTING_PERIODS',		100);
define('ZBX_SLA_DEFAULT_REPORTING_PERIODS',	20);

define('ZBX_SLA_STATUS_DISABLED',	0);
define('ZBX_SLA_STATUS_ENABLED',	1);

define('ZBX_SLA_PERIOD_DAILY',		0);
define('ZBX_SLA_PERIOD_WEEKLY',		1);
define('ZBX_SLA_PERIOD_MONTHLY',	2);
define('ZBX_SLA_PERIOD_QUARTERLY',	3);
define('ZBX_SLA_PERIOD_ANNUALLY',	4);

define('ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL',	0);
define('ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE',		2);

define('ZBX_SERVICE_STATUS_CALC_SET_OK',			0);
define('ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL',	1);
define('ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE',	2);

define('SERVICE_STATUS_ANY', -1);
define('SERVICE_STATUS_OK', 0);
define('SERVICE_STATUS_PROBLEM', 1);

define('ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL',	0);
define('ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE',		2);

define('ZBX_SERVICE_FILTER_TAGS_ANY',		0);
define('ZBX_SERVICE_FILTER_TAGS_SERVICE',	1);
define('ZBX_SERVICE_FILTER_TAGS_PROBLEM',	2);

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
define('TRIGGER_DISCOVER',		0);
define('TRIGGER_NO_DISCOVER',	1);

define('TRIGGER_VALUE_FALSE',	0);
define('TRIGGER_VALUE_TRUE',	1);

define('TRIGGER_STATE_NORMAL',	0);
define('TRIGGER_STATE_UNKNOWN',	1);

define('ZBX_SEVERITY_OK',					-1);
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
define('ZBX_MEDIA_TYPE_TAGS_DISABLED',	0);
define('ZBX_MEDIA_TYPE_TAGS_ENABLED',	1);
define('ZBX_EVENT_MENU_HIDE',	0);
define('ZBX_EVENT_MENU_SHOW',	1);

define('MEDIA_TYPE_EMAIL',		0);
define('MEDIA_TYPE_EXEC',		1);
define('MEDIA_TYPE_SMS',		2);
define('MEDIA_TYPE_WEBHOOK',	4);

define('SMTP_CONNECTION_SECURITY_NONE',		0);
define('SMTP_CONNECTION_SECURITY_STARTTLS',	1);
define('SMTP_CONNECTION_SECURITY_SSL_TLS',	2);

define('SMTP_AUTHENTICATION_NONE',		0);
define('SMTP_AUTHENTICATION_NORMAL',	1);

define('SMTP_MESSAGE_FORMAT_PLAIN_TEXT',	0);
define('SMTP_MESSAGE_FORMAT_HTML',			1);

define('ACTION_STATUS_ENABLED',		0);
define('ACTION_STATUS_DISABLED',	1);

define('ACTION_PAUSE_SUPPRESSED_FALSE',		0);
define('ACTION_PAUSE_SUPPRESSED_TRUE',		1);

define('ACTION_NOTIFY_IF_CANCELED_FALSE',	0);
define('ACTION_NOTIFY_IF_CANCELED_TRUE',	1);

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
define('OPERATION_TYPE_UPDATE_MESSAGE',		12);

define('ACTION_OPERATION',			0);
define('ACTION_RECOVERY_OPERATION',	1);
define('ACTION_UPDATE_OPERATION',	2);

define('CONDITION_EVAL_TYPE_AND_OR',		0);
define('CONDITION_EVAL_TYPE_AND',			1);
define('CONDITION_EVAL_TYPE_OR',			2);
define('CONDITION_EVAL_TYPE_EXPRESSION', 	3);

// screen
define('SCREEN_RESOURCE_GRAPH',				0);
define('SCREEN_RESOURCE_SIMPLE_GRAPH',		1);
define('SCREEN_RESOURCE_MAP',				2);
define('SCREEN_RESOURCE_HISTORY',			17);
define('SCREEN_RESOURCE_HTTPTEST_DETAILS',	21);
define('SCREEN_RESOURCE_DISCOVERY',			22);
define('SCREEN_RESOURCE_HTTPTEST',			23);
define('SCREEN_RESOURCE_PROBLEM',			24);

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

define('SCREEN_REFRESH_RESPONSIVENESS',	10);

// default, minimum and maximum number of lines for dashboard widgets
define('ZBX_DEFAULT_WIDGET_LINES', 25);
define('ZBX_MIN_WIDGET_LINES', 1);
define('ZBX_MAX_WIDGET_LINES', 100);

// dashboards
define('DASHBOARD_MAX_PAGES',		50);
define('DASHBOARD_MAX_COLUMNS',		24);
define('DASHBOARD_MAX_ROWS',		64);
define('DASHBOARD_WIDGET_MIN_ROWS',	2);
define('DASHBOARD_WIDGET_MAX_ROWS',	32);
define('DASHBOARD_FILTER_SHOW_ALL',	0);
define('DASHBOARD_FILTER_SHOW_MY',	1);
define('DASHBOARD_DISPLAY_PERIODS',	[10, 30, 60, 120, 600, 1800, 3600]);

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

// view style [Overview, Plaintext]
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

define('ZBX_SERVICE_STATUS_RULE_TYPE_N_GE',		0);
define('ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE',	1);
define('ZBX_SERVICE_STATUS_RULE_TYPE_N_L',		2);
define('ZBX_SERVICE_STATUS_RULE_TYPE_NP_L',		3);
define('ZBX_SERVICE_STATUS_RULE_TYPE_W_GE',		4);
define('ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE',	5);
define('ZBX_SERVICE_STATUS_RULE_TYPE_W_L',		6);
define('ZBX_SERVICE_STATUS_RULE_TYPE_WP_L',		7);

define('ZBX_SERVICE_STATUS_PROPAGATION_AS_IS',		0);
define('ZBX_SERVICE_STATUS_PROPAGATION_INCREASE',	1);
define('ZBX_SERVICE_STATUS_PROPAGATION_DECREASE',	2);
define('ZBX_SERVICE_STATUS_PROPAGATION_IGNORE',		3);
define('ZBX_SERVICE_STATUS_PROPAGATION_FIXED',		4);

define('SERVICE_TIME_TYPE_UPTIME',				0);
define('SERVICE_TIME_TYPE_DOWNTIME',			1);
define('SERVICE_TIME_TYPE_ONETIME_DOWNTIME',	2);

define('ZBX_DISCOVERY_UNSPEC',	0);
define('ZBX_DISCOVERY_DNS',		1);
define('ZBX_DISCOVERY_IP',		2);
define('ZBX_DISCOVERY_VALUE',	3);

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
define('GROUP_GUI_ACCESS_LDAP', 	2);
define('GROUP_GUI_ACCESS_DISABLED', 3);

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

define('HTTPTEST_AUTH_NONE',		0);
define('HTTPTEST_AUTH_BASIC',		1);
define('HTTPTEST_AUTH_NTLM',		2);
define('HTTPTEST_AUTH_KERBEROS',	3);
define('HTTPTEST_AUTH_DIGEST',		4);

define('HTTPTEST_STATUS_ACTIVE',	0);
define('HTTPTEST_STATUS_DISABLED',	1);

define('ZBX_HTTPFIELD_HEADER',		0);
define('ZBX_HTTPFIELD_VARIABLE',	1);
define('ZBX_HTTPFIELD_POST_FIELD',	2);
define('ZBX_HTTPFIELD_QUERY_FIELD',	3);

define('ZBX_POSTTYPE_RAW',	0);
define('ZBX_POSTTYPE_FORM',	1);
define('ZBX_POSTTYPE_JSON',	2);
define('ZBX_POSTTYPE_XML',	3);

define('HTTPCHECK_STORE_RAW',	0);
define('HTTPCHECK_STORE_JSON',	1);

define('HTTPCHECK_ALLOW_TRAPS_OFF',	0);
define('HTTPCHECK_ALLOW_TRAPS_ON',	1);

define('HTTPCHECK_REQUEST_GET',		0);
define('HTTPCHECK_REQUEST_POST',	1);
define('HTTPCHECK_REQUEST_PUT',		2);
define('HTTPCHECK_REQUEST_HEAD',	3);

define('HTTPSTEP_ITEM_TYPE_RSPCODE',	0);
define('HTTPSTEP_ITEM_TYPE_TIME',		1);
define('HTTPSTEP_ITEM_TYPE_IN',			2);
define('HTTPSTEP_ITEM_TYPE_LASTSTEP',	3);
define('HTTPSTEP_ITEM_TYPE_LASTERROR',	4);

define('HTTPTEST_STEP_RETRIEVE_MODE_CONTENT',	0);
define('HTTPTEST_STEP_RETRIEVE_MODE_HEADERS',	1);
define('HTTPTEST_STEP_RETRIEVE_MODE_BOTH',		2);

define('HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF',	0);
define('HTTPTEST_STEP_FOLLOW_REDIRECTS_ON',		1);

define('HTTPTEST_VERIFY_PEER_OFF',	0);
define('HTTPTEST_VERIFY_PEER_ON',	1);

define('HTTPTEST_VERIFY_HOST_OFF',	0);
define('HTTPTEST_VERIFY_HOST_ON',	1);

define('EVENT_NOT_ACKNOWLEDGED',	'0');
define('EVENT_ACKNOWLEDGED',		'1');

define('ZBX_ACKNOWLEDGE_SELECTED',	0);
define('ZBX_ACKNOWLEDGE_PROBLEM',	1);

define('ZBX_PROBLEM_SUPPRESSED_FALSE',	0);
define('ZBX_PROBLEM_SUPPRESSED_TRUE',	1);

define('ZBX_PROBLEM_UPDATE_NONE',			0x00);
define('ZBX_PROBLEM_UPDATE_CLOSE',			0x01);
define('ZBX_PROBLEM_UPDATE_ACKNOWLEDGE',	0x02);
define('ZBX_PROBLEM_UPDATE_MESSAGE',		0x04);
define('ZBX_PROBLEM_UPDATE_SEVERITY',		0x08);
define('ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE',	0x10);

define('ZBX_EVENT_HISTORY_PROBLEM_EVENT',		0);
define('ZBX_EVENT_HISTORY_RECOVERY_EVENT',		1);
define('ZBX_EVENT_HISTORY_MANUAL_UPDATE',		2);
define('ZBX_EVENT_HISTORY_ALERT',				3);

define('ZBX_TM_TASK_CLOSE_PROBLEM', 1);
define('ZBX_TM_TASK_ACKNOWLEDGE',	4);
define('ZBX_TM_TASK_CHECK_NOW',		6);
define('ZBX_TM_TASK_DATA',			7);

define('ZBX_TM_STATUS_NEW',			1);
define('ZBX_TM_STATUS_INPROGRESS',	2);

define('ZBX_TM_DATA_TYPE_DIAGINFO',		1);
define('ZBX_TM_DATA_TYPE_CHECK_NOW',	6);

define('EVENT_SOURCE_TRIGGERS',			0);
define('EVENT_SOURCE_DISCOVERY',		1);
define('EVENT_SOURCE_AUTOREGISTRATION',	2);
define('EVENT_SOURCE_INTERNAL',			3);
define('EVENT_SOURCE_SERVICE',			4);

define('EVENT_OBJECT_TRIGGER',			0);
define('EVENT_OBJECT_DHOST',			1);
define('EVENT_OBJECT_DSERVICE',			2);
define('EVENT_OBJECT_AUTOREGHOST',		3);
define('EVENT_OBJECT_ITEM',				4);
define('EVENT_OBJECT_LLDRULE',			5);
define('EVENT_OBJECT_SERVICE',			6);

// System information widget constants.
define('ZBX_SYSTEM_INFO_SERVER_STATS',	0);
define('ZBX_SYSTEM_INFO_HAC_STATUS',	1);

// Problem and event tag constants.
define('TAG_EVAL_TYPE_AND_OR',		0);
define('TAG_EVAL_TYPE_OR',			2);

define('TAG_OPERATOR_LIKE',			0);
define('TAG_OPERATOR_EQUAL',		1);
define('TAG_OPERATOR_NOT_LIKE',		2);
define('TAG_OPERATOR_NOT_EQUAL',	3);
define('TAG_OPERATOR_EXISTS',		4);
define('TAG_OPERATOR_NOT_EXISTS',	5);

define('GRAPH_FILTER_ALL',		0);
define('GRAPH_FILTER_HOST',		1);
define('GRAPH_FILTER_SIMPLE',	2);

define('GRAPH_AGGREGATE_DEFAULT_INTERVAL',	'1h');

define('AGGREGATE_NONE',	0);
define('AGGREGATE_MIN',		1);
define('AGGREGATE_MAX',		2);
define('AGGREGATE_AVG',		3);
define('AGGREGATE_COUNT',	4);
define('AGGREGATE_SUM',		5);
define('AGGREGATE_FIRST',	6);
define('AGGREGATE_LAST',	7);

define('GRAPH_AGGREGATE_BY_ITEM',		0);
define('GRAPH_AGGREGATE_BY_DATASET',	1);

define('GRAPH_YAXIS_TYPE_CALCULATED',	0);
define('GRAPH_YAXIS_TYPE_FIXED',		1);
define('GRAPH_YAXIS_TYPE_ITEM_VALUE',	2);

define('GRAPH_YAXIS_SIDE_LEFT',		0);
define('GRAPH_YAXIS_SIDE_RIGHT',	1);
define('GRAPH_YAXIS_SIDE_BOTTOM',	2);

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

define('SVG_GRAPH_TYPE_LINE',		0);
define('SVG_GRAPH_TYPE_POINTS',		1);
define('SVG_GRAPH_TYPE_STAIRCASE',	2);
define('SVG_GRAPH_TYPE_BAR',		3);

define('SVG_GRAPH_MISSING_DATA_NONE',			 0);
define('SVG_GRAPH_MISSING_DATA_CONNECTED',		 1);
define('SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO',	 2);

define('SVG_GRAPH_DATA_SOURCE_AUTO',	0);
define('SVG_GRAPH_DATA_SOURCE_HISTORY',	1);
define('SVG_GRAPH_DATA_SOURCE_TRENDS',	2);

define('SVG_GRAPH_CUSTOM_TIME',	1);

define('SVG_GRAPH_LEGEND_TYPE_NONE', 0);
define('SVG_GRAPH_LEGEND_TYPE_SHORT', 1);

define('SVG_GRAPH_LEGEND_LINES_MIN', 1);
define('SVG_GRAPH_LEGEND_LINES_MAX', 5);

define('SVG_GRAPH_PROBLEMS_SHOW', 1);

define('SVG_GRAPH_SELECTED_ITEM_PROBLEMS', 1);

define('SVG_GRAPH_AXIS_SHOW', 1);

define('SVG_GRAPH_AXIS_UNITS_AUTO', 0);
define('SVG_GRAPH_AXIS_UNITS_STATIC', 1);

define('SVG_GRAPH_MAX_NUMBER_OF_METRICS', 50);

define('SVG_GRAPH_DEFAULT_WIDTH',         1);
define('SVG_GRAPH_DEFAULT_POINTSIZE',     3);
define('SVG_GRAPH_DEFAULT_TRANSPARENCY',  5);
define('SVG_GRAPH_DEFAULT_FILL',          3);

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

define('INVENTORY_URL_MACRO_NONE', -1);
define('INVENTORY_URL_MACRO_HOST', 0);
define('INVENTORY_URL_MACRO_TRIGGER', 1);

define('EXPRESSION_HOST_UNKNOWN',			'#ERROR_HOST#');
define('EXPRESSION_HOST_ITEM_UNKNOWN',		'#ERROR_ITEM#');
define('EXPRESSION_NOT_A_MACRO_ERROR',		'#ERROR_MACRO#');
define('EXPRESSION_FUNCTION_UNKNOWN',		'#ERROR_FUNCTION#');
define('EXPRESSION_UNSUPPORTED_VALUE_TYPE',	'#ERROR_VALUE_TYPE#');

define('ZBX_FUNCTION_TYPE_AGGREGATE',	0);
define('ZBX_FUNCTION_TYPE_BITWISE',		1);
define('ZBX_FUNCTION_TYPE_DATE_TIME',	2);
define('ZBX_FUNCTION_TYPE_HISTORY',		3);
define('ZBX_FUNCTION_TYPE_MATH',		4);
define('ZBX_FUNCTION_TYPE_OPERATOR',	5);
define('ZBX_FUNCTION_TYPE_PREDICTION',	6);
define('ZBX_FUNCTION_TYPE_STRING',		7);

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

// End of line sequence.
define('ZBX_EOL_LF',	0);
define('ZBX_EOL_CRLF',	1);

// Time intervals.
define('SEC_PER_MIN',			60);
define('SEC_PER_HOUR',			3600);
define('SEC_PER_DAY',			86400);
define('SEC_PER_WEEK',			604800);
define('SEC_PER_MONTH',			2592000);
define('SEC_PER_YEAR',			31536000);

// Time suffixes and multipliers.
define('ZBX_TIME_SUFFIXES', 'smhdw');
define('ZBX_TIME_SUFFIXES_WITH_YEAR', 'smhdwMy');
define('ZBX_TIME_SUFFIX_MULTIPLIERS', [
	's' => 1,
	'm' => SEC_PER_MIN,
	'h' => SEC_PER_HOUR,
	'd' => SEC_PER_DAY,
	'w' => SEC_PER_WEEK,
	'M' => SEC_PER_MONTH,
	'y' => SEC_PER_YEAR
]);

// Byte suffixes and multipliers.
define('ZBX_BYTE_SUFFIXES', 'KMGT');
define('ZBX_BYTE_SUFFIX_MULTIPLIERS', [
	'K' => ZBX_KIBIBYTE,
	'M' => ZBX_MEBIBYTE,
	'G' => ZBX_GIBIBYTE,
	'T' => ZBX_TEBIBYTE
]);

// Geographic coordinate system edges.
define('GEOMAP_LAT_MIN', -90);
define('GEOMAP_LAT_MAX', 90);
define('GEOMAP_LNG_MIN', -180);
define('GEOMAP_LNG_MAX', 180);

// Regular expressions.
define('ZBX_PREG_PRINT', '^\x00-\x1F');
define('ZBX_PREG_MACRO_NAME', '([A-Z0-9\._]+)');
define('ZBX_PREG_MACRO_NAME_LLD', '([A-Z0-9\._]+)');
define('ZBX_PREG_INTERNAL_NAMES', '([0-9a-zA-Z_\. \-]+)'); // !!! Don't forget sync code with C !!!
define('ZBX_PREG_NUMBER', '(?<number>-?(\d+(\.\d*)?|\.\d+)([Ee][+-]?\d+)?)');
define('ZBX_PREG_INT', '(?<int>-?\d+)');
define('ZBX_PREG_DEF_FONT_STRING', '/^[0-9\.:% ]+$/');
define('ZBX_PREG_DNS_FORMAT', '([0-9a-zA-Z_\.\-$]|\{\$?'.ZBX_PREG_MACRO_NAME.'\})*');
define('ZBX_PREG_HOST_FORMAT', ZBX_PREG_INTERNAL_NAMES);
define('ZBX_PREG_MACRO_NAME_FORMAT', '(\{[A-Z\.]+\})');
define('ZBX_PREG_EXPRESSION_LLD_MACROS', '(\{\#'.ZBX_PREG_MACRO_NAME_LLD.'\})');

define('TRIGGER_QUERY_PLACEHOLDER', '$'); // !!! Don't forget sync code with C !!!

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

// Value map mappings type
define('VALUEMAP_MAPPING_TYPE_EQUAL',			0);
define('VALUEMAP_MAPPING_TYPE_GREATER_EQUAL',	1);
define('VALUEMAP_MAPPING_TYPE_LESS_EQUAL',		2);
define('VALUEMAP_MAPPING_TYPE_IN_RANGE',		3);
define('VALUEMAP_MAPPING_TYPE_REGEXP',			4);
define('VALUEMAP_MAPPING_TYPE_DEFAULT',			5);

define('ZBX_SOCKET_BYTES_LIMIT',    ZBX_MEBIBYTE * 16); // socket response size limit

// value is also used in servercheck.js file
define('SERVER_CHECK_INTERVAL', 10);

define('DATE_TIME_FORMAT_SECONDS_XML', 'Y-m-d\TH:i:s\Z');

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
define('API_MULTIPLE',				0);
// scalar data types
define('API_STRING_UTF8',			1);
define('API_INT32',					2);
define('API_ID',					3);
define('API_BOOLEAN',				4);
define('API_FLAG',					5);
define('API_FLOAT',					6);
define('API_UINT64',				7);
// arrays
define('API_OBJECT',				8);
define('API_IDS',					9);
define('API_OBJECTS',				10);
define('API_STRINGS_UTF8',			11);
define('API_INTS32',				12);
define('API_FLOATS',				13);
define('API_UINTS64',				14);
define('API_CUIDS',					44);
define('API_USER_MACROS',			52);
// specific types
define('API_HG_NAME',				15);
define('API_SCRIPT_MENU_PATH',		16);
define('API_USER_MACRO',			17);
define('API_TIME_PERIOD',			18);
define('API_REGEX',					19);
define('API_HTTP_POST',				20);
define('API_VARIABLE_NAME',			21);
define('API_OUTPUT',				22);
define('API_TIME_UNIT',				23);
define('API_URL',					24);
define('API_H_NAME',				25);
define('API_COLOR',					27);
define('API_NUMERIC',				28);
define('API_LLD_MACRO',				29);
define('API_PSK',					30);
define('API_SORTORDER',				31);
define('API_CALC_FORMULA',			32);
define('API_IP',					33);
define('API_DNS',					34);
define('API_PORT',					35);
define('API_TRIGGER_EXPRESSION',	36);
define('API_EVENT_NAME',			37);
define('API_JSONRPC_PARAMS',		38);
define('API_JSONRPC_ID',			39);
define('API_DATE',					40);
define('API_NUMERIC_RANGES',		41);
define('API_UUID',					42);
define('API_VAULT_SECRET',			43);
define('API_CUID',					45);
define('API_IP_RANGES',				46);
define('API_IMAGE',					47);
define('API_EXEC_PARAMS',			48);
define('API_COND_FORMULA',			49);
define('API_COND_FORMULAID',		50);
define('API_UNEXPECTED',			51);
define('API_INT32_RANGES',			53);
define('API_LAT_LNG_ZOOM',			54);
define('API_TIMESTAMP',				55);

// flags
define('API_REQUIRED',					0x00001);
define('API_NOT_EMPTY',					0x00002);
define('API_ALLOW_NULL',				0x00004);
define('API_NORMALIZE',					0x00008);
define('API_DEPRECATED',				0x00010);
define('API_ALLOW_USER_MACRO',			0x00020);
define('API_ALLOW_COUNT',				0x00040);
define('API_ALLOW_LLD_MACRO',			0x00080);
define('API_REQUIRED_LLD_MACRO',		0x00100);
define('API_TIME_UNIT_WITH_YEAR',		0x00200);
define('API_ALLOW_EVENT_TAGS_MACRO',	0x00400);
define('API_PRESERVE_KEYS',				0x00800);
define('API_ALLOW_MACRO',				0x01000);
define('API_ALLOW_GLOBAL_REGEX',		0x02000);
define('API_ALLOW_UNEXPECTED',			0x04000);
define('API_ALLOW_DNS',					0x08000);
define('API_ALLOW_RANGE',				0x10000);

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

// Error types of unexpected API parameter.
define('API_ERR_INHERITED', 0);
define('API_ERR_DISCOVERED', 1);

define('API_OUTPUT_EXTEND',		'extend');
define('API_OUTPUT_COUNT',		'count');

define('ZBX_AUTH_TOKEN_ENABLED', 0);
define('ZBX_AUTH_TOKEN_DISABLED', 1);

define('ZBX_JAN_2038', 2145916800);

define('DAY_IN_YEAR', 365);

define('ZBX_MIN_PORT_NUMBER', 0);
define('ZBX_MAX_PORT_NUMBER', 65535);

define('ZBX_MACRO_TYPE_TEXT', 0); // Display macro value as text.
define('ZBX_MACRO_TYPE_SECRET', 1); // Display masked macro value.
define('ZBX_MACRO_TYPE_VAULT', 2); // Display macro value as text (path to secret in HashiCorp Vault).

define('ZBX_SECRET_MASK', '******'); // Placeholder for secret values.

// Layout
define('ZBX_LAYOUT_NORMAL',		0);
define('ZBX_LAYOUT_KIOSKMODE',	1);
define('ZBX_LAYOUT_MODE', 'layout-mode');

// Sidebar
define('ZBX_SIDEBAR_VIEW_MODE_FULL',	0);
define('ZBX_SIDEBAR_VIEW_MODE_COMPACT',	1);
define('ZBX_SIDEBAR_VIEW_MODE_HIDDEN',	2);

// List
define('ZBX_LIST_MODE_VIEW', 0);
define('ZBX_LIST_MODE_EDIT', 1);

// input fields
define('ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH',		218);
define('ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH',	218);
define('ZBX_TEXTAREA_MACRO_WIDTH',				250);
define('ZBX_TEXTAREA_MACRO_VALUE_WIDTH',		300);
define('ZBX_TEXTAREA_MACRO_INHERITED_WIDTH',	180);
define('ZBX_TEXTAREA_TAG_WIDTH',				250);
define('ZBX_TEXTAREA_TAG_VALUE_WIDTH',			300);
define('ZBX_TEXTAREA_MAPPING_VALUE_WIDTH',		250);
define('ZBX_TEXTAREA_MAPPING_NEWVALUE_WIDTH',	250);
define('ZBX_TEXTAREA_FILTER_SMALL_WIDTH',		150);
define('ZBX_TEXTAREA_FILTER_STANDARD_WIDTH',	300);
define('ZBX_TEXTAREA_TINY_WIDTH',				75);
define('ZBX_TEXTAREA_SMALL_WIDTH',				150);
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

// Helper buttons that allow selected objects to be added, replaced or removed.
define('ZBX_ACTION_ADD',		0);
define('ZBX_ACTION_REPLACE',	1);
define('ZBX_ACTION_REMOVE',		2);
define('ZBX_ACTION_REMOVE_ALL', 3);
define('ZBX_ACTION_RENAME',		4);

// Maximum width for popups in Actions column for problems.
define('ZBX_ACTIONS_POPUP_MAX_WIDTH',			800);

define('ZBX_HINTBOX_CONTENT_LIMIT',				8192);

// dashboard widgets
define('WIDGET_ACTION_LOG',			'actionlog');
define('WIDGET_CLOCK',				'clock');
define('WIDGET_DISCOVERY',			'discovery');
define('WIDGET_FAV_GRAPHS',			'favgraphs');
define('WIDGET_FAV_MAPS',			'favmaps');
define('WIDGET_GEOMAP',				'geomap');
define('WIDGET_GRAPH',				'graph');
define('WIDGET_GRAPH_PROTOTYPE',	'graphprototype');
define('WIDGET_HOST_AVAIL',			'hostavail');
define('WIDGET_MAP',				'map');
define('WIDGET_NAV_TREE',			'navtree');
define('WIDGET_PLAIN_TEXT',			'plaintext');
define('WIDGET_PROBLEM_HOSTS',		'problemhosts');
define('WIDGET_PROBLEMS',			'problems');
define('WIDGET_PROBLEMS_BY_SV',		'problemsbysv');
define('WIDGET_SLA_REPORT',			'slareport');
define('WIDGET_SVG_GRAPH',			'svggraph');
define('WIDGET_SYSTEM_INFO',		'systeminfo');
define('WIDGET_TOP_HOSTS',			'tophosts');
define('WIDGET_TRIG_OVER',			'trigover');
define('WIDGET_URL',				'url');
define('WIDGET_WEB',				'web');
define('WIDGET_ITEM',				'item');
// Deprecated widgets
define('WIDGET_DATA_OVER',			'dataover');

// Item widget object positions.
define('WIDGET_ITEM_POS_LEFT',		0);
define('WIDGET_ITEM_POS_CENTER',	1);
define('WIDGET_ITEM_POS_RIGHT',		2);

define('WIDGET_ITEM_POS_TOP',		0);
define('WIDGET_ITEM_POS_MIDDLE',	1);
define('WIDGET_ITEM_POS_BOTTOM',	2);

define('WIDGET_ITEM_POS_BEFORE',	0);
define('WIDGET_ITEM_POS_ABOVE',		1);
define('WIDGET_ITEM_POS_AFTER',		2);
define('WIDGET_ITEM_POS_BELOW',		3);

// sysmap widget source types
define('WIDGET_SYSMAP_SOURCETYPE_MAP',	1);
define('WIDGET_SYSMAP_SOURCETYPE_FILTER',	2);

// widget select resource field types
define('WIDGET_FIELD_SELECT_RES_SYSMAP',	1);

// max depth of navigation tree
define('WIDGET_NAVIGATION_TREE_MAX_DEPTH', 10);

// event details widgets
define('WIDGET_HAT_TRIGGERDETAILS',		'hat_triggerdetails');
define('WIDGET_HAT_EVENTDETAILS',		'hat_eventdetails');
define('WIDGET_HAT_EVENTACTIONS',		'hat_eventactions');
define('WIDGET_HAT_EVENTLIST',			'hat_eventlist');
// search widget
define('WIDGET_SEARCH_HOSTS',			'search_hosts');
define('WIDGET_SEARCH_HOSTGROUP',		'search_hostgroup');
define('WIDGET_SEARCH_TEMPLATES',		'search_templates');

// dashboard widget dynamic state
define('WIDGET_SIMPLE_ITEM',	0);
define('WIDGET_DYNAMIC_ITEM',	1);

// item widget blocks
define('WIDGET_ITEM_SHOW_DESCRIPTION',		1);
define('WIDGET_ITEM_SHOW_VALUE',			2);
define('WIDGET_ITEM_SHOW_TIME',				3);
define('WIDGET_ITEM_SHOW_CHANGE_INDICATOR',	4);

// widget defaults
define('ZBX_WIDGET_ROWS', 20);

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
define('ZBX_WIDGET_FIELD_TYPE_SERVICE',			9);
define('ZBX_WIDGET_FIELD_TYPE_SLA',				10);

define('ZBX_WIDGET_FIELD_RESOURCE_GRAPH',					0);
define('ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH',			1);
define('ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE',			2);
define('ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE',	3);

// widget view modes
define('ZBX_WIDGET_VIEW_MODE_NORMAL',			0);
define('ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER',	1);

// top hosts widget
define('ZBX_WIDGET_TOP_HOSTS_DEFAULT_FILL',	'#97AAB3');

// validation
define('DB_ID',		"({}>=0&&bccomp({},\"9223372036854775807\")<=0)&&");
define('NOT_EMPTY',	"({}!='')&&");
define('NOT_ZERO',	"({}!=0)&&");

define('ZBX_VALID_OK',		0);
define('ZBX_VALID_ERROR',	1);
define('ZBX_VALID_WARNING',	2);

// user default language
define('LANG_DEFAULT', 'default');

// the default language
define('ZBX_DEFAULT_LANG', 'en_US');

// user default time zone
define('TIMEZONE_DEFAULT', 'default');

// the default time zone
define('ZBX_DEFAULT_TIMEZONE', 'system');

// user default theme
define('THEME_DEFAULT', 'default');

// the default theme
define('ZBX_DEFAULT_THEME', 'blue-theme');

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

// target types to copy items/triggers/graphs
define('COPY_TYPE_TO_HOST_GROUP',	0);
define('COPY_TYPE_TO_HOST',			1);
define('COPY_TYPE_TO_TEMPLATE',		2);

define('HISTORY_GRAPH', 'showgraph');
define('HISTORY_BATCH_GRAPH', 'batchgraph');
define('HISTORY_VALUES', 'showvalues');
define('HISTORY_LATEST', 'showlatest');

// Item history and trends storage modes.
define('ITEM_STORAGE_OFF',		0);
define('ITEM_STORAGE_CUSTOM',	1);

// Item history and trends storage value to define 0 storage period.
define('ITEM_NO_STORAGE_VALUE',	0);

// configuration -> maps default add icon name
define('MAP_DEFAULT_ICON', 'Server_(96)');

// Condition popup types.
define('ZBX_POPUP_CONDITION_TYPE_EVENT_CORR', 0);
define('ZBX_POPUP_CONDITION_TYPE_ACTION', 1);
define('ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION', 2);

// Tab indicator names.
define('TAB_INDICATOR_AUTH_HTTP', 'http');
define('TAB_INDICATOR_AUTH_LDAP', 'ldap');
define('TAB_INDICATOR_AUTH_SAML', 'saml');
define('TAB_INDICATOR_CHILD_SERVICES', 'child-services');
define('TAB_INDICATOR_DEPENDENCY', 'dependency');
define('TAB_INDICATOR_ENCRYPTION', 'encryption');
define('TAB_INDICATOR_EXCLUDED_DOWNTIMES', 'excluded-downtimes');
define('TAB_INDICATOR_FILTERS', 'filters');
define('TAB_INDICATOR_FRONTEND_MESSAGE', 'frontend-message');
define('TAB_INDICATOR_GRAPH_DATASET', 'graph-dataset');
define('TAB_INDICATOR_GRAPH_LEGEND', 'graph-legend');
define('TAB_INDICATOR_GRAPH_OPTIONS', 'graph-options');
define('TAB_INDICATOR_GRAPH_OVERRIDES', 'graph-overrides');
define('TAB_INDICATOR_GRAPH_PROBLEMS', 'graph-problems');
define('TAB_INDICATOR_GRAPH_TIME', 'graph-time');
define('TAB_INDICATOR_HTTP_AUTH', 'http-auth');
define('TAB_INDICATOR_INVENTORY', 'inventory');
define('TAB_INDICATOR_LLD_MACROS', 'lld-macros');
define('TAB_INDICATOR_MACROS', 'macros');
define('TAB_INDICATOR_MEDIA', 'media');
define('TAB_INDICATOR_MESSAGE_TEMPLATE', 'message-template');
define('TAB_INDICATOR_OPERATIONS', 'operations');
define('TAB_INDICATOR_OVERRIDES', 'overrides');
define('TAB_INDICATOR_PERMISSIONS', 'permissions');
define('TAB_INDICATOR_PREPROCESSING', 'preprocessing');
define('TAB_INDICATOR_SHARING', 'sharing');
define('TAB_INDICATOR_STEPS', 'steps');
define('TAB_INDICATOR_TAG_FILTER', 'tag-filter');
define('TAB_INDICATOR_TAGS', 'tags');
define('TAB_INDICATOR_TIME', 'time');
define('TAB_INDICATOR_VALUEMAPS', 'valuemaps');

// CSS styles
define('ZBX_STYLE_ACTION_BUTTONS', 'action-buttons');
define('ZBX_STYLE_ACTION_CONTAINER', 'action-container');
define('ZBX_STYLE_ADM_IMG', 'adm-img');
define('ZBX_STYLE_AVERAGE_BG', 'average-bg');
define('ZBX_STYLE_ARROW_DOWN', 'arrow-down');
define('ZBX_STYLE_ARROW_LEFT', 'arrow-left');
define('ZBX_STYLE_ARROW_RIGHT', 'arrow-right');
define('ZBX_STYLE_ARROW_UP', 'arrow-up');
define('ZBX_STYLE_BLUE', 'blue');
define('ZBX_STYLE_BTN_ADD', 'btn-add');
define('ZBX_STYLE_BTN_ADD_FAV', 'btn-add-fav');
define('ZBX_STYLE_BTN_ALT', 'btn-alt');
define('ZBX_STYLE_BTN_TOGGLE_CHEVRON', 'btn-toggle-chevron');
define('ZBX_STYLE_BTN_SPLIT', 'btn-split');
define('ZBX_STYLE_BTN_TOGGLE', 'btn-dropdown-toggle');
define('ZBX_STYLE_BTN_BACK_MAP', 'btn-back-map');
define('ZBX_STYLE_BTN_BACK_MAP_CONTAINER', 'btn-back-map-container');
define('ZBX_STYLE_BTN_BACK_MAP_CONTENT', 'btn-back-map-content');
define('ZBX_STYLE_BTN_BACK_MAP_ICON', 'btn-back-map-icon');
define('ZBX_STYLE_BTN_ACTION', 'btn-action');
define('ZBX_STYLE_BTN_DASHBOARD_CONF', 'btn-dashboard-conf');
define('ZBX_STYLE_BTN_DASHBOARD_NORMAL', 'btn-dashboard-normal');
define('ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_TOGGLE_SLIDESHOW', 'btn-dashboard-kioskmode-toggle-slideshow');
define('ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_PREVIOUS_PAGE', 'btn-dashboard-kioskmode-previous-page');
define('ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_NEXT_PAGE', 'btn-dashboard-kioskmode-next-page');
define('ZBX_STYLE_BTN_DEBUG', 'btn-debug');
define('ZBX_STYLE_BTN_EDIT', 'btn-edit');
define('ZBX_STYLE_BTN_GREY', 'btn-grey');
define('ZBX_STYLE_BTN_IMPORT', 'btn-import');
define('ZBX_STYLE_BTN_INFO', 'btn-info');
define('ZBX_STYLE_BTN_LINK', 'btn-link');
define('ZBX_STYLE_BTN_KIOSK', 'btn-kiosk');
define('ZBX_STYLE_BTN_MIN', 'btn-min');
define('ZBX_STYLE_BTN_REMOVE', 'btn-remove');
define('ZBX_STYLE_BTN_REMOVE_FAV', 'btn-remove-fav');
define('ZBX_STYLE_BTN_TIME', 'btn-time');
define('ZBX_STYLE_BTN_TIME_LEFT', 'btn-time-left');
define('ZBX_STYLE_BTN_TIME_OUT', 'btn-time-out');
define('ZBX_STYLE_BTN_TIME_RIGHT', 'btn-time-right');
define('ZBX_STYLE_BTN_WIDGET_ACTION', 'btn-widget-action');
define('ZBX_STYLE_BTN_WIDGET_COLLAPSE', 'btn-widget-collapse');
define('ZBX_STYLE_BTN_WIDGET_EDIT', 'btn-widget-edit');
define('ZBX_STYLE_BTN_WIDGET_EXPAND', 'btn-widget-expand');
define('ZBX_STYLE_BOTTOM', 'bottom');
define('ZBX_STYLE_BROWSER_LOGO_CHROME', 'browser-logo-chrome');
define('ZBX_STYLE_BROWSER_LOGO_FF', 'browser-logo-ff');
define('ZBX_STYLE_BROWSER_LOGO_ED', 'browser-logo-ed');
define('ZBX_STYLE_BROWSER_LOGO_OPERA', 'browser-logo-opera');
define('ZBX_STYLE_BROWSER_LOGO_SAFARI', 'browser-logo-safari');
define('ZBX_STYLE_BROWSER_WARNING_CONTAINER', 'browser-warning-container');
define('ZBX_STYLE_BROWSER_WARNING_FOOTER', 'browser-warning-footer');
define('ZBX_STYLE_CELL', 'cell');
define('ZBX_STYLE_CELL_WIDTH', 'cell-width');
define('ZBX_STYLE_CENTER', 'center');
define('ZBX_STYLE_CHECKBOX_RADIO', 'checkbox-radio');
define('ZBX_STYLE_CLOCK', 'clock');
define('ZBX_STYLE_SYSMAP', 'sysmap');
define('ZBX_STYLE_NAVIGATIONTREE', 'navtree');
define('ZBX_STYLE_CHECKBOX_LIST', 'checkbox-list');
define('ZBX_STYLE_CLOCK_SVG', 'clock-svg');
define('ZBX_STYLE_CLOCK_FACE', 'clock-face');
define('ZBX_STYLE_CLOCK_HAND', 'clock-hand');
define('ZBX_STYLE_CLOCK_HAND_SEC', 'clock-hand-sec');
define('ZBX_STYLE_CLOCK_LINES', 'clock-lines');
define('ZBX_STYLE_COLOR_PICKER', 'color-picker');
define('ZBX_STYLE_COLOR_PREVIEW_BOX', 'color-preview-box');
define('ZBX_STYLE_COLUMN_TAGS_1', 'column-tags-1');
define('ZBX_STYLE_COLUMN_TAGS_2', 'column-tags-2');
define('ZBX_STYLE_COLUMN_TAGS_3', 'column-tags-3');
define('ZBX_STYLE_COMPACT_VIEW', 'compact-view');
define('ZBX_STYLE_CURSOR_POINTER', 'cursor-pointer');
define('ZBX_STYLE_DASHBOARD', 'dashboard');
define('ZBX_STYLE_DASHBOARD_IS_MULTIPAGE', 'dashboard-is-multipage');
define('ZBX_STYLE_DASHBOARD_IS_EDIT_MODE', 'dashboard-is-edit-mode');
define('ZBX_STYLE_DASHBOARD_KIOSKMODE_CONTROLS', 'dashboard-kioskmode-controls');
define('ZBX_STYLE_DASHBOARD_GRID', 'dashboard-grid');
define('ZBX_STYLE_DASHBOARD_NAVIGATION', 'dashboard-navigation');
define('ZBX_STYLE_DASHBOARD_NAVIGATION_CONTROLS', 'dashboard-navigation-controls');
define('ZBX_STYLE_DASHBOARD_NAVIGATION_TABS', 'dashboard-navigation-tabs');
define('ZBX_STYLE_DASHBOARD_PREVIOUS_PAGE', 'dashboard-previous-page');
define('ZBX_STYLE_DASHBOARD_NEXT_PAGE', 'dashboard-next-page');
define('ZBX_STYLE_DASHBOARD_TOGGLE_SLIDESHOW', 'dashboard-toggle-slideshow');
define('ZBX_STYLE_DASHBOARD_WIDGET', 'dashboard-widget');
define('ZBX_STYLE_DASHBOARD_WIDGET_FLUID', 'dashboard-widget-fluid');
define('ZBX_STYLE_DASHBOARD_WIDGET_HEAD', 'dashboard-widget-head');
define('ZBX_STYLE_DASHBOARD_WIDGET_FOOT', 'dashboard-widget-foot');
define('ZBX_STYLE_DASHBOARD_EDIT', 'dashboard-edit');
define('ZBX_STYLE_DASHBOARD_WIDGET_GRAPH_LINK', 'dashboard-widget-graph-link');
define('ZBX_STYLE_DASHED_BORDER', 'dashed-border');
define('ZBX_STYLE_DEBUG_OUTPUT', 'debug-output');
define('ZBX_STYLE_DIFF', 'diff');
define('ZBX_STYLE_DIFF_ADDED', 'diff-added');
define('ZBX_STYLE_DIFF_REMOVED', 'diff-removed');
define('ZBX_STYLE_DISABLED', 'disabled');
define('ZBX_STYLE_DISASTER_BG', 'disaster-bg');
define('ZBX_STYLE_DISPLAY_NONE', 'display-none');
define('ZBX_STYLE_DRAG_ICON', 'drag-icon');
define('ZBX_STYLE_PROBLEM_UNACK_FG', 'problem-unack-fg');
define('ZBX_STYLE_PROBLEM_ACK_FG', 'problem-ack-fg');
define('ZBX_STYLE_OK_UNACK_FG', 'ok-unack-fg');
define('ZBX_STYLE_OK_ACK_FG', 'ok-ack-fg');
define('ZBX_STYLE_OVERRIDES_LIST', 'overrides-list');
define('ZBX_STYLE_OVERRIDES_LIST_ITEM', 'overrides-list-item');
define('ZBX_STYLE_OVERRIDES_OPTIONS_LIST', 'overrides-options-list');
define('ZBX_STYLE_PLUS_ICON', 'plus-icon');
define('ZBX_STYLE_DRAG_DROP_AREA', 'drag-drop-area');
define('ZBX_STYLE_TABLE_FORMS_SEPARATOR', 'table-forms-separator');
define('ZBX_STYLE_TABLE_LEFT_BORDER', 'border-left');
define('ZBX_STYLE_TIME_INPUT', 'time-input');
define('ZBX_STYLE_TIME_INPUT_ERROR', 'time-input-error');
define('ZBX_STYLE_TIME_QUICK', 'time-quick');
define('ZBX_STYLE_TIME_QUICK_RANGE', 'time-quick-range');
define('ZBX_STYLE_TIME_SELECTION_CONTAINER', 'time-selection-container');
define('ZBX_STYLE_FILTER_BTN_CONTAINER', 'filter-btn-container');
define('ZBX_STYLE_FILTER_CONTAINER', 'filter-container');
define('ZBX_STYLE_FILTER_HIGHLIGHT_ROW_CB', 'filter-highlight-row-cb');
define('ZBX_STYLE_FILTER_FORMS', 'filter-forms');
define('ZBX_STYLE_FILTER_SPACE', 'filter-space');
define('ZBX_STYLE_FILTER_TRIGGER', 'filter-trigger');
define('ZBX_STYLE_FLH_AVERAGE_BG', 'flh-average-bg');
define('ZBX_STYLE_FLH_DISASTER_BG', 'flh-disaster-bg');
define('ZBX_STYLE_FLH_HIGH_BG', 'flh-high-bg');
define('ZBX_STYLE_FLH_INFO_BG', 'flh-info-bg');
define('ZBX_STYLE_FLH_NA_BG', 'flh-na-bg');
define('ZBX_STYLE_FLH_WARNING_BG', 'flh-warning-bg');
define('ZBX_STYLE_FLOAT_LEFT', 'float-left');
define('ZBX_STYLE_FORM_INPUT_MARGIN', 'form-input-margin');
define('ZBX_STYLE_FORM_FIELDS_INLINE', 'form-fields-inline');
define('ZBX_STYLE_FORM_NEW_GROUP', 'form-new-group');
define('ZBX_STYLE_GRAPH_WRAPPER', 'graph-wrapper');
define('ZBX_STYLE_GREEN', 'green');
define('ZBX_STYLE_GREEN_BG', 'green-bg');
define('ZBX_STYLE_GREY', 'grey');
define('ZBX_STYLE_TEAL', 'teal');
define('ZBX_STYLE_HEADER_TITLE', 'header-title');
define('ZBX_STYLE_HEADER_CONTROLS', 'header-controls');
define('ZBX_STYLE_HEADER_Z_SELECT', 'header-z-select');
define('ZBX_STYLE_HIGH_BG', 'high-bg');
define('ZBX_STYLE_HOR_LIST', 'hor-list');
define('ZBX_STYLE_HOVER_NOBG', 'hover-nobg');
define('ZBX_STYLE_HINTBOX_WRAP', 'hintbox-wrap');
define('ZBX_STYLE_ICON_ACKN', 'icon-ackn');
define('ZBX_STYLE_ICON_CAL', 'icon-cal');
define('ZBX_STYLE_ICON_DEPEND_DOWN', 'icon-depend-down');
define('ZBX_STYLE_ICON_DEPEND_UP', 'icon-depend-up');
define('ZBX_STYLE_ICON_DESCRIPTION', 'icon-description');
define('ZBX_STYLE_ICON_INFO', 'icon-info');
define('ZBX_STYLE_ICON_INVISIBLE', 'icon-invisible');
define('ZBX_STYLE_ICON_USER', 'icon-user');
define('ZBX_STYLE_ICON_USER_GROUP', 'icon-user-group');
define('ZBX_STYLE_ICON_MAINTENANCE', 'icon-maintenance');
define('ZBX_STYLE_ICON_WZRD_ACTION', 'icon-wzrd-action');
define('ZBX_STYLE_ACTION_COMMAND', 'icon-action-command');
define('ZBX_STYLE_ACTION_ICON_CLOSE', 'icon-action-close');
define('ZBX_STYLE_ACTION_ICON_MSG', 'icon-action-msg');
define('ZBX_STYLE_ACTION_ICON_MSGS', 'icon-action-msgs');
define('ZBX_STYLE_ACTION_ICON_SEV_UP', 'icon-action-severity-up');
define('ZBX_STYLE_ACTION_ICON_SEV_DOWN', 'icon-action-severity-down');
define('ZBX_STYLE_ACTION_ICON_SEV_CHANGED', 'icon-action-severity-changed');
define('ZBX_STYLE_ACTION_MESSAGE', 'icon-action-message');
define('ZBX_STYLE_ACTION_ICON_ACK', 'icon-action-ack');
define('ZBX_STYLE_ACTION_ICON_UNACK', 'icon-action-unack');
define('ZBX_STYLE_PROBLEM_GENERATED', 'icon-problem-generated');
define('ZBX_STYLE_PROBLEM_RECOVERY', 'icon-problem-recovery');
define('ZBX_STYLE_ACTIONS_NUM_GRAY', 'icon-actions-number-gray');
define('ZBX_STYLE_ACTIONS_NUM_YELLOW', 'icon-actions-number-yellow');
define('ZBX_STYLE_ACTIONS_NUM_RED', 'icon-actions-number-red');
define('ZBX_STYLE_INACTIVE_BG', 'inactive-bg');
define('ZBX_STYLE_INFO_BG', 'info-bg');
define('ZBX_STYLE_INLINE_FILTER', 'inline-filter');
define('ZBX_STYLE_INLINE_FILTER_LABEL', 'inline-filter-label');
define('ZBX_STYLE_INLINE_FILTER_FOOTER', 'inline-filter-footer');
define('ZBX_STYLE_INLINE_FILTER_STATS', 'inline-filter-stats');
define('ZBX_STYLE_LAYOUT_KIOSKMODE', 'layout-kioskmode');
define('ZBX_STYLE_CONTAINER', 'container');
define('ZBX_STYLE_LAYOUT_WRAPPER', 'wrapper');
define('ZBX_STYLE_LEFT', 'left');
define('ZBX_STYLE_LINK_ACTION', 'link-action');
define('ZBX_STYLE_LINK_ALT', 'link-alt');
define('ZBX_STYLE_LIST_CHECK_RADIO', 'list-check-radio');
define('ZBX_STYLE_LIST_DASHED', 'list-dashed');
define('ZBX_STYLE_LIST_TABLE', 'list-table');
define('ZBX_STYLE_LIST_TABLE_ACTIONS', 'list-table-actions');
define('ZBX_STYLE_LIST_TABLE_FOOTER', 'list-table-footer');
define('ZBX_STYLE_LIST_TABLE_STICKY_HEADER', 'sticky-header');
define('ZBX_STYLE_LIST_TABLE_STICKY_FOOTER', 'sticky-footer');
define('ZBX_STYLE_LIST_VERTICAL_ACCORDION', 'list-vertical-accordion');
define('ZBX_STYLE_LIST_ACCORDION_FOOT', 'list-accordion-foot');
define('ZBX_STYLE_LIST_ACCORDION_ITEM', 'list-accordion-item');
define('ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED', 'list-accordion-item-opened');
define('ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED', 'list-accordion-item-closed');
define('ZBX_STYLE_LIST_ACCORDION_ITEM_HEAD', 'list-accordion-item-head');
define('ZBX_STYLE_LIST_ACCORDION_ITEM_BODY', 'list-accordion-item-body');
define('ZBX_STYLE_LOCAL_CLOCK', 'local-clock');
define('ZBX_STYLE_LOG_NA_BG', 'log-na-bg');
define('ZBX_STYLE_LOG_INFO_BG', 'log-info-bg');
define('ZBX_STYLE_LOG_WARNING_BG', 'log-warning-bg');
define('ZBX_STYLE_LOG_HIGH_BG', 'log-high-bg');
define('ZBX_STYLE_LOG_DISASTER_BG', 'log-disaster-bg');
define('ZBX_STYLE_LOGO', 'logo');
define('ZBX_STYLE_MAP_AREA', 'map-area');
define('ZBX_STYLE_MIDDLE', 'middle');
define('ZBX_STYLE_MONOSPACE_FONT', 'monospace-font');
define('ZBX_STYLE_MSG_GOOD', 'msg-good');
define('ZBX_STYLE_MSG_BAD', 'msg-bad');
define('ZBX_STYLE_MSG_WARNING', 'msg-warning');
define('ZBX_STYLE_MSG_GLOBAL_FOOTER', 'msg-global-footer');
define('ZBX_STYLE_MSG_DETAILS', 'msg-details');
define('ZBX_STYLE_MSG_DETAILS_BORDER', 'msg-details-border');
define('ZBX_STYLE_NA_BG', 'na-bg');
define('ZBX_STYLE_NORMAL_BG', 'normal-bg');
define('ZBX_STYLE_NOTHING_TO_SHOW', 'nothing-to-show');
define('ZBX_STYLE_NOWRAP', 'nowrap');
define('ZBX_STYLE_WORDWRAP', 'wordwrap');
define('ZBX_STYLE_WORDBREAK', 'wordbreak');
define('ZBX_STYLE_ORANGE', 'orange');
define('ZBX_STYLE_OVERLAY_CLOSE_BTN', 'overlay-close-btn');
define('ZBX_STYLE_OVERLAY_DESCR', 'overlay-descr');
define('ZBX_STYLE_OVERLAY_DESCR_URL', 'overlay-descr-url');
define('ZBX_STYLE_OVERFLOW_ELLIPSIS', 'overflow-ellipsis');
define('ZBX_STYLE_PAGING_BTN_CONTAINER', 'paging-btn-container');
define('ZBX_STYLE_PAGING_SELECTED', 'paging-selected');
define('ZBX_STYLE_PAGE_TITLE', 'page-title-general');
define('ZBX_STYLE_PAGE_TITLE_SUBMENU', 'page-title-submenu');
define('ZBX_STYLE_RED', 'red');
define('ZBX_STYLE_RED_BG', 'red-bg');
define('ZBX_STYLE_REL_CONTAINER', 'rel-container');
define('ZBX_STYLE_RIGHT', 'right');
define('ZBX_STYLE_ROW', 'row');
define('ZBX_STYLE_INLINE_SR_ONLY', 'inline-sr-only');
define('ZBX_STYLE_VALUEMAP_LIST_TABLE', 'valuemap-list-table');
define('ZBX_STYLE_VALUEMAP_CHECKBOX', 'valuemap-checkbox');
define('ZBX_STYLE_VALUEMAP_MAPPINGS_TABLE', 'mappings-table');
define('ZBX_STYLE_SEARCH', 'search');
define('ZBX_STYLE_FORM_SEARCH', 'form-search');
define('ZBX_STYLE_SECOND_COLUMN_LABEL', 'second-column-label');
define('ZBX_STYLE_SELECTED', 'selected');
define('ZBX_STYLE_SELECTED_ITEM_COUNT', 'selected-item-count');
define('ZBX_STYLE_SERVER_NAME', 'server-name');
define('ZBX_STYLE_SERVICE_ACTIONS', 'service-actions');
define('ZBX_STYLE_SERVICE_INFO', 'service-info');
define('ZBX_STYLE_SERVICE_INFO_GRID', 'service-info-grid');
define('ZBX_STYLE_SERVICE_INFO_LABEL', 'service-info-label');
define('ZBX_STYLE_SERVICE_INFO_VALUE', 'service-info-value');
define('ZBX_STYLE_SERVICE_INFO_VALUE_SLA', 'service-info-value-sla');
define('ZBX_STYLE_SERVICE_NAME', 'service-name');
define('ZBX_STYLE_SERVICE_STATUS', 'service-status');
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
define('ZBX_STYLE_SVG_GRAPH', 'svg-graph');
define('ZBX_STYLE_SVG_GRAPH_PREVIEW', 'svg-graph-preview');
define('ZBX_STYLE_SUBFILTER', 'subfilter');
define('ZBX_STYLE_SUBFILTER_ENABLED', 'subfilter-enabled');
define('ZBX_STYLE_TABLE', 'table');
define('ZBX_STYLE_TABLE_FORMS', 'table-forms');
define('ZBX_STYLE_TABLE_FORMS_CONTAINER', 'table-forms-container');
define('ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN', 'table-forms-second-column');
define('ZBX_STYLE_TABLE_FORMS_TD_LEFT', 'table-forms-td-left');
define('ZBX_STYLE_TABLE_FORMS_TD_RIGHT', 'table-forms-td-right');
define('ZBX_STYLE_TABLE_FORMS_OVERFLOW_BREAK', 'overflow-break');
define('ZBX_STYLE_TABLE_PAGING', 'table-paging');
define('ZBX_STYLE_TABLE_STATS', 'table-stats');
define('ZBX_STYLE_TABS_NAV', 'tabs-nav');
define('ZBX_STYLE_TAG', 'tag');
define('ZBX_STYLE_TEXT_PLACEHOLDER', 'text-placeholder');
define('ZBX_STYLE_TEXTAREA_FLEXIBLE', 'textarea-flexible');
define('ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER', 'textarea-flexible-container');
define('ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT', 'textarea-flexible-parent');
define('ZBX_STYLE_TFOOT_BUTTONS', 'tfoot-buttons');
define('ZBX_STYLE_TD_DRAG_ICON', 'td-drag-icon');
define('ZBX_STYLE_TIME_ZONE', 'time-zone');
define('ZBX_STYLE_TIMELINE_AXIS', 'timeline-axis');
define('ZBX_STYLE_TIMELINE_DATE', 'timeline-date');
define('ZBX_STYLE_TIMELINE_DOT', 'timeline-dot');
define('ZBX_STYLE_TIMELINE_DOT_BIG', 'timeline-dot-big');
define('ZBX_STYLE_TIMELINE_TD', 'timeline-td');
define('ZBX_STYLE_TIMELINE_TH', 'timeline-th');
define('ZBX_STYLE_TOC', 'toc');
define('ZBX_STYLE_TOC_ARROW', 'toc-arrow');
define('ZBX_STYLE_TOC_ITEM', 'toc-item');
define('ZBX_STYLE_TOC_LIST', 'toc-list');
define('ZBX_STYLE_TOC_ROW', 'toc-row');
define('ZBX_STYLE_TOC_SUBLIST', 'toc-sublist');
define('ZBX_STYLE_TOP', 'top');
define('ZBX_STYLE_TOTALS_LIST', 'totals-list');
define('ZBX_STYLE_TOTALS_LIST_HORIZONTAL', 'totals-list-horizontal');
define('ZBX_STYLE_TOTALS_LIST_VERTICAL', 'totals-list-vertical');
define('ZBX_STYLE_TOTALS_LIST_COUNT', 'count');
define('ZBX_STYLE_TREEVIEW', 'treeview');
define('ZBX_STYLE_TREEVIEW_PLUS', 'treeview-plus');
define('ZBX_STYLE_UPPERCASE', 'uppercase');
define('ZBX_STYLE_WARNING_BG', 'warning-bg');
define('ZBX_STYLE_WIDGET_URL', 'widget-url');
define('ZBX_STYLE_BLINK_HIDDEN', 'blink-hidden');
define('ZBX_STYLE_YELLOW', 'yellow');
define('ZBX_STYLE_YELLOW_BG', 'yellow-bg');
define('ZBX_STYLE_FIELD_LABEL_ASTERISK', 'form-label-asterisk');
define('ZBX_STYLE_PROBLEM_ICON_LIST' , 'problem-icon-list');
define('ZBX_STYLE_PROBLEM_ICON_LINK' , 'problem-icon-link');
define('ZBX_STYLE_PROBLEM_ICON_LIST_ITEM' , 'problem-icon-list-item');
define('ZBX_STYLE_ZABBIX_LOGO', 'zabbix-logo');
define('ZBX_STYLE_ZABBIX_SIDEBAR_LOGO', 'zabbix-sidebar-logo');
define('ZBX_STYLE_ZABBIX_SIDEBAR_LOGO_COMPACT', 'zabbix-sidebar-logo-compact');
define('ZBX_STYLE_WIDGET_ITEM_LABEL', 'widget-item-label');

// HTML column layout.
define('ZBX_STYLE_COLUMNS', 'columns-wrapper');
define('ZBX_STYLE_COLUMNS_NOWRAP', 'columns-nowrap');
define('ZBX_STYLE_COLUMNS_2', 'columns-2');
define('ZBX_STYLE_COLUMNS_3', 'columns-3');
// column occupies x% width of column wrapper
define('ZBX_STYLE_COLUMN_5', 'column-5');
define('ZBX_STYLE_COLUMN_10', 'column-10');
define('ZBX_STYLE_COLUMN_15', 'column-15');
define('ZBX_STYLE_COLUMN_20', 'column-20');
define('ZBX_STYLE_COLUMN_33', 'column-33'); // column occupies 1/3 width of column wrapper.
define('ZBX_STYLE_COLUMN_35', 'column-35');
define('ZBX_STYLE_COLUMN_40', 'column-40');
define('ZBX_STYLE_COLUMN_50', 'column-50');
define('ZBX_STYLE_COLUMN_75', 'column-75');
define('ZBX_STYLE_COLUMN_90', 'column-90');
define('ZBX_STYLE_COLUMN_95', 'column-95');

// column visual options
define('ZBX_STYLE_COLUMN_CENTER', 'column-center');
define('ZBX_STYLE_COLUMN_MIDDLE', 'column-middle');

// Widget "Host availability" styles.
define('ZBX_STYLE_HOST_AVAIL_WIDGET', 'host-avail-widget');
define('ZBX_STYLE_HOST_AVAIL_TRUE', 'host-avail-true');
define('ZBX_STYLE_HOST_AVAIL_FALSE', 'host-avail-false');
define('ZBX_STYLE_HOST_AVAIL_UNKNOWN', 'host-avail-unknown');
define('ZBX_STYLE_HOST_AVAIL_TOTAL', 'host-avail-total');

// Widget "Problems by severity" styles.
define('ZBX_STYLE_BY_SEVERITY_WIDGET', 'by-severity-widget');

define('ZBX_STYLE_CHECKBOX_BLOCK', 'checkbox-block');

// Icons.
define('ZBX_STYLE_ICON_TEXT', 'icon-text');
define('ZBX_STYLE_ICON_SECRET_TEXT', 'icon-secret');
define('ZBX_STYLE_ICON_HELP_HINT', 'icon-help-hint');

// Host interface styles.
define('ZBX_STYLE_HOST_INTERFACE_CONTAINER', 'interface-container');
define('ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER', 'interface-container-header');
define('ZBX_STYLE_HOST_INTERFACE_ROW', 'interface-row');
define('ZBX_STYLE_HOST_INTERFACE_ROW_HEADER', 'interface-row-header');
define('ZBX_STYLE_HOST_INTERFACE_CELL', 'interface-cell');
define('ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS', 'interface-cell-details');
define('ZBX_STYLE_HOST_INTERFACE_CELL_HEADER', 'interface-cell-header');
define('ZBX_STYLE_HOST_INTERFACE_CELL_TYPE', 'interface-cell-type');
define('ZBX_STYLE_HOST_INTERFACE_CELL_IP', 'interface-cell-ip');
define('ZBX_STYLE_HOST_INTERFACE_CELL_DNS', 'interface-cell-dns');
define('ZBX_STYLE_HOST_INTERFACE_CELL_USEIP', 'interface-cell-useip');
define('ZBX_STYLE_HOST_INTERFACE_CELL_PORT', 'interface-cell-port');
define('ZBX_STYLE_HOST_INTERFACE_CELL_DEFAULT', 'interface-cell-default');
define('ZBX_STYLE_HOST_INTERFACE_CELL_ACTION', 'interface-cell-action');
define('ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE', 'interface-btn-toggle');
define('ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE', 'interface-btn-remove');
define('ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE', 'interface-btn-main-interface');
define('ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND', 'interface-input-expand');

define('ZBX_STYLE_ZSELECT_HOST_INTERFACE', 'z-select-host-interface');

// Dashboard list table classes.
define('ZBX_STYLE_DASHBOARD_LIST', 'dashboard-list');
define('ZBX_STYLE_DASHBOARD_LIST_ITEM', 'dashboard-list-item');

// server variables
define('HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off');

define('ZBX_PROPERTY_INHERITED',	0x01);
define('ZBX_PROPERTY_OWN',			0x02);
define('ZBX_PROPERTY_BOTH',			0x03);	// ZBX_PROPERTY_INHERITED | ZBX_PROPERTY_OWN

// Number of tags to display in Problems widget and Monitoring > Problems.
define('SHOW_TAGS_NONE', 0);
define('SHOW_TAGS_1', 1);
define('SHOW_TAGS_2', 2);
define('SHOW_TAGS_3', 3);

// Tag name format to display in Problems widget and Monitoring > Problems.
define('TAG_NAME_FULL',      0);
define('TAG_NAME_SHORTENED', 1);
define('TAG_NAME_NONE',      2);

define('OPERATIONAL_DATA_SHOW_NONE',         0);
define('OPERATIONAL_DATA_SHOW_SEPARATELY',   1);
define('OPERATIONAL_DATA_SHOW_WITH_PROBLEM', 2);

define('ZBX_ROLE_RULE_DISABLED',				0);
define('ZBX_ROLE_RULE_ENABLED',					1);
define('ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM',	0);
define('ZBX_ROLE_RULE_SERVICES_ACCESS_ALL',		1);
define('ZBX_ROLE_RULE_API_MODE_DENY',			0);
define('ZBX_ROLE_RULE_API_MODE_ALLOW',			1);
define('ZBX_ROLE_RULE_API_WILDCARD',			'*');
define('ZBX_ROLE_RULE_API_WILDCARD_ALIAS',		'*.*');

// Allows to set "rel" tag value "noreferer" when setting target="_blank".
define('ZBX_NOREFERER', true);

// High availability server node states.
define('ZBX_NODE_STATUS_STANDBY',		0);
define('ZBX_NODE_STATUS_STOPPED',		1);
define('ZBX_NODE_STATUS_UNAVAILABLE',	2);
define('ZBX_NODE_STATUS_ACTIVE',		3);

// init $_REQUEST
ini_set('variables_order', 'GP');
$_REQUEST = $_POST + $_GET;

// init precision
ini_set('precision', 14);

// BC Math scale. bcscale() can be undefined prior requirement check in setup.
if (function_exists('bcscale')) {
	bcscale(7);
}
