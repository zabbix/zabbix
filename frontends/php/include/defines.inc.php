<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	define("XML_TAG_ZABBIX_EXPORT",		'zabbix_export');
	define("XML_TAG_HOSTS",			'hosts');
	define("XML_TAG_HOST",			'host');
	define("XML_TAG_GROUPS",		'groups');
	define("XML_TAG_GROUP",			'group');
	define("XML_TAG_ITEMS",			'items');
	define("XML_TAG_ITEM",			'item');
	define("XML_TAG_TRIGGERS",		'triggers');
	define("XML_TAG_TRIGGER",		'trigger');
	define("XML_TAG_GRAPHS",		'graphs');
	define("XML_TAG_GRAPH",			'graph');
	define("XML_TAG_GRAPH_ELEMENT",		'graph_element');
	define("XML_TAG_GRAPH_ELEMENTS",	'graph_elements');
	define("XML_TAG_SCREENS",		'screens');
	define("XML_TAG_SCREEN",		'screen');
	define("XML_TAG_SCREEN_ELEMENT",	'screen_element');
	define("XML_TAG_SCREEN_ELEMENTS",	'screen_elements');
	
	define("PAGE_TYPE_HTML",	0);
	define("PAGE_TYPE_IMAGE",	1);
	define("PAGE_TYPE_XML",		2);

	define("T_ZBX_STR",			0);
	define("T_ZBX_INT",			1);
	define("T_ZBX_DBL",			2);
	define("T_ZBX_PERIOD",			3);
	define("T_ZBX_IP",			4);
	define("T_ZBX_CLR",			5);

	define("O_MAND",			0);
	define("O_OPT",				1);
	define("O_NO",				2);

	define("P_SYS",				1);
	define("P_USR",				2);
	define("P_GET",				4);
	define("P_POST",			8);
	define("P_ACT",				16);
	define("P_NZERO",			32);

//	MISC PARAMETERS
	define("IMAGE_FORMAT_PNG",         	"PNG");
	define("IMAGE_FORMAT_JPEG",         	"JPEG");
	define("IMAGE_FORMAT_TEXT",         	"JPEG");
//	END OF MISC PARAMETERS

	define("AUDIT_ACTION_ADD",		0);
	define("AUDIT_ACTION_UPDATE",		1);
	define("AUDIT_ACTION_DELETE",		2);
	define("AUDIT_ACTION_LOGIN",		3);
	define("AUDIT_ACTION_LOGOUT",		4);

	define("AUDIT_RESOURCE_USER",		0);
//	define("AUDIT_RESOURCE_ZABBIX",		1);
	define("AUDIT_RESOURCE_ZABBIX_CONFIG",	2);
	define("AUDIT_RESOURCE_MEDIA_TYPE",	3);
	define("AUDIT_RESOURCE_HOST",		4);
	define("AUDIT_RESOURCE_ACTION",		5);
	define("AUDIT_RESOURCE_GRAPH",		6);
	define("AUDIT_RESOURCE_GRAPH_ELEMENT",	7);
//	define("AUDIT_RESOURCE_ESCALATION",	8);
//	define("AUDIT_RESOURCE_ESCALATION_RULE",9);
//	define("AUDIT_RESOURCE_AUTOREGISTRATION",10);
	define("AUDIT_RESOURCE_USER_GROUP",	11);
	define("AUDIT_RESOURCE_APPLICATION",	12);
	define("AUDIT_RESOURCE_TRIGGER",	13);
	define("AUDIT_RESOURCE_HOST_GROUP",	14);
	define("AUDIT_RESOURCE_ITEM",		15);
	define("AUDIT_RESOURCE_IMAGE",		16);
	define("AUDIT_RESOURCE_VALUE_MAP",	17);
	define("AUDIT_RESOURCE_IT_SERVICE",	18);
	define("AUDIT_RESOURCE_MAP",		19);
	define("AUDIT_RESOURCE_SCREEN",		20);
	define("AUDIT_RESOURCE_NODE",		21);

	define("CONDITION_TYPE_GROUP",		0);
	define("CONDITION_TYPE_HOST",		1);
	define("CONDITION_TYPE_TRIGGER",	2);
	define("CONDITION_TYPE_TRIGGER_NAME",	3);
	define("CONDITION_TYPE_TRIGGER_SEVERITY",4);
	define("CONDITION_TYPE_TRIGGER_VALUE",	5);
	define("CONDITION_TYPE_TIME_PERIOD",	6);

	define("CONDITION_OPERATOR_EQUAL",	0);
	define("CONDITION_OPERATOR_NOT_EQUAL",	1);
	define("CONDITION_OPERATOR_LIKE",	2);
	define("CONDITION_OPERATOR_NOT_LIKE",	3);
	define("CONDITION_OPERATOR_IN",		4);
	define("CONDITION_OPERATOR_MORE_EQUAL",	5);
	define("CONDITION_OPERATOR_LESS_EQUAL",	6);

	define("HOST_STATUS_MONITORED",		0);
	define("HOST_STATUS_NOT_MONITORED",	1);
//	define("HOST_STATUS_UNREACHABLE",	2);
	define("HOST_STATUS_TEMPLATE",		3);
	define("HOST_STATUS_DELETED",		4);

	define("HOST_AVAILABLE_UNKNOWN",	0);
	define("HOST_AVAILABLE_TRUE",		1);
	define("HOST_AVAILABLE_FALSE",		2);

	define("MAP_LABEL_TYPE_LABEL",0);
	define("MAP_LABEL_TYPE_IP",1);
	define("MAP_LABEL_TYPE_NAME",2);
	define("MAP_LABEL_TYPE_STATUS",3);
	define("MAP_LABEL_TYPE_NOTHING",4);

	define("MAP_LABEL_LOC_BOTTOM",		0);
	define("MAP_LABEL_LOC_LEFT",		1);
	define("MAP_LABEL_LOC_RIGHT",		2);
	define("MAP_LABEL_LOC_TOP",		3);

	define("SYSMAP_ELEMENT_TYPE_HOST",	0);
	define("SYSMAP_ELEMENT_TYPE_MAP",	1);
	define("SYSMAP_ELEMENT_TYPE_TRIGGER",	2);
	define("SYSMAP_ELEMENT_TYPE_HOST_GROUP",3);

	define("ITEM_TYPE_ZABBIX",0);
	define("ITEM_TYPE_SNMPV1",1);
	define("ITEM_TYPE_TRAPPER",2);
	define("ITEM_TYPE_SIMPLE",3);
	define("ITEM_TYPE_SNMPV2C",4);
	define("ITEM_TYPE_INTERNAL",5);
	define("ITEM_TYPE_SNMPV3",6);
	define("ITEM_TYPE_ZABBIX_ACTIVE",7);
	define("ITEM_TYPE_AGGREGATE",8);

	define("ITEM_VALUE_TYPE_FLOAT",0);
	define("ITEM_VALUE_TYPE_STR",1);
	define("ITEM_VALUE_TYPE_LOG",2);
	define("ITEM_VALUE_TYPE_UINT64",3);
	define("ITEM_VALUE_TYPE_TEXT",4);

	define("ITEM_STATUS_ACTIVE",0);
	define("ITEM_STATUS_DISABLED",1);
	define("ITEM_STATUS_NOTSUPPORTED",3);

	define("ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV",0);
	define("ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV",1);
	define("ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV",2);

	define('GRAPH_ITEM_DRAWTYPE_LINE',		0);
	define('GRAPH_ITEM_DRAWTYPE_FILLED_REGION',	1);
	define('GRAPH_ITEM_DRAWTYPE_BOLD_LINE',		2);
	define('GRAPH_ITEM_DRAWTYPE_DOT',		3);
	define('GRAPH_ITEM_DRAWTYPE_DASHED_LINE',	4);

	define("SERVICE_ALGORITHM_NONE",0);
	define("SERVICE_ALGORITHM_MAX",1);
	define("SERVICE_ALGORITHM_MIN",2);

	define("TRIGGER_VALUE_FALSE",0);
	define("TRIGGER_VALUE_TRUE",1);
	define("TRIGGER_VALUE_UNKNOWN",2);

	define("ALERT_STATUS_NOT_SENT",0);
	define("ALERT_STATUS_SENT",1);

	define("ALERT_TYPE_EMAIL",0);
	define("ALERT_TYPE_EXEC",1);
	define("ALERT_TYPE_SMS",2);

	define("ACTION_STATUS_ENABLED",0);
	define("ACTION_STATUS_DISABLED",1);

	define("ACTION_TYPE_MESSAGE",0);
	define("ACTION_TYPE_COMMAND",1);

	define("TRIGGER_STATUS_ENABLED",0);
	define("TRIGGER_STATUS_DISABLED",1);
	define("TRIGGER_STATUS_UNKNOWN",2);

	define("RECIPIENT_TYPE_USER",0);
	define("RECIPIENT_TYPE_GROUP",1);

	define("LOGFILE_SEVERITY_NOT_CLASSIFIED",0);
	define("LOGFILE_SEVERITY_INFORMATION",1);
	define("LOGFILE_SEVERITY_WARNING",2);
	define("LOGFILE_SEVERITY_AVERAGE",3);
	define("LOGFILE_SEVERITY_HIGH",4);
	define("LOGFILE_SEVERITY_DISASTER",5);
	define("LOGFILE_SEVERITY_AUDIT_SUCCESS",6);
	define("LOGFILE_SEVERITY_AUDIT_FAILURE",7);

	define("SCREEN_RESOURCE_GRAPH", 0);
	define("SCREEN_RESOURCE_SIMPLE_GRAPH", 1);
	define("SCREEN_RESOURCE_MAP", 2);
	define("SCREEN_RESOURCE_PLAIN_TEXT", 3);
	define("SCREEN_RESOURCE_HOSTS_INFO", 4);
	define("SCREEN_RESOURCE_TRIGGERS_INFO", 5);
	define("SCREEN_RESOURCE_SERVER_INFO", 6);
	define("SCREEN_RESOURCE_CLOCK", 7);
	define("SCREEN_RESOURCE_SCREEN", 8);
	define("SCREEN_RESOURCE_TRIGGERS_OVERVIEW", 9);
	define("SCREEN_RESOURCE_DATA_OVERVIEW", 10);
	define("SCREEN_RESOURCE_URL", 11);
	define("SCREEN_RESOURCE_ACTIONS", 12);
	define("SCREEN_RESOURCE_EVENTS",13);

/* alignes */
	define("HALIGN_DEFAULT",0);
	define("HALIGN_CENTER",	0);
	define("HALIGN_LEFT",	1);
	define("HALIGN_RIGHT",	2);

	define("VALIGN_DEFAULT",0);
	define("VALIGN_MIDDLE",	0);
	define("VALIGN_TOP",	1);
	define("VALIGN_BOTTOM",	2);

/* info module style */
	define("STYLE_HORISONTAL",	0);
	define("STYLE_VERTICAL",	1);

/* time module tipe */
        define("TIME_TYPE_LOCAL",	0);
        define("TIME_TYPE_SERVER",	1);

	define("FILTER_TAST_SHOW",	0);
	define("FILTER_TAST_HIDE",	1);
	define("FILTER_TAST_MARK",	2);
	define("FILTER_TAST_INVERT_MARK", 3);

	define("MARK_COLOR_RED",	1);
	define("MARK_COLOR_GREEN",	2);
	define("MARK_COLOR_BLUE",	3);

	define("PROFILE_TYPE_UNCNOWN",	0);
	define("PROFILE_TYPE_ARRAY",	1);
	define("PROFILE_TYPE_INT",	2);
	define("PROFILE_TYPE_STR",	3);

	define("CALC_FNC_MIN", 1);
	define("CALC_FNC_AVG", 2);
	define("CALC_FNC_MAX", 4);
	define("CALC_FNC_ALL", 7);

	
	define("SERVICE_TIME_TYPE_UPTIME", 0);
	define("SERVICE_TIME_TYPE_DOWNTIME", 1);
	define("SERVICE_TIME_TYPE_ONETIME_DOWNTIME", 2);

	define("USER_TYPE_ZABBIX_USER",		1);
	define("USER_TYPE_ZABBIX_ADMIN",	2);
	define("USER_TYPE_SUPER_ADMIN",		3);

	define("PERM_MAX",		3);
	define("PERM_READ_WRITE",	3);
	define("PERM_READ_ONLY",	2);
	define("PERM_READ_LIST",	1);
	define("PERM_DENY",		0);

	define("PERM_RES_STRING_LINE",	0); /* return string of nodes id - "1,2,3,4,5" */
	define("PERM_RES_IDS_ARRAY",	1); /* return array of nodes id - array(1,2,3,4) */
	define("PERM_RES_DATA_ARRAY",	2); 

	define("PERM_MODE_NE",	5);
	define("PERM_MODE_EQ",	4);
	define("PERM_MODE_GT",	3);
	define("PERM_MODE_LT",	2);
	define("PERM_MODE_LE",	1);
	define("PERM_MODE_GE",	0);

	define("RESOURCE_TYPE_NODE",		0);
	define("RESOURCE_TYPE_GROUP",		1);

	define("ZBX_NODE_REMOTE",	0);
	define("ZBX_NODE_LOCAL",	1);
	define("ZBX_NODE_MASTER",	2);

/* Support for PHP5. PHP5 does not have $HTTP_..._VARS */
	if (!function_exists('version_compare'))
	{
		$_REQUEST = $HTTP_GET_VARS;
		$_POST = $HTTP_POST_VARS;
		$_COOKIE = $HTTP_COOKIE_VARS;
	}
?>
