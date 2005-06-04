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
//	MISC PARAMETERS
	define("MAP_OUTPUT_FORMAT",		"DEFAULT");
#	define("MAP_OUTPUT_FORMAT",		"JPG");
//	END OF MISC PARAMETERS

	define("AUDIT_ACTION_ADD",		0);
	define("AUDIT_ACTION_UPDATE",		1);
	define("AUDIT_ACTION_DELETE",		2);
	define("AUDIT_ACTION_LOGIN",		3);
	define("AUDIT_ACTION_LOGOUT",		4);

	define("AUDIT_RESOURCE_USER",		0);
	define("AUDIT_RESOURCE_ZABBIX",		1);
	define("AUDIT_RESOURCE_ZABBIX_CONFIG",	2);
	define("AUDIT_RESOURCE_MEDIA_TYPE",	3);
	define("AUDIT_RESOURCE_HOST",		4);
	define("AUDIT_RESOURCE_ACTION",		5);
	define("AUDIT_RESOURCE_GRAPH",		6);
	define("AUDIT_RESOURCE_GRAPH_ELEMENT",	7);
	define("AUDIT_RESOURCE_ESCALATION",	8);
	define("AUDIT_RESOURCE_ESCALATION_RULE",9);



	define("HOST_STATUS_MONITORED",		0);
	define("HOST_STATUS_NOT_MONITORED",	1);
//	define("HOST_STATUS_UNREACHABLE",	2);
	define("HOST_STATUS_TEMPLATE",		3);
	define("HOST_STATUS_DELETED",		4);

	define("HOST_AVAILABLE_UNKNOWN",	0);
	define("HOST_AVAILABLE_TRUE",		1);
	define("HOST_AVAILABLE_FALSE",		2);

	define("GRAPH_DRAW_TYPE_LINE",0);
	define("GRAPH_DRAW_TYPE_FILL",1);
	define("GRAPH_DRAW_TYPE_BOLDLINE",2);
	define("GRAPH_DRAW_TYPE_DOT",3);
	define("GRAPH_DRAW_TYPE_DASHEDLINE",4);

	define("GRAPH_YAXIS_TYPE_CALCULATED",0);
	define("GRAPH_YAXIS_TYPE_FIXED",1);

	define("MAP_LABEL_TYPE_HOSTLABEL",0);
	define("MAP_LABEL_TYPE_IP",1);
	define("MAP_LABEL_TYPE_HOSTNAME",2);
	define("MAP_LABEL_TYPE_STATUS",3);
	define("MAP_LABEL_TYPE_NOTHING",4);

	define("ITEM_TYPE_ZABBIX",0);
	define("ITEM_TYPE_SNMPV1",1);
	define("ITEM_TYPE_TRAPPER",2);
	define("ITEM_TYPE_SIMPLE",3);
	define("ITEM_TYPE_SNMPV2C",4);
	define("ITEM_TYPE_INTERNAL",5);
	define("ITEM_TYPE_SNMPV3",6);
	define("ITEM_TYPE_ZABBIX_ACTIVE",7);

	define("ITEM_VALUE_TYPE_FLOAT",0);
	define("ITEM_VALUE_TYPE_STR",1);
	define("ITEM_VALUE_TYPE_LOG",2);

	define("ITEM_STATUS_ACTIVE",0);
	define("ITEM_STATUS_DISABLED",1);
	define("ITEM_STATUS_NOTSUPPORTED",3);

	define("ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV",0);
	define("ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV",1);
	define("ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV",2);

	define("SERVICE_ALGORITHM_NONE",0);
	define("SERVICE_ALGORITHM_MAX",1);
	define("SERVICE_ALGORITHM_MIN",2);

	define("TRIGGER_VALUE_FALSE",0);
	define("TRIGGER_VALUE_TRUE",1);
	define("TRIGGER_VALUE_UNKNOWN",2);

	define("RECIPIENT_TYPE_USER",0);
	define("RECIPIENT_TYPE_GROUP",1);

/* Support for PHP5. PHP5 does not have $HTTP_..._VARS */
	if (!function_exists('version_compare'))
	{
		$_GET = $HTTP_GET_VARS;
		$_POST = $HTTP_POST_VARS;
		$_COOKIE = $HTTP_COOKIE_VARS;
	}
?>
