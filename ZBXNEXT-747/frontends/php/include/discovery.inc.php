<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
	require_once('include/perm.inc.php');


	function check_right_on_discovery($permission){
		global $USER_DETAILS;

		if($USER_DETAILS['type'] >= USER_TYPE_ZABBIX_ADMIN){
			if(count(get_accessible_nodes_by_user($USER_DETAILS, $permission, PERM_RES_IDS_ARRAY)))
				return true;
		}
	return false;
	}

	function svc_default_port($type_int){
		$typePort = array(
			SVC_SSH =>		'22',
			SVC_LDAP =>		'389',
			SVC_SMTP =>		'25',
			SVC_FTP =>		'21',
			SVC_HTTP =>		'80',
			SVC_POP =>		'110',
			SVC_NNTP =>		'119',
			SVC_IMAP =>		'143',
			SVC_AGENT =>	'10050',
			SVC_SNMPv1 =>	'161',
			SVC_SNMPv2c =>	'161',
			SVC_SNMPv3 =>	'161',
			SVC_HTTPS =>	'443',
			SVC_TELNET =>	'23',
		);

		return isset($typePort[$type_int]) ? $typePort[$type_int] : 0;
	}

	function discovery_check_type2str($type=null){
		$discovery_types = array(
			SVC_SSH => S_SSH,
			SVC_LDAP => S_LDAP,
			SVC_SMTP => S_SMTP,
			SVC_FTP => S_FTP,
			SVC_HTTP => S_HTTP,
			SVC_POP => S_POP,
			SVC_NNTP => S_NNTP,
			SVC_IMAP => S_IMAP,
			SVC_TCP => S_TCP,
			SVC_AGENT => _('Zabbix agent'),
			SVC_SNMPv1 => _('SNMPv1 agent'),
			SVC_SNMPv2c => _('SNMPv2 agent'),
			SVC_SNMPv3 => _('SNMPv3 agent'),
			SVC_ICMPPING => S_ICMPPING,
			SVC_TELNET => _('Telnet'),
			SVC_HTTPS => _('HTTPS'),
		);

		if(is_null($type)){
			order_result($discovery_types);
			return $discovery_types;
		}
		else if(isset($discovery_types[$type]))
			return $discovery_types[$type];
		else
			return false;
	}

	function discovery_check2str($type, $key, $port){
		$external_param = '';

		if(!empty($key)){
			switch($type){
				case SVC_SNMPv1:
				case SVC_SNMPv2c:
				case SVC_SNMPv3:
				case SVC_AGENT:
					$external_param = ' "'.$key.'"';
					break;
			}
		}
		$result = discovery_check_type2str($type);
		if((svc_default_port($type) != $port) || ($type == SVC_TCP))
			$result .= ' ('.$port.')';
		$result .= $external_param;

		return $result;
	}

	function discovery_port2str($type_int, $port){
		$port_def = svc_default_port($type_int);

		if($port != $port_def){
			return ' ('.$port.')';
		}

	return '';
	}

	function discovery_status2str($status=null){
		$discovery_statuses = array(
			DRULE_STATUS_ACTIVE => _('Active'),
			DRULE_STATUS_DISABLED => _('Disabled'),
		);

		if(is_null($status)){
			return $discovery_statuses;
		}
		else if(isset($discovery_statuses[$status]))
			return $discovery_statuses[$status];
		else
			return _('Unknown');
	}

	function discovery_status2style($status){
		switch($status){
			case DRULE_STATUS_ACTIVE: $status = 'off'; break;
			case DRULE_STATUS_DISABLED: $status = 'on'; break;
			default: $status = 'unknown'; break;
		}
		return $status;
	}

	function discovery_object_status2str($status=null){
		$statuses = array(
			DOBJECT_STATUS_UP => S_UP,
			DOBJECT_STATUS_DOWN => S_DOWN,
			DOBJECT_STATUS_DISCOVER => S_DISCOVERED,
			DOBJECT_STATUS_LOST => S_LOST,
		);

		if(is_null($status)){
			order_result($statuses);
			return $statuses;
		}
		else if(isset($statuses[$status]))
			return $statuses[$status];
		else
			return S_UNKNOWN;
	}

	function get_discovery_rule_by_druleid($druleid){
		return DBfetch(DBselect('select * from drules where druleid='.$druleid));
	}

	function delete_discovery_rule($druleid){
		$actionids = array();
// conditions
		$sql = 'SELECT DISTINCT actionid '.
				' FROM conditions '.
				' WHERE conditiontype='.CONDITION_TYPE_DRULE.
					" AND value='$druleid'";

		$db_actions = DBselect($sql);
		while($db_action = DBfetch($db_actions))
			$actionids[] = $db_action['actionid'];

// disabling actions with deleted conditions
		if(!empty($actionids)){
			DBexecute('UPDATE actions '.
					' SET status='.ACTION_STATUS_DISABLED.
					' WHERE '.DBcondition('actionid', $actionids));

// delete action conditions
			DBexecute('DELETE FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_DRULE.
					" AND value='$druleid'");
		}

		$result = DBexecute('delete from drules where druleid='.$druleid);

		return $result;
	}
?>
