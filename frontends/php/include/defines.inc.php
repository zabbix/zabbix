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
	define('XML_TAG_ZABBIX_EXPORT',		'zabbix_export');
	define('XML_TAG_HOSTS',			'hosts');
	define('XML_TAG_HOST',			'host');
	define('XML_TAG_GROUPS',		'groups');
	define('XML_TAG_GROUP',			'group');
	define('XML_TAG_APPLICATIONS',		'applications');
	define('XML_TAG_APPLICATION',		'application');
	define('XML_TAG_ITEMS',			'items');
	define('XML_TAG_ITEM',			'item');
	define('XML_TAG_TRIGGERS',		'triggers');
	define('XML_TAG_TRIGGER',		'trigger');
	define('XML_TAG_GRAPHS',		'graphs');
	define('XML_TAG_GRAPH',			'graph');
	define('XML_TAG_GRAPH_ELEMENT',		'graph_element');
	define('XML_TAG_GRAPH_ELEMENTS',	'graph_elements');
	define('XML_TAG_SCREENS',		'screens');
	define('XML_TAG_SCREEN',		'screen');
	define('XML_TAG_SCREEN_ELEMENT',	'screen_element');
	define('XML_TAG_SCREEN_ELEMENTS',	'screen_elements');
	
	define('PAGE_TYPE_HTML',	0);
	define('PAGE_TYPE_IMAGE',	1);
	define('PAGE_TYPE_XML',		2);

	define('T_ZBX_STR',			0);
	define('T_ZBX_INT',			1);
	define('T_ZBX_DBL',			2);
	define('T_ZBX_PERIOD',			3);
	define('T_ZBX_IP',			4);
	define('T_ZBX_CLR',			5);
	define('T_ZBX_PORTS',			6);
	define('T_ZBX_IP_RANGE',		7);
	define('T_ZBX_INT_RANGE',		8);

	define('O_MAND',			0);
	define('O_OPT',				1);
	define('O_NO',				2);

	define('P_SYS',				1);
	define('P_UNSET_EMPTY',			2);
//	define('P_USR',				2);
//	define('P_GET',				4);
//	define('P_POST',			8);
	define('P_ACT',				16);
	define('P_NZERO',			32);

//	MISC PARAMETERS
	define('IMAGE_FORMAT_PNG',         	'PNG');
	define('IMAGE_FORMAT_JPEG',         	'JPEG');
	define('IMAGE_FORMAT_TEXT',         	'JPEG');
//	END OF MISC PARAMETERS

	define('AUDIT_ACTION_ADD',		0);
	define('AUDIT_ACTION_UPDATE',		1);
	define('AUDIT_ACTION_DELETE',		2);
	define('AUDIT_ACTION_LOGIN',		3);
	define('AUDIT_ACTION_LOGOUT',		4);

	define('AUDIT_RESOURCE_USER',		0);
//	define('AUDIT_RESOURCE_ZABBIX',		1);
	define('AUDIT_RESOURCE_ZABBIX_CONFIG',	2);
	define('AUDIT_RESOURCE_MEDIA_TYPE',	3);
	define('AUDIT_RESOURCE_HOST',		4);
	define('AUDIT_RESOURCE_ACTION',		5);
	define('AUDIT_RESOURCE_GRAPH',		6);
	define('AUDIT_RESOURCE_GRAPH_ELEMENT',	7);
//	define('AUDIT_RESOURCE_ESCALATION',	8);
//	define('AUDIT_RESOURCE_ESCALATION_RULE',9);
//	define('AUDIT_RESOURCE_AUTOREGISTRATION',10);
	define('AUDIT_RESOURCE_USER_GROUP',	11);
	define('AUDIT_RESOURCE_APPLICATION',	12);
	define('AUDIT_RESOURCE_TRIGGER',	13);
	define('AUDIT_RESOURCE_HOST_GROUP',	14);
	define('AUDIT_RESOURCE_ITEM',		15);
	define('AUDIT_RESOURCE_IMAGE',		16);
	define('AUDIT_RESOURCE_VALUE_MAP',	17);
	define('AUDIT_RESOURCE_IT_SERVICE',	18);
	define('AUDIT_RESOURCE_MAP',		19);
	define('AUDIT_RESOURCE_SCREEN',		20);
	define('AUDIT_RESOURCE_NODE',		21);
	define('AUDIT_RESOURCE_SCENARIO',	22);
	define('AUDIT_RESOURCE_DISCOVERY_RULE',	23);
	define('AUDIT_RESOURCE_SLIDESHOW',	24);

	define('CONDITION_TYPE_HOST_GROUP',	0);
	define('CONDITION_TYPE_HOST',		1);
	define('CONDITION_TYPE_TRIGGER',	2);
	define('CONDITION_TYPE_TRIGGER_NAME',	3);
	define('CONDITION_TYPE_TRIGGER_SEVERITY',4);
	define('CONDITION_TYPE_TRIGGER_VALUE',	5);
	define('CONDITION_TYPE_TIME_PERIOD',	6);
	define('CONDITION_TYPE_DHOST_IP',	7);
	define('CONDITION_TYPE_DSERVICE_TYPE',	8);
	define('CONDITION_TYPE_DSERVICE_PORT',	9);
	define('CONDITION_TYPE_DSTATUS',	10);
	define('CONDITION_TYPE_DUPTIME',	11);
	define('CONDITION_TYPE_DVALUE',		12);

	define('CONDITION_OPERATOR_EQUAL',	0);
	define('CONDITION_OPERATOR_NOT_EQUAL',	1);
	define('CONDITION_OPERATOR_LIKE',	2);
	define('CONDITION_OPERATOR_NOT_LIKE',	3);
	define('CONDITION_OPERATOR_IN',		4);
	define('CONDITION_OPERATOR_MORE_EQUAL',	5);
	define('CONDITION_OPERATOR_LESS_EQUAL',	6);
	define('CONDITION_OPERATOR_NOT_IN',	7);

	define('HOST_STATUS_MONITORED',		0);
	define('HOST_STATUS_NOT_MONITORED',	1);
//	define('HOST_STATUS_UNREACHABLE',	2);
	define('HOST_STATUS_TEMPLATE',		3);
	define('HOST_STATUS_DELETED',		4);

	define('HOST_AVAILABLE_UNKNOWN',	0);
	define('HOST_AVAILABLE_TRUE',		1);
	define('HOST_AVAILABLE_FALSE',		2);

	define('MAP_LABEL_TYPE_LABEL',0);
	define('MAP_LABEL_TYPE_IP',1);
	define('MAP_LABEL_TYPE_NAME',2);
	define('MAP_LABEL_TYPE_STATUS',3);
	define('MAP_LABEL_TYPE_NOTHING',4);

	define('MAP_LABEL_LOC_BOTTOM',		0);
	define('MAP_LABEL_LOC_LEFT',		1);
	define('MAP_LABEL_LOC_RIGHT',		2);
	define('MAP_LABEL_LOC_TOP',		3);

	define('SYSMAP_ELEMENT_TYPE_HOST',	0);
	define('SYSMAP_ELEMENT_TYPE_MAP',	1);
	define('SYSMAP_ELEMENT_TYPE_TRIGGER',	2);
	define('SYSMAP_ELEMENT_TYPE_HOST_GROUP',3);

	define('SYSMAP_ELEMENT_ICON_ON',	0);
	define('SYSMAP_ELEMENT_ICON_OFF',	1);
	define('SYSMAP_ELEMENT_ICON_UNKNOWN',	2);

	define('ITEM_TYPE_ZABBIX',0);
	define('ITEM_TYPE_SNMPV1',1);
	define('ITEM_TYPE_TRAPPER',2);
	define('ITEM_TYPE_SIMPLE',3);
	define('ITEM_TYPE_SNMPV2C',4);
	define('ITEM_TYPE_INTERNAL',5);
	define('ITEM_TYPE_SNMPV3',6);
	define('ITEM_TYPE_ZABBIX_ACTIVE',7);
	define('ITEM_TYPE_AGGREGATE',8);
	define('ITEM_TYPE_HTTPTEST',9);
	define('ITEM_TYPE_EXTERNAL',10);

	define('ITEM_VALUE_TYPE_FLOAT',0);
	define('ITEM_VALUE_TYPE_STR',1);
	define('ITEM_VALUE_TYPE_LOG',2);
	define('ITEM_VALUE_TYPE_UINT64',3);
	define('ITEM_VALUE_TYPE_TEXT',4);

	define('ITEM_STATUS_ACTIVE',0);
	define('ITEM_STATUS_DISABLED',1);
	define('ITEM_STATUS_NOTSUPPORTED',3);

	define('ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV',0);
	define('ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV',1);
	define('ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV',2);

	define('GRAPH_ITEM_DRAWTYPE_LINE',		0);
	define('GRAPH_ITEM_DRAWTYPE_FILLED_REGION',	1);
	define('GRAPH_ITEM_DRAWTYPE_BOLD_LINE',		2);
	define('GRAPH_ITEM_DRAWTYPE_DOT',		3);
	define('GRAPH_ITEM_DRAWTYPE_DASHED_LINE',	4);

	define('MAP_LINK_DRAWTYPE_LINE',		0);
	define('MAP_LINK_DRAWTYPE_BOLD_LINE',		2);
	define('MAP_LINK_DRAWTYPE_DOT',			3);
	define('MAP_LINK_DRAWTYPE_DASHED_LINE',		4);

	define('SERVICE_ALGORITHM_NONE',0);
	define('SERVICE_ALGORITHM_MAX',1);
	define('SERVICE_ALGORITHM_MIN',2);

	define('TRIGGER_VALUE_FALSE',0);
	define('TRIGGER_VALUE_TRUE',1);
	define('TRIGGER_VALUE_UNKNOWN',2);

	define('TRIGGER_SEVERITY_NOT_CLASSIFIED',	0);
	define('TRIGGER_SEVERITY_INFORMATION',		1);
	define('TRIGGER_SEVERITY_WARNING',		2);
	define('TRIGGER_SEVERITY_AVERAGE',		3);
	define('TRIGGER_SEVERITY_HIGH',			4);
	define('TRIGGER_SEVERITY_DISASTER',		5);

	define('ALERT_STATUS_NOT_SENT',0);
	define('ALERT_STATUS_SENT',1);

	define('ALERT_TYPE_EMAIL',0);
	define('ALERT_TYPE_EXEC',1);
	define('ALERT_TYPE_SMS',2);
	define('ALERT_TYPE_JABBER',3);

	define('ACTION_STATUS_ENABLED',0);
	define('ACTION_STATUS_DISABLED',1);

	define('OPERATION_TYPE_MESSAGE',	0);
	define('OPERATION_TYPE_COMMAND',	1);
	define('OPERATION_TYPE_HOST_ADD',	2);
	define('OPERATION_TYPE_HOST_REMOVE',	3);
	define('OPERATION_TYPE_GROUP_ADD',	4);
	define('OPERATION_TYPE_GROUP_REMOVE',	5);
	define('OPERATION_TYPE_TEMPLATE_ADD',	6);
	define('OPERATION_TYPE_TEMPLATE_REMOVE',7);

	define('ACTION_EVAL_TYPE_AND_OR',0);
	define('ACTION_EVAL_TYPE_AND',1);
	define('ACTION_EVAL_TYPE_OR',2);

	define('TRIGGER_STATUS_ENABLED',0);
	define('TRIGGER_STATUS_DISABLED',1);
	define('TRIGGER_STATUS_UNKNOWN',2);

	define('OPERATION_OBJECT_USER',		0);
	define('OPERATION_OBJECT_GROUP',	1);

	define('LOGFILE_SEVERITY_NOT_CLASSIFIED',0);
	define('LOGFILE_SEVERITY_INFORMATION',1);
	define('LOGFILE_SEVERITY_WARNING',2);
	define('LOGFILE_SEVERITY_AVERAGE',3);
	define('LOGFILE_SEVERITY_HIGH',4);
	define('LOGFILE_SEVERITY_DISASTER',5);
	define('LOGFILE_SEVERITY_AUDIT_SUCCESS',6);
	define('LOGFILE_SEVERITY_AUDIT_FAILURE',7);

	define('SCREEN_RESOURCE_GRAPH', 0);
	define('SCREEN_RESOURCE_SIMPLE_GRAPH', 1);
	define('SCREEN_RESOURCE_MAP', 2);
	define('SCREEN_RESOURCE_PLAIN_TEXT', 3);
	define('SCREEN_RESOURCE_HOSTS_INFO', 4);
	define('SCREEN_RESOURCE_TRIGGERS_INFO', 5);
	define('SCREEN_RESOURCE_SERVER_INFO', 6);
	define('SCREEN_RESOURCE_CLOCK', 7);
	define('SCREEN_RESOURCE_SCREEN', 8);
	define('SCREEN_RESOURCE_TRIGGERS_OVERVIEW', 9);
	define('SCREEN_RESOURCE_DATA_OVERVIEW', 10);
	define('SCREEN_RESOURCE_URL', 11);
	define('SCREEN_RESOURCE_ACTIONS', 12);
	define('SCREEN_RESOURCE_EVENTS',13);

/* alignes */
	define('HALIGN_DEFAULT',0);
	define('HALIGN_CENTER',	0);
	define('HALIGN_LEFT',	1);
	define('HALIGN_RIGHT',	2);

	define('VALIGN_DEFAULT',0);
	define('VALIGN_MIDDLE',	0);
	define('VALIGN_TOP',	1);
	define('VALIGN_BOTTOM',	2);

/* info module style */
	define('STYLE_HORISONTAL',	0);
	define('STYLE_VERTICAL',	1);

/* time module tipe */
        define('TIME_TYPE_LOCAL',	0);
        define('TIME_TYPE_SERVER',	1);

	define('FILTER_TAST_SHOW',	0);
	define('FILTER_TAST_HIDE',	1);
	define('FILTER_TAST_MARK',	2);
	define('FILTER_TAST_INVERT_MARK', 3);

	define('MARK_COLOR_RED',	1);
	define('MARK_COLOR_GREEN',	2);
	define('MARK_COLOR_BLUE',	3);

	define('PROFILE_TYPE_UNKNOWN',	0);
	define('PROFILE_TYPE_ARRAY',	1);
	define('PROFILE_TYPE_INT',	2);
	define('PROFILE_TYPE_STR',	3);

	define('CALC_FNC_MIN', 1);
	define('CALC_FNC_AVG', 2);
	define('CALC_FNC_MAX', 4);
	define('CALC_FNC_ALL', 7);

	
	define('SERVICE_TIME_TYPE_UPTIME', 0);
	define('SERVICE_TIME_TYPE_DOWNTIME', 1);
	define('SERVICE_TIME_TYPE_ONETIME_DOWNTIME', 2);

	define('USER_TYPE_ZABBIX_USER',		1);
	define('USER_TYPE_ZABBIX_ADMIN',	2);
	define('USER_TYPE_SUPER_ADMIN',		3);

	define('PERM_MAX',		3);
	define('PERM_READ_WRITE',	3);
	define('PERM_READ_ONLY',	2);
	define('PERM_READ_LIST',	1);
	define('PERM_DENY',		0);

	define('PERM_RES_STRING_LINE',	0); /* return string of nodes id - '1,2,3,4,5' */
	define('PERM_RES_IDS_ARRAY',	1); /* return array of nodes id - array(1,2,3,4) */
	define('PERM_RES_DATA_ARRAY',	2); 

	define('PERM_MODE_NE',	5);
	define('PERM_MODE_EQ',	4);
	define('PERM_MODE_GT',	3);
	define('PERM_MODE_LT',	2);
	define('PERM_MODE_LE',	1);
	define('PERM_MODE_GE',	0);

	define('RESOURCE_TYPE_NODE',		0);
	define('RESOURCE_TYPE_GROUP',		1);

	define('PARAM_TYPE_SECONDS',	0);
	define('PARAM_TYPE_COUNTS',	1);

	define('ZBX_NODE_REMOTE',	0);
	define('ZBX_NODE_LOCAL',	1);
	define('ZBX_NODE_MASTER',	2);

	define('HTTPTEST_STATUS_ACTIVE',	0);
	define('HTTPTEST_STATUS_DISABLED',	1);

	define('HTTPTEST_STATE_IDLE',	0);
	define('HTTPTEST_STATE_BUSY',	1);
	define('HTTPTEST_STATE_UNKNOWN',3);

	define('HTTPSTEP_ITEM_TYPE_RSPCODE',	0);
	define('HTTPSTEP_ITEM_TYPE_TIME',	1);
	define('HTTPSTEP_ITEM_TYPE_IN',		2);
	define('HTTPSTEP_ITEM_TYPE_LASTSTEP',	3);

	define('EVENT_SOURCE_TRIGGERS',		0);
	define('EVENT_SOURCE_DISCOVERY',	1);

	define('EVENT_OBJECT_TRIGGER',		0);
	define('EVENT_OBJECT_DHOST',		1);
	define('EVENT_OBJECT_DSERVICE',		2);

	define('DOBJECT_STATUS_UP',	0);
	define('DOBJECT_STATUS_DOWN',	1);

	define('DRULE_STATUS_ACTIVE',		0);
	define('DRULE_STATUS_DISABLED',		1);

	define('DSVC_STATUS_ACTIVE',		0);
	define('DSVC_STATUS_DISABLED',		1);

	define('SVC_SSH',	0);
	define('SVC_LDAP',	1);
	define('SVC_SMTP',	2);
	define('SVC_FTP',	3);
	define('SVC_HTTP',	4);
	define('SVC_POP',	5);
	define('SVC_NNTP',	6);
	define('SVC_IMAP',	7);
	define('SVC_TCP',	8);
	define('SVC_AGENT',	9);
	define('SVC_SNMPv1',	10);
	define('SVC_SNMPv2',	11);

	define('DHOST_STATUS_ACTIVE',		0);
	define('DHOST_STATUS_DISABLED',		1);
	
	define('BR',	"<br/>\n");
	define('SPACE',	'&nbsp;');
	define('RARR',	'&rArr;');

	define('ZBX_EREG_SPACES', '([[:space:]]+){0,1}');
	define('ZBX_EREG_INTERNAL_NAMES', '([0-9a-zA-Z_.[:space:][.-.]$]+)');
	define('ZBX_EREG_KEY_NAME', '([0-9a-zA-Z_.,[.:.][:space:][.-.]$]+)');
	define('ZBX_EREG_PARAMS', '([[:print:]]+){0,1}');
	define('ZBX_EREG_SIGN', '([&|><=+*/#[.-.]])');
	define('ZBX_EREG_NUMBER', '([[.-.]+]*[0-9]+[.]{0,1}[0-9]*[A-Z]{0,1})');

	/* Character '-' must be last in the list of symbols, otherwise it won't be accepted */
	define('ZBX_EREG_DNS_FORMAT', '([0-9a-zA-Z\_\.\$[.-.]]+)');
	define('ZBX_EREG_HOST_FORMAT', ZBX_EREG_INTERNAL_NAMES);
	define('ZBX_EREG_NODE_FORMAT', ZBX_EREG_INTERNAL_NAMES);
	define('ZBX_EREG_ITEM_KEY_FORMAT', '('.ZBX_EREG_KEY_NAME.'(\['.ZBX_EREG_PARAMS.'\]){0,1})');
	define('ZBX_KEY_ID', 1);
	define('ZBX_KEY_NAME_ID', 2);
	define('ZBX_KEY_PARAM_ID', 4);
	define('ZBX_EREG_FUNCTION_FORMAT', '('.ZBX_EREG_INTERNAL_NAMES.'(\('.ZBX_EREG_PARAMS.'\)))');

	define('ZBX_EREG_SIMPLE_EXPRESSION_FORMAT','(\{'.ZBX_EREG_HOST_FORMAT.'\:'.ZBX_EREG_ITEM_KEY_FORMAT.'\.'.ZBX_EREG_FUNCTION_FORMAT.'\})');
	define('ZBX_EREG_MACRO_NAME_FORMAT', '(\{[A-Z\.]+\})');

	define('ZBX_SIMPLE_EXPRESSION_HOST_ID', 2);
	define('ZBX_SIMPLE_EXPRESSION_KEY_ID', 2 + ZBX_KEY_ID);
	define('ZBX_SIMPLE_EXPRESSION_KEY_NAME_ID', 2 + ZBX_KEY_NAME_ID);
	define('ZBX_SIMPLE_EXPRESSION_KEY_PARAM_ID', 2 + ZBX_KEY_PARAM_ID);
	define('ZBX_SIMPLE_EXPRESSION_FUNCTION_ID', 7);
	define('ZBX_SIMPLE_EXPRESSION_FUNCTION_NAME_ID', 8);
	define('ZBX_SIMPLE_EXPRESSION_FUNCTION_PARAM_ID', 10);

	define('ZBX_EREG_EXPRESSION_TOKEN_FORMAT', '^([[:print:]]*)('.ZBX_EREG_SIMPLE_EXPRESSION_FORMAT.'|'.ZBX_EREG_MACRO_NAME_FORMAT.')([[:print:]]*)$');

	define('ZBX_EXPRESSION_LEFT_ID', 1);
	define('ZBX_EXPRESSION_SIMPLE_EXPRESSION_ID', 2);
	define('ZBX_EXPRESSION_MACRO_ID', 13);
	define('ZBX_EXPRESSION_RIGHT_ID', 14);

	define('ZBX_MIN_PERIOD', 3600);
	define('ZBX_MAX_PERIOD', 12*31*24*3600);
	define('ZBX_PERIOD_DEFAULT', ZBX_MIN_PERIOD);

	global $_GET, $_POST, $_COOKIE, $_REQUEST;

/* Support for PHP5. PHP5 does not have $HTTP_..._VARS */
	if (!function_exists('version_compare'))
	{
		$_GET		= $HTTP_GET_VARS;
		$_POST		= $HTTP_POST_VARS;
		$_COOKIE	= $HTTP_COOKIE_VARS;
	}

/* if magic quotes on then get rid of them */
	if (get_magic_quotes_gpc()) {
		$_GET    = zbx_stripslashes($_GET);
		$_POST	 = zbx_stripslashes($_POST);
		$_COOKIE = zbx_stripslashes($_COOKIE);
	}

/* init $_REQUEST */
	ini_set('variables_order', 'GP');
	$_REQUEST = $_POST + $_GET;

?>
