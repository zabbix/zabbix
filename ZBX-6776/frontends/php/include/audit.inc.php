<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
	function audit_resource2str($resource_type=null){
		$resources = array(
			AUDIT_RESOURCE_USER => S_USER,
			AUDIT_RESOURCE_ZABBIX_CONFIG => S_CONFIGURATION_OF_ZABBIX,
			AUDIT_RESOURCE_MEDIA_TYPE => S_MEDIA_TYPE,
			AUDIT_RESOURCE_HOST => S_HOST,
			AUDIT_RESOURCE_ACTION => S_ACTION,
			AUDIT_RESOURCE_GRAPH => S_GRAPH,
			AUDIT_RESOURCE_GRAPH_ELEMENT => S_GRAPH_ELEMENT,
			AUDIT_RESOURCE_USER_GROUP => S_USER_GROUP,
			AUDIT_RESOURCE_APPLICATION => S_APPLICATION,
			AUDIT_RESOURCE_TRIGGER => S_TRIGGER,
			AUDIT_RESOURCE_HOST_GROUP => S_HOST_GROUP,
			AUDIT_RESOURCE_ITEM => S_ITEM,
			AUDIT_RESOURCE_IMAGE => S_IMAGE,
			AUDIT_RESOURCE_VALUE_MAP => S_VALUE_MAP,
			AUDIT_RESOURCE_IT_SERVICE => S_IT_SERVICE,
			AUDIT_RESOURCE_MAP => S_MAP,
			AUDIT_RESOURCE_SCREEN => S_SCREEN,
			AUDIT_RESOURCE_NODE => S_NODE,
			AUDIT_RESOURCE_SCENARIO => S_SCENARIO,
			AUDIT_RESOURCE_DISCOVERY_RULE => S_DISCOVERY_RULE,
			AUDIT_RESOURCE_SLIDESHOW => S_SLIDESHOW,
			AUDIT_RESOURCE_PROXY => S_PROXY,
			AUDIT_RESOURCE_REGEXP => S_REGULAR_EXPRESSION,
			AUDIT_RESOURCE_MAINTENANCE => S_MAINTENANCE,
			AUDIT_RESOURCE_SCRIPT => S_SCRIPT,
			AUDIT_RESOURCE_MACRO => S_MACRO,
			AUDIT_RESOURCE_TEMPLATE => S_TEMPLATE,
		);

		if(is_null($resource_type)){
			natsort($resources);
			return $resources;
		}
		else if(isset($resources[$resource_type]))
			return $resources[$resource_type];
		else
			return S_UNKNOWN_RESOURCE;
	}

	function add_audit_if($condition,$action,$resourcetype,$details){
		if($condition)
			return add_audit($action,$resourcetype,$details);

		return false;
	}

	function add_audit($action,$resourcetype,$details){
		global $USER_DETAILS;

		if(!isset($USER_DETAILS['userid'])) return false;

		$auditid = get_dbid('auditlog','auditid');

		if(zbx_strlen($details) > 128)
			$details = zbx_substr($details, 0, 125).'...';

		$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];

		if(($result = DBexecute('INSERT INTO auditlog (auditid,userid,clock,action,resourcetype,details,ip) '.
			' VALUES ('.$auditid.','.$USER_DETAILS['userid'].','.time().','.
						$action.','.$resourcetype.','.zbx_dbstr($details).','.
						zbx_dbstr($ip).')')))
		{
			$result = $auditid;
		}

		return $result;
	}

	function add_audit_ext($action, $resourcetype, $resourceid, $resourcename, $table_name, $values_old, $values_new) {
		global $USER_DETAILS;

		if (!isset($USER_DETAILS['userid'])) {
			check_authorisation();
		}

		$values_diff = array();
		if ($action == AUDIT_ACTION_UPDATE && !empty($values_new)) {
			foreach ($values_new as $id => $value) {
				if ($values_old[$id] !== $value) {
					array_push($values_diff, $id);
				}
			}

			if (count($values_diff) == 0) {
				return true;
			}
		}

		$auditid = get_dbid('auditlog', 'auditid');

		if (zbx_strlen($resourcename) > 255) {
			$details = zbx_substr($resourcename, 0, 252).'...';
		}

		$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

		$result = DBexecute('INSERT INTO auditlog (auditid,userid,clock,ip,action,resourcetype,resourceid,resourcename)'.
				' values ('.$auditid.','.$USER_DETAILS['userid'].','.time().','.zbx_dbstr($ip).
				','.$action.','.$resourcetype.','.$resourceid.','.zbx_dbstr($resourcename).')');

		if ($result && $action == AUDIT_ACTION_UPDATE) {
			foreach ($values_diff as $id) {
				$auditdetailid = get_dbid('auditlog_details', 'auditdetailid');
				$result &= DBexecute('insert into auditlog_details (auditdetailid,auditid,table_name,field_name,oldvalue,newvalue)'.
						' values ('.$auditdetailid.','.$auditid.','.zbx_dbstr($table_name).','.
						zbx_dbstr($id).','.zbx_dbstr($values_old[$id]).','.zbx_dbstr($values_new[$id]).')');
			}
		}

		if ($result) {
			$result = $auditid;
		}

		return $result;
	}
?>
