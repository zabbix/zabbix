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
	function	audit_resource2str($resource_type)
	{
		$str_resource[AUDIT_RESOURCE_USER] 		= S_USER;
		$str_resource[AUDIT_RESOURCE_ZABBIX_CONFIG] 	= S_CONFIGURATION_OF_ZABBIX;
		$str_resource[AUDIT_RESOURCE_MEDIA_TYPE] 	= S_MEDIA_TYPE;
		$str_resource[AUDIT_RESOURCE_HOST] 		= S_HOST;
		$str_resource[AUDIT_RESOURCE_ACTION] 		= S_ACTION;
		$str_resource[AUDIT_RESOURCE_GRAPH] 		= S_GRAPH;
		$str_resource[AUDIT_RESOURCE_GRAPH_ELEMENT]	= S_GRAPH_ELEMENT;
		$str_resource[AUDIT_RESOURCE_USER_GROUP] 	= S_USER_GROUP;
		$str_resource[AUDIT_RESOURCE_APPLICATION] 	= S_APPLICATION;
		$str_resource[AUDIT_RESOURCE_TRIGGER] 		= S_TRIGGER;

		if(isset($str_resource[$resource_type]))
			return $str_resource[$resource_type];

		return S_UNKNOWN_RESOURCE;
	}

	function add_audit($action,$resourcetype,$details)
	{
		global $USER_DETAILS;

		$auditid	= get_dbid("auditlog","auditid");

		if(($result = DBexecute("insert into auditlog (auditid,userid,clock,action,resourcetype,details) ".
			" values ($auditid,".$USER_DETAILS["userid"].",".time().",$action,$resourcetype,".zbx_dbstr($details).")")))
		{
			$result = $auditid;
		}

		return $result;
	}
?>
